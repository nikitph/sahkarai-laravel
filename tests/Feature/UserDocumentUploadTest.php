<?php

namespace Tests\Feature;

use App\Actions\Videos\BuildExplainerLesson;
use App\Contracts\Videos\ExplainerVideoGenerator;
use App\Data\RenderedExplainerVideo;
use App\Enums\Applicability;
use App\Enums\CreditReason;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Jobs\Ingestion\ExtractDocumentText;
use App\Jobs\Videos\GenerateExplainerVideo;
use App\Models\DocumentVersion;
use App\Models\RegulatoryDocument;
use App\Models\User;
use App\Support\Documents\ExtractedTextNormalizer;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_tier_two_user_can_upload_a_private_pdf_into_the_existing_pipeline(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->tier2()->create();

        $response = $this->actingAs($user)->post(route('archive.uploads.store'), [
            'title' => 'Member compliance circular',
            'published_at' => '2026-07-22',
            'description' => 'Internal compliance reference.',
            'document' => $this->pdfUpload(),
        ]);

        $document = RegulatoryDocument::query()->sole();
        $response->assertRedirect(route('archive.show', $document));
        $this->assertSame(RegulatorySource::UserUpload, $document->source);
        $this->assertSame($user->getKey(), $document->uploaded_by_user_id);
        $this->assertSame('Internal compliance reference.', $document->upload_description);
        $this->assertSame('2026-07-22', $document->published_at?->toDateString());
        $version = $document->versions()->sole();
        $this->assertSame('application/pdf', $version->mime_type);
        $this->assertSame(64, strlen($version->sha256));
        Storage::disk('local')->assertExists($version->original_path);
        Queue::assertPushed(ExtractDocumentText::class, fn (ExtractDocumentText $job) => $job->documentVersionId === $version->getKey());
    }

    public function test_only_tier_two_tier_three_and_admin_users_can_upload(): void
    {
        $this->assertFalse(User::factory()->create()->can('upload', RegulatoryDocument::class));
        $this->assertFalse(User::factory()->tier1()->create()->can('upload', RegulatoryDocument::class));
        $this->assertTrue(User::factory()->tier2()->create()->can('upload', RegulatoryDocument::class));
        $this->assertTrue(User::factory()->tier3()->create()->can('upload', RegulatoryDocument::class));
        $this->assertTrue(User::factory()->admin()->create()->can('upload', RegulatoryDocument::class));

        $this->actingAs(User::factory()->tier1()->create())
            ->post(route('archive.uploads.store'), [
                'title' => 'Forbidden',
                'document' => $this->pdfUpload(),
            ])
            ->assertForbidden();
    }

    public function test_upload_validation_rejects_oversized_and_unreadable_files(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->tier2()->create();

        $this->actingAs($user)->post(route('archive.uploads.store'), [
            'title' => 'Too large',
            'document' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf'),
        ])->assertSessionHasErrors('document');

        $this->actingAs($user)->post(route('archive.uploads.store'), [
            'title' => 'Unreadable',
            'document' => UploadedFile::fake()->createWithContent('broken.pdf', "%PDF-1.4\nnot a readable document"),
        ])->assertSessionHasErrors('document');

        $this->actingAs($user)->post(route('archive.uploads.store'), [
            'title' => 'Encrypted',
            'document' => UploadedFile::fake()->createWithContent('encrypted.pdf', "%PDF-1.4\n/Encrypt 2 0 R\n%%EOF"),
        ])->assertSessionHasErrors('document');

        $this->assertDatabaseCount('regulatory_documents', 0);
        Queue::assertNothingPushed();
    }

    public function test_user_uploads_are_private_across_archive_download_chat_and_export_entry_points(): void
    {
        Storage::fake('local');
        [$document, $version] = $this->privateDocument(User::factory()->tier2()->create(), 'Alice private memo');
        $intruder = User::factory()->tier2()->create();

        $this->actingAs($intruder)->get(route('archive.index'))
            ->assertOk()
            ->assertDontSee('Alice private memo');
        $this->actingAs($intruder)->get(route('archive.show', $document))->assertForbidden();
        $this->actingAs($intruder)->get(route('archive.download', $document))->assertForbidden();
        $this->actingAs($intruder)->post(route('chats.store', $document), ['version' => $version->getKey()])->assertForbidden();
        $interpretation = $version->interpretation()->sole();
        $this->actingAs($intruder)->get(route('interpretations.export', [$interpretation, 'md']))->assertForbidden();
        $this->actingAs($intruder)->post(route('interpretations.issues.store', $interpretation), [
            'locale' => 'en',
            'description' => 'Attempt to access another user upload.',
        ])->assertForbidden();
    }

    public function test_owner_can_delete_upload_and_related_files_but_cannot_delete_platform_documents(): void
    {
        Storage::fake('local');
        $owner = User::factory()->tier2()->create();
        [$document, $version] = $this->privateDocument($owner, 'Disposable memo');
        Storage::disk('local')->put($version->original_path, 'pdf');
        Storage::disk('local')->put($version->extracted_path, 'text');

        $this->actingAs($owner)
            ->delete(route('archive.uploads.destroy', $document))
            ->assertRedirect(route('archive.index'));

        $this->assertDatabaseMissing('regulatory_documents', ['id' => $document->getKey()]);
        Storage::disk('local')->assertMissing($version->original_path);
        Storage::disk('local')->assertMissing($version->extracted_path);

        $platform = RegulatoryDocument::query()->create([
            'source' => RegulatorySource::Rbi,
            'source_document_id' => (string) Str::uuid(),
            'title' => 'Platform circular',
            'document_type' => DocumentType::Circular,
            'applicability' => Applicability::Generic,
        ]);
        $this->actingAs($owner)->delete(route('archive.uploads.destroy', $platform))->assertForbidden();
    }

    public function test_chat_is_hidden_and_rejected_until_extraction_and_interpretation_are_ready(): void
    {
        $user = User::factory()->tier2()->create();
        $document = RegulatoryDocument::query()->create([
            'source' => RegulatorySource::UserUpload,
            'source_document_id' => (string) Str::uuid(),
            'title' => 'Processing upload',
            'document_type' => DocumentType::Other,
            'applicability' => Applicability::Generic,
            'uploaded_by_user_id' => $user->getKey(),
        ]);
        $version = $document->versions()->create([
            'version' => 1,
            'status' => 'extracted',
            'extraction_status' => 'ok',
            'interpretation_status' => 'pending',
            'original_path' => 'originals/user-uploads/processing.pdf',
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', 'processing'),
            'extracted_text' => 'Readable but interpretation is not ready.',
            'acquired_at' => now(),
        ]);

        $this->actingAs($user)->get(route('archive.show', $document))
            ->assertInertia(fn ($page) => $page->where('capabilities.chat', false));
        $this->actingAs($user)->post(route('chats.store', $document), [
            'version' => $version->getKey(),
        ])->assertConflict();
        $this->assertDatabaseCount('chats', 0);
    }

    public function test_existing_malformed_extracted_text_is_sanitized_before_ai_use(): void
    {
        $version = new DocumentVersion([
            'extracted_text' => "Reserve Bank\0 of India \xC3\x28 directions",
        ]);

        $text = $version->sourceText();

        $this->assertTrue(mb_check_encoding($text, 'UTF-8'));
        $this->assertSame(app(ExtractedTextNormalizer::class)->normalize($text), $text);
        $this->assertNotFalse(json_encode(['prompt' => $text]));
    }

    public function test_tier_two_owner_can_queue_one_private_explainer_video_for_ten_credits(): void
    {
        Queue::fake();
        $owner = User::factory()->tier2()->create(['credits_balance' => 20]);
        [$document, $version] = $this->privateDocument($owner, 'Video memo');
        $version->update(['status' => 'published', 'interpretation_status' => 'published']);

        $request = ['version' => $version->getKey(), 'locale' => 'en'];
        $this->actingAs($owner)->post(route('explainer-videos.store', $document), $request)->assertRedirect();
        $this->actingAs($owner)->post(route('explainer-videos.store', $document), $request)->assertRedirect();

        $video = $version->explainerVideo()->sole();
        $this->assertSame('queued', $video->status->value);
        $this->assertSame('queued', $version->refresh()->video_status);
        $this->assertSame(10, $owner->refresh()->credits_balance);
        $this->assertDatabaseCount('explainer_videos', 1);
        $this->assertDatabaseCount('credit_ledger', 1);
        $this->assertDatabaseHas('credit_ledger', [
            'user_id' => $owner->getKey(),
            'amount' => -10,
            'reason' => CreditReason::DebitExplainerVideo->value,
        ]);
        Queue::assertPushed(GenerateExplainerVideo::class, 1);
    }

    public function test_private_video_generation_requires_the_owner_and_tier_two_capability(): void
    {
        Queue::fake();
        $tierOneOwner = User::factory()->tier1()->create(['credits_balance' => 20]);
        [$document, $version] = $this->privateDocument($tierOneOwner, 'Restricted video memo');
        $version->update(['status' => 'published', 'interpretation_status' => 'published']);

        $this->actingAs($tierOneOwner)->post(route('explainer-videos.store', $document), ['version' => $version->getKey()])->assertForbidden();
        $this->actingAs(User::factory()->tier2()->create(['credits_balance' => 20]))
            ->post(route('explainer-videos.store', $document), ['version' => $version->getKey()])
            ->assertForbidden();

        $this->assertSame(20, $tierOneOwner->refresh()->credits_balance);
        $this->assertDatabaseCount('explainer_videos', 0);
    }

    public function test_private_video_generation_does_not_queue_or_debit_without_ten_credits(): void
    {
        Queue::fake();
        $owner = User::factory()->tier2()->create(['credits_balance' => 9]);
        [$document, $version] = $this->privateDocument($owner, 'Insufficient video credits');
        $version->update(['status' => 'published', 'interpretation_status' => 'published']);

        $this->actingAs($owner)
            ->post(route('explainer-videos.store', $document), ['version' => $version->getKey()])
            ->assertSessionHasErrors('credits');

        $this->assertSame(9, $owner->refresh()->credits_balance);
        $this->assertDatabaseCount('explainer_videos', 0);
        $this->assertDatabaseCount('credit_ledger', 0);
        Queue::assertNothingPushed();
    }

    public function test_video_job_persists_artifacts_and_marks_the_document_ready(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->tier2()->create(['credits_balance' => 20]);
        [$document, $version] = $this->privateDocument($owner, 'Rendered video memo');
        $version->update(['status' => 'published', 'interpretation_status' => 'published']);
        $this->actingAs($owner)->post(route('explainer-videos.store', $document), ['version' => $version->getKey()]);
        $video = $version->explainerVideo()->sole();
        $generator = new class implements ExplainerVideoGenerator
        {
            public function generate(array $lesson, string $workingDirectory): RenderedExplainerVideo
            {
                File::ensureDirectoryExists($workingDirectory);
                File::put($workingDirectory.'/final.mp4', 'video-bytes');
                File::put($workingDirectory.'/build-manifest.json', '{"ok":true}');

                return new RenderedExplainerVideo(
                    $workingDirectory.'/final.mp4',
                    $workingDirectory.'/build-manifest.json',
                    65000,
                    hash('sha256', json_encode($lesson, JSON_THROW_ON_ERROR)),
                    'test-pipeline/1.0.0',
                );
            }
        };

        (new GenerateExplainerVideo($video->getKey()))->handle(app(BuildExplainerLesson::class), $generator);

        $video->refresh();
        $this->assertSame('ready', $video->status->value);
        $this->assertSame('ready', $version->refresh()->video_status);
        $this->assertSame(65000, $video->duration_ms);
        Storage::disk('local')->assertExists($video->video_path);
        Storage::disk('local')->assertExists($video->manifest_path);
    }

    public function test_terminal_private_video_failure_refunds_the_original_debit(): void
    {
        Queue::fake();
        $owner = User::factory()->tier2()->create(['credits_balance' => 20]);
        [$document, $version] = $this->privateDocument($owner, 'Refunded video memo');
        $version->update(['status' => 'published', 'interpretation_status' => 'published']);
        $this->actingAs($owner)->post(route('explainer-videos.store', $document), ['version' => $version->getKey()]);
        $video = $version->explainerVideo()->sole();

        (new GenerateExplainerVideo($video->getKey()))->failed(new \RuntimeException('Renderer unavailable'));

        $this->assertSame(20, $owner->refresh()->credits_balance);
        $this->assertSame('failed', $video->refresh()->status->value);
        $this->assertDatabaseHas('credit_ledger', [
            'user_id' => $owner->getKey(),
            'amount' => 10,
            'reason' => CreditReason::RefundExplainerVideo->value,
        ]);
    }

    /** @return array{RegulatoryDocument, DocumentVersion} */
    private function privateDocument(User $owner, string $title): array
    {
        $document = RegulatoryDocument::query()->create([
            'source' => RegulatorySource::UserUpload,
            'source_document_id' => (string) Str::uuid(),
            'title' => $title,
            'document_type' => DocumentType::Other,
            'applicability' => Applicability::Generic,
            'uploaded_by_user_id' => $owner->getKey(),
        ]);
        $version = $document->versions()->create([
            'version' => 1,
            'status' => 'extracted',
            'extraction_status' => 'ok',
            'interpretation_status' => 'pending',
            'original_path' => "originals/user-uploads/{$owner->getKey()}/memo.pdf",
            'original_filename' => 'memo.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'sha256' => hash('sha256', (string) Str::uuid()),
            'extracted_text' => 'Private document text.',
            'extracted_path' => "extracted/user-uploads/{$owner->getKey()}/memo.txt",
            'acquired_at' => now(),
        ]);
        $version->interpretation()->create([
            'status' => 'published',
            'locale_payloads' => [
                'en' => [
                    'summary' => 'Private interpretation.',
                    'takeaways' => ['One', 'Two', 'Three'],
                    'glossary' => [],
                    'deadlines' => [],
                ],
            ],
            'published_at' => now(),
        ]);

        return [$document, $version];
    }

    private function pdfUpload(): UploadedFile
    {
        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml('<p>This compliance circular contains readable regulatory text for interpretation.</p>');
        $dompdf->render();

        return UploadedFile::fake()->createWithContent('member-circular.pdf', $dompdf->output());
    }
}
