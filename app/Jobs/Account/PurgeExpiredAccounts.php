<?php

namespace App\Jobs\Account;

use App\Actions\Archive\DeleteUploadedDocument;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class PurgeExpiredAccounts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $deleteUploadedDocument = app(DeleteUploadedDocument::class);

        User::onlyTrashed()->where('hard_delete_at', '<=', now())->each(function (User $user) use ($deleteUploadedDocument): void {
            $user->uploadedDocuments()->each(
                fn ($document) => $deleteUploadedDocument->handle($document),
            );
            DB::transaction(function () use ($user): void {
                DB::table('issue_reports')->where('user_id', $user->getKey())->update(['user_id' => null]);
                DB::table('audit_events')->where('actor_id', $user->getKey())->update([
                    'actor_id' => null,
                    'metadata' => json_encode(['actor_anonymized' => true]),
                ]);
                $user->forceDelete();
            });
        });
    }
}
