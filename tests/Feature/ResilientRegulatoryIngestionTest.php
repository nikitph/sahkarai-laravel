<?php

namespace Tests\Feature;

use App\Actions\Ingestion\CompleteTextExtraction;
use App\Actions\Interpretations\GenerateLocaleInterpretation;
use App\Actions\Notifications\NotifyRegulatoryUpdate;
use App\Actions\Videos\QueueExplainerVideo;
use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Enums\SupportedLocale;
use App\Jobs\Ingestion\ExtractDocumentText;
use App\Jobs\Ingestion\ExtractDocumentTextWithKimi;
use App\Jobs\Interpretations\GenerateInterpretation;
use App\Models\DocumentVersion;
use App\Models\RegulatoryDocument;
use App\Models\User;
use App\Services\Ingestion\CbicCircularAdapter;
use App\Services\Ingestion\KimiFileExtractor;
use App\Services\Ingestion\NabardCircularAdapter;
use App\Services\Ingestion\RbiNotificationAdapter;
use App\Support\Documents\ReadablePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ResilientRegulatoryIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_pdf_failure_falls_back_to_kimi_and_records_both_attempts(): void
    {
        Storage::fake('local');
        Queue::fake();
        config([
            'sahkarai.ingestion.storage_disk' => 'local',
            'sahkarai.ingestion.kimi.enabled' => true,
            'sahkarai.ingestion.kimi.api_key' => 'test-key',
            'sahkarai.ingestion.kimi.base_url' => 'https://kimi.test/v1',
        ]);
        [, $version] = $this->platformVersion();
        Storage::disk('local')->put($version->original_path, '%PDF-image-only');
        $native = Mockery::mock(ReadablePdf::class);
        $native->shouldReceive('extractText')->once()->andThrow(new RuntimeException('No native text.'));

        (new ExtractDocumentText($version->id))->handle(null, $native);

        $this->assertSame('kimi_pending', $version->refresh()->extraction_status);
        Queue::assertPushed(ExtractDocumentTextWithKimi::class);
        $deleted = false;
        Http::fake(function (Request $request) use (&$deleted) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'file-123', 'status' => 'ok', 'bytes' => 15], 200, ['x-request-id' => 'req-123']);
            }
            if ($request->method() === 'DELETE') {
                $deleted = true;

                return Http::response([], 204);
            }

            return Http::response("Extracted by Kimi\nwith OCR text.", 200, ['Content-Type' => 'text/plain']);
        });

        (new ExtractDocumentTextWithKimi($version->id))->handle(
            app(KimiFileExtractor::class),
            app(CompleteTextExtraction::class),
        );

        $version->refresh();
        $this->assertSame('ok', $version->extraction_status);
        $this->assertSame('kimi', $version->extraction_method);
        $this->assertSame(hash('sha256', $version->extracted_text), $version->extracted_text_sha256);
        $this->assertTrue($deleted);
        $this->assertDatabaseHas('extraction_attempts', ['document_version_id' => $version->id, 'method' => 'native', 'status' => 'failed']);
        $this->assertDatabaseHas('extraction_attempts', ['document_version_id' => $version->id, 'method' => 'kimi', 'status' => 'ok', 'request_id' => 'req-123']);
        Storage::disk('local')->assertExists($version->extracted_path);
        Queue::assertPushed(GenerateInterpretation::class);
    }

    public function test_exhausted_kimi_extraction_is_retained_for_review_but_invisible(): void
    {
        [$document, $version] = $this->platformVersion();
        $job = new ExtractDocumentTextWithKimi($version->id);

        $job->failed(new RuntimeException('OCR exhausted.'));

        $this->assertSame('needs_review', $version->refresh()->extraction_status);
        $this->assertNotNull($version->needs_review_at);
        $this->assertFalse($document->refresh()->is_public);
        $this->assertSame(0, RegulatoryDocument::query()->visibleTo(User::factory()->create())->count());
    }

    public function test_kimi_job_never_sends_a_private_user_upload(): void
    {
        config([
            'sahkarai.ingestion.kimi.enabled' => true,
            'sahkarai.ingestion.kimi.api_key' => 'test-key',
        ]);
        Http::fake();
        [$document, $version] = $this->platformVersion();
        $document->update(['uploaded_by_user_id' => User::factory()->create()->id]);

        (new ExtractDocumentTextWithKimi($version->id))->handle(
            app(KimiFileExtractor::class),
            app(CompleteTextExtraction::class),
        );

        Http::assertNothingSent();
        $this->assertSame('needs_review', $version->refresh()->extraction_status);
        $this->assertSame('Kimi extraction is limited to platform-owned PDF versions.', $version->extraction_error);
    }

    public function test_english_publishes_immediately_while_other_locales_retry_three_times(): void
    {
        Log::spy();
        Queue::fake();
        [$document, $version] = $this->platformVersion('ok');
        $generate = Mockery::mock(GenerateLocaleInterpretation::class);
        $generate->shouldReceive('handle')->andReturnUsing(function (DocumentVersion $ignored, SupportedLocale $locale): array {
            if ($locale !== SupportedLocale::English) {
                throw new RuntimeException("{$locale->value} unavailable");
            }

            return $this->interpretationPayload('en');
        });
        $notify = Mockery::mock(NotifyRegulatoryUpdate::class);
        $notify->shouldReceive('handle')->once();
        $video = Mockery::mock(QueueExplainerVideo::class);
        $video->shouldNotReceive('forArchive');
        $job = new GenerateInterpretation($version->id);

        foreach ([1, 2, 3] as $attempt) {
            try {
                $job->handle($generate, $notify, $video);
                $this->assertSame(3, $attempt);
            } catch (RuntimeException $exception) {
                $this->assertLessThan(3, $attempt);
                $this->assertSame('One or more locales require another generation attempt.', $exception->getMessage());
            }

            if ($attempt === 1) {
                $this->assertTrue($document->refresh()->is_public);
                $this->assertSame('partial', $version->refresh()->interpretation_status);
                $this->assertNotNull($version->interpretation->published_at);
                $this->assertSame(1, RegulatoryDocument::query()->visibleTo(User::factory()->create())->count());
            }
        }

        $this->assertSame(['en'], array_keys($version->interpretation->refresh()->locale_payloads));
        $this->assertSame(['en' => 1, 'hi' => 3, 'gu' => 3, 'mr' => 3], $version->interpretation->locale_attempts);
    }

    public function test_a_failed_new_revision_does_not_replace_the_visible_published_revision(): void
    {
        [$document, $published] = $this->platformVersion('ok', true);
        $failed = $document->versions()->create([
            'supersedes_id' => $published->id,
            'version' => 2,
            'status' => 'needs_review',
            'extraction_status' => 'needs_review',
            'interpretation_status' => 'pending',
            'original_path' => 'originals/rbi/failed.pdf',
            'mime_type' => 'application/pdf',
            'sha256' => str_repeat('b', 64),
            'acquired_at' => now(),
            'needs_review_at' => now(),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('archive.show', $document))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('document.latest_version.id', $published->id));
        $this->actingAs($user)->get(route('archive.show', [$document, 'version' => $failed->id]))->assertNotFound();
    }

    public function test_archive_import_is_integrity_checked_and_idempotent(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['sahkarai.ingestion.storage_disk' => 'local']);
        $root = storage_path('framework/testing/archive-'.uniqid());
        mkdir($root.'/cbic/2026', 0777, true);
        $pdf = '%PDF-1.4 archived circular';
        file_put_contents($root.'/cbic/2026/one.pdf', $pdf);
        file_put_contents($root.'/cbic/manifest.jsonl', json_encode([
            'status' => 'downloaded',
            'source' => 'cbic',
            'year' => 2026,
            'source_id' => '42',
            'title' => 'Archived CBIC circular',
            'source_url' => 'https://cbic.test/catalogue',
            'download_url' => 'https://cbic.test/content/42',
            'published_at' => '2026-04-01',
            'relative_path' => 'cbic/2026/one.pdf',
            'sha256' => hash('sha256', $pdf),
            'bytes' => strlen($pdf),
        ], JSON_THROW_ON_ERROR)."\n");

        $this->artisan('regulatory:archive-import', ['path' => $root, '--dry-run' => true])->assertSuccessful();
        $this->artisan('regulatory:archive-import', ['path' => $root])->assertSuccessful();
        $this->artisan('regulatory:archive-import', ['path' => $root])->assertSuccessful();

        $this->assertDatabaseCount('regulatory_documents', 1);
        $this->assertDatabaseCount('document_versions', 1);
        $this->assertDatabaseHas('regulatory_documents', ['source' => 'cbic', 'source_document_id' => '42', 'is_backfill' => true, 'is_public' => false]);
    }

    public function test_cbic_adapter_discovers_catalogue_rows_and_repairs_content_urls(): void
    {
        config([
            'sahkarai.ingestion.sources.cbic.base_url' => 'https://cbic.test',
            'sahkarai.ingestion.sources.cbic.years' => 1,
        ]);
        Http::fake(['https://cbic.test/api/*' => Http::response([[
            'id' => 91,
            'circularName' => 'GST clarification',
            'circularDt' => now()->format('Y-m-d'),
            'docFilePath' => 'tax_repository\\customs\\Circulars\\Circular 1.pdf',
        ]])]);

        $candidate = collect((new CbicCircularAdapter)->discover())->sole();

        $this->assertSame(RegulatorySource::Cbic, $candidate->source);
        $this->assertSame('91', $candidate->sourceDocumentId);
        $this->assertSame('https://cbic.test/content/pdf/tax_repository/customs/circulars/Circular%201.pdf', $candidate->downloadUrl);
    }

    public function test_rbi_adapter_submits_the_year_form_and_discovers_official_pdfs(): void
    {
        config([
            'sahkarai.ingestion.sources.rbi.notifications_url' => 'https://rbi.test/notifications',
            'sahkarai.ingestion.sources.rbi.years' => 1,
        ]);
        Http::fake(fn (Request $request) => $request->method() === 'GET'
            ? Http::response('<input type="hidden" name="__VIEWSTATE" value="state">')
            : Http::response(<<<'HTML'
                <b>27 August 2026</b>
                <a href=NotificationUser.aspx?Id=123&amp;Mode=0 class="link2">RBI prudential circular</a>
                <a href="https://rbidocs.rbi.org.in/rdocs/notification/PDFs/ABC.PDF">PDF</a>
                HTML));

        $candidate = collect((new RbiNotificationAdapter)->discover())->sole();

        $this->assertSame('123', $candidate->sourceDocumentId);
        $this->assertSame('RBI prudential circular', $candidate->title);
        $this->assertSame('2026-08-27', $candidate->publishedAt?->toDateString());
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->body(), 'hdnYear=2026'));
    }

    public function test_nabard_adapter_deduplicates_repeated_pdf_anchors_in_a_row(): void
    {
        config([
            'sahkarai.ingestion.sources.nabard.circulars_url' => 'https://nabard.test/circulars.aspx',
            'sahkarai.ingestion.sources.nabard.years' => 1,
        ]);
        Http::fake(['https://nabard.test/*' => Http::response(<<<'HTML'
            <form>
              <div class="circulars_row">
                <div class="circulars_date"><span>05 August 2026</span></div>
                <a href="/files/circular.PDF">Operational guidelines</a>
                <a href="/files/circular.PDF"><img alt="PDF"></a>
              </div>
            </form>
            HTML)]);

        $candidate = collect((new NabardCircularAdapter)->discover())->sole();

        $this->assertSame('Operational guidelines', $candidate->title);
        $this->assertSame('https://nabard.test/files/circular.PDF', $candidate->downloadUrl);
        $this->assertSame('2026-08-05', $candidate->publishedAt?->toDateString());
    }

    /** @return array{RegulatoryDocument, DocumentVersion} */
    private function platformVersion(string $extractionStatus = 'pending', bool $published = false): array
    {
        $document = RegulatoryDocument::create([
            'source' => RegulatorySource::Rbi,
            'source_document_id' => uniqid('rbi-', true),
            'title' => 'Pipeline test circular',
            'document_type' => DocumentType::Circular,
            'applicability' => Applicability::Generic,
            'published_at' => now(),
            'is_public' => $published,
        ]);
        $version = $document->versions()->create([
            'version' => 1,
            'status' => $published ? 'published' : 'acquired',
            'extraction_status' => $published ? 'ok' : $extractionStatus,
            'interpretation_status' => $published ? 'published' : 'pending',
            'original_path' => 'originals/rbi/pipeline.pdf',
            'original_filename' => 'pipeline.pdf',
            'mime_type' => 'application/pdf',
            'sha256' => str_repeat('a', 64),
            'extracted_text' => $extractionStatus === 'ok' || $published ? 'Extracted source text.' : null,
            'acquired_at' => now(),
        ]);
        if ($published) {
            $version->interpretation()->create([
                'status' => 'published',
                'locale_payloads' => ['en' => $this->interpretationPayload('en')],
                'published_at' => now(),
            ]);
        }

        return [$document, $version];
    }

    /** @return array<string, mixed> */
    private function interpretationPayload(string $locale): array
    {
        return [
            'locale' => $locale,
            'summary' => implode(' ', array_fill(0, 150, "{$locale}-word")),
            'takeaways' => ['Review the circular.', 'Assign an owner.', 'Track implementation.'],
            'glossary' => [],
            'deadlines' => [],
            'applicability_tags' => ['generic'],
            'effective_date' => null,
            'document_type' => 'circular',
        ];
    }
}
