<?php

namespace App\Policies;

use App\Models\IssueReport;
use App\Models\User;

class IssueReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canUseInterpretations() && ! $user->isAdmin();
    }

    public function update(User $user, IssueReport $issueReport): bool
    {
        return $user->isAdmin();
    }
}
