<?php

namespace Database\Seeders;

use App\Enums\Applicability;
use App\Enums\CreditReason;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use App\Enums\UserRole;
use App\Models\CreditLedger;
use App\Models\RegulatoryDocument;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(['email' => 'demo@example.com'], [
            'name' => 'Demo Member', 'password' => 'password', 'email_verified_at' => now(),
        ]);
        $user->update(['tier' => Tier::Tier2, 'credits_balance' => 200]);
        $user->subscription()->updateOrCreate([], [
            'tier' => Tier::Tier2, 'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->startOfMonth(), 'current_period_end' => now()->addMonth()->startOfMonth(),
        ]);
        $user->notificationPreference()->firstOrCreate();
        CreditLedger::query()->firstOrCreate(['idempotency_key' => 'seed-initial-credits'], [
            'user_id' => $user->getKey(), 'amount' => 200, 'balance_after' => 200, 'reason' => CreditReason::GrantCycle,
        ]);

        $admin = User::query()->firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'SahkarAI Operations', 'password' => 'password', 'email_verified_at' => now(),
        ]);
        $admin->update(['role' => UserRole::SaasAdmin]);
        $admin->subscription()->firstOrCreate([], ['tier' => Tier::Free, 'status' => SubscriptionStatus::Free]);
        $admin->notificationPreference()->firstOrCreate();

        $documents = [
            [RegulatorySource::Rbi, 'RBI/2026-27/41', 'Updated prudential norms for Urban Co-operative Banks', DocumentType::Circular, Applicability::Ucb],
            [RegulatorySource::Rbi, 'RBI/MD/2026/07', 'Master Direction on governance in co-operative banks', DocumentType::MasterDirection, Applicability::Generic],
            [RegulatorySource::IncomeTax, 'CBDT-NOT-2026-18', 'Electronic filing changes for specified financial entities', DocumentType::Notification, Applicability::Generic],
            [RegulatorySource::Gst, 'GST-CIRC-244-2026', 'Clarification on input tax credit documentation', DocumentType::Circular, Applicability::Generic],
            [RegulatorySource::Rbi, 'RBI/2026-27/28', 'Cyber resilience controls for regulated co-operative entities', DocumentType::Notification, Applicability::Dccb],
            [RegulatorySource::Gst, 'GST-FAQ-2026-Q2', 'Frequently asked questions on annual return reconciliation', DocumentType::Faq, Applicability::Generic],
        ];

        foreach ($documents as $index => [$source, $sourceId, $title, $type, $applicability]) {
            $document = RegulatoryDocument::query()->firstOrCreate(['source' => $source, 'source_document_id' => $sourceId], [
                'title' => $title, 'document_type' => $type, 'applicability' => $applicability,
                'published_at' => now()->subDays($index * 3 + 1), 'effective_at' => now()->addDays(30 - $index),
                'source_url' => 'https://example.test/regulatory/'.str($sourceId)->slug(),
            ]);
            $document->update(['applicability_tags' => [$applicability->value]]);
            $text = "{$title}\n\nRegulated entities must review the updated requirements, assign accountable owners, preserve supporting records and complete implementation within the stated transition period. Boards should receive progress reporting and material exceptions should be escalated. This seeded publication exists to exercise the complete SahkarAI product flow.";
            $storageId = preg_replace('/[^A-Za-z0-9._-]/', '_', $sourceId);
            $path = "originals/{$source->storageDirectory()}/".now()->format('Y/m')."/{$storageId}.txt";
            $extractedPath = "extracted/{$source->storageDirectory()}/".now()->format('Y/m')."/{$storageId}.txt";
            Storage::disk(config('sahkarai.ingestion.storage_disk'))->put($path, $text);
            Storage::disk(config('sahkarai.ingestion.storage_disk'))->put($extractedPath, $text);
            $version = $document->versions()->firstOrCreate(['version' => 1], [
                'status' => 'published', 'original_path' => $path, 'original_filename' => str($title)->slug().'.txt',
                'mime_type' => 'text/plain', 'size_bytes' => strlen($text), 'sha256' => hash('sha256', $text),
                'extracted_text' => $text, 'extracted_path' => $extractedPath, 'acquired_at' => now(), 'extracted_at' => now(),
            ]);
            $summary = 'This update asks covered co-operative financial institutions to review the revised regulatory expectations, identify the teams and systems affected, and establish a documented implementation plan. Management should allocate clear ownership, retain evidence for each completed control, and escalate gaps that cannot be resolved within the transition period. The board or an appropriate committee should receive concise progress reporting so that delays and material exceptions remain visible. Institutions should read the original publication before acting because applicability can depend on entity type, existing approvals, and the precise facts of a transaction. The interpretation highlights the operational direction of the update while preserving the original document as the authoritative source. Teams should compare current policy, process, technology, reporting, and record-retention arrangements with the publication; record decisions and assumptions; and seek qualified advice where the wording or their circumstances create uncertainty. No requirement should be inferred beyond the source text. Always verify actions against the publication.';
            $version->interpretation()->firstOrCreate([], [
                'status' => 'published', 'locale_payloads' => collect(['en', 'hi', 'gu', 'mr'])->mapWithKeys(fn ($locale) => [$locale => [
                    'locale' => $locale, 'summary' => $summary,
                    'takeaways' => ['Identify the exact entities and processes in scope.', 'Assign an accountable owner and retain implementation evidence.', 'Report material gaps and progress to the board or appropriate committee.'],
                    'glossary' => [['term' => 'Regulated entity', 'definition' => 'An institution to which the issuing authority applies the publication.']],
                ]])->all(),
                'applicability_tags' => [$applicability->value],
                'effective_date' => now()->addDays(30 - $index)->toDateString(),
                'document_type' => $type->value,
                'failed_locales' => [], 'locale_attempts' => ['en' => 1, 'hi' => 1, 'gu' => 1, 'mr' => 1],
                'deadlines' => [['due_date' => now()->addDays(30 - $index)->toDateString(), 'description' => 'Target date shown in this seeded demonstration record.']],
                'model_id' => 'seeded-reference', 'prompt_version' => 'seed.1', 'attempts' => 1,
                'generated_at' => now(), 'published_at' => now(),
            ]);
        }
    }
}
