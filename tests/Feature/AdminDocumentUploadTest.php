<?php

namespace Tests\Feature;

use App\Actions\Interpretations\GenerateLocaleInterpretation;
use App\Ai\Agents\RegulatoryInterpretationAgent;
use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Enums\SupportedLocale;
use App\Jobs\Ingestion\ExtractDocumentText;
use App\Jobs\Interpretations\GenerateInterpretation;
use App\Models\RegulatoryDocument;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_add_a_pdf_to_the_shared_archive(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->can('uploadShared', RegulatoryDocument::class));
        $this->assertFalse(User::factory()->tier3()->create()->can('uploadShared', RegulatoryDocument::class));

        $this->actingAs(User::factory()->tier3()->create())
            ->post(route('ops.archive.uploads.store'), ['document' => $this->pdfUpload()])
            ->assertForbidden();
    }

    public function test_admin_metadata_is_recorded_as_authoritative_while_risky_fields_are_system_controlled(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('ops.archive.uploads.store'), [
            'document' => $this->pdfUpload(),
            'title' => 'CBDT Circular 06/2026',
            'source' => 'income_tax',
            'reference_number' => 'Circular No. 06/2026',
            'document_type' => 'circular',
            'published_at' => '2026-07-17',
            'effective_at' => '2026-08-01',
            'applicability' => 'generic',
            'applicability_tags' => 'generic, ucb',
            'description' => 'Uploaded from the official publication.',
            'source_document_id' => 'admin-controlled-id',
            'source_url' => 'https://attacker.invalid/document.pdf',
            'is_public' => true,
            'uploaded_by_user_id' => $admin->getKey(),
            'status' => 'published',
        ]);

        $document = RegulatoryDocument::query()->sole();
        $response->assertRedirect(route('archive.show', $document));
        $this->assertSame(RegulatorySource::IncomeTax, $document->source);
        $this->assertSame(DocumentType::Circular, $document->document_type);
        $this->assertSame(Applicability::Generic, $document->applicability);
        $this->assertSame(['generic', 'ucb'], $document->applicability_tags);
        $this->assertSame('Circular No. 06/2026', $document->reference_number);
        $this->assertSame($admin->getKey(), $document->ingested_by_user_id);
        $this->assertNull($document->uploaded_by_user_id);
        $this->assertNull($document->source_url);
        $this->assertFalse($document->is_public);
        $this->assertNotSame('admin-controlled-id', $document->source_document_id);
        $this->assertEqualsCanonicalizing([
            'title',
            'source',
            'reference_number',
            'document_type',
            'published_at',
            'effective_at',
            'applicability',
            'applicability_tags',
            'upload_description',
        ], $document->manual_metadata_fields);
        $version = $document->versions()->sole();
        $this->assertSame('acquired', $version->status);
        Storage::disk('local')->assertExists($version->original_path);
        Queue::assertPushed(ExtractDocumentText::class);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->getKey(),
            'event' => 'archive.admin_pdf_uploaded',
            'subject_id' => $document->getKey(),
        ]);
    }

    public function test_admin_can_leave_metadata_blank_and_duplicate_content_is_rejected(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $contents = $this->pdfContents();

        $this->actingAs($admin)->post(route('ops.archive.uploads.store'), [
            'document' => UploadedFile::fake()->createWithContent('income-tax-circular.pdf', $contents),
        ])->assertSessionHasNoErrors();

        $document = RegulatoryDocument::query()->sole();
        $this->assertSame('Income Tax Circular', $document->title);
        $this->assertSame(RegulatorySource::UserUpload, $document->source);
        $this->assertSame([], $document->manual_metadata_fields);

        $this->actingAs($admin)->post(route('ops.archive.uploads.store'), [
            'document' => UploadedFile::fake()->createWithContent('duplicate.pdf', $contents),
        ])->assertSessionHasErrors('document');

        $this->assertDatabaseCount('regulatory_documents', 1);
    }

    public function test_admin_upload_stays_private_until_text_extraction_succeeds(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $member = User::factory()->tier2()->create();

        $this->actingAs($admin)->post(route('ops.archive.uploads.store'), [
            'title' => 'Pending shared circular',
            'document' => $this->pdfUpload(),
        ])->assertSessionHasNoErrors();

        $document = RegulatoryDocument::query()->sole();
        $version = $document->versions()->sole();
        $this->actingAs($member)->get(route('archive.show', $document))->assertForbidden();
        $this->actingAs($member)->get(route('archive.index'))->assertDontSee('Pending shared circular');
        $this->actingAs($admin)->get(route('archive.show', $document))->assertOk();

        (new ExtractDocumentText($version->getKey()))->handle();

        $this->assertTrue($document->refresh()->is_public);
        Queue::assertPushed(GenerateInterpretation::class);
        $this->actingAs($member)->get(route('archive.show', $document))->assertOk();
        $this->actingAs($member)->get(route('archive.index'))->assertSee('Pending shared circular');
    }

    public function test_interpretation_fills_missing_metadata_without_overwriting_admin_values(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('ops.archive.uploads.store'), [
            'title' => 'Admin verified title',
            'source' => 'income_tax',
            'applicability_tags' => 'ucb',
            'document' => $this->pdfUpload(),
        ])->assertSessionHasNoErrors();
        $document = RegulatoryDocument::query()->sole();
        $version = $document->versions()->sole();
        $version->update([
            'extracted_text' => 'Extracted official circular text.',
            'extracted_at' => now(),
        ]);
        RegulatoryInterpretationAgent::fake([[
            'locale' => 'en',
            'summary' => implode(' ', array_fill(0, 150, 'word')),
            'takeaways' => ['Review the circular.', 'Assign an owner.', 'Retain evidence.'],
            'glossary' => [],
            'deadlines' => [],
            'applicability_tags' => ['pacs'],
            'effective_date' => '2026-08-01',
            'document_type' => 'circular',
            'document_title' => 'AI supplied title',
            'regulatory_source' => 'rbi',
            'reference_number' => 'Circular No. 06/2026',
            'published_date' => '2026-07-17',
        ]])->preventStrayPrompts();

        app(GenerateLocaleInterpretation::class)->handle($version, SupportedLocale::English);

        $document->refresh();
        $this->assertSame('Admin verified title', $document->title);
        $this->assertSame(RegulatorySource::IncomeTax, $document->source);
        $this->assertSame(['ucb'], $document->applicability_tags);
        $this->assertSame(Applicability::Ucb, $document->applicability);
        $this->assertSame('Circular No. 06/2026', $document->reference_number);
        $this->assertSame('2026-07-17', $document->published_at?->toDateString());
        $this->assertSame('2026-08-01', $document->effective_at?->toDateString());
        $this->assertSame(DocumentType::Circular, $document->document_type);
    }

    private function pdfUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('circular.pdf', $this->pdfContents());
    }

    private function pdfContents(): string
    {
        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml('<p>This official circular contains complete readable regulatory text for extraction and interpretation.</p>');
        $dompdf->render();

        return $dompdf->output();
    }
}
