<?php

namespace Tests\Feature;

use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Jobs\Ingestion\ExtractDocumentText;
use App\Models\DocumentVersion;
use App\Models\RegulatoryDocument;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
