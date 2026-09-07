<?php

namespace App\Actions\Organizations;

use App\Models\Invitation;
use Illuminate\Support\Facades\DB;

class CancelOrganizationInvitation
{
    public function handle(Invitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $invitation->seat()->delete();
            $invitation->delete();
        });
    }
}
