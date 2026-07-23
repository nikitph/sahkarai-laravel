<?php

namespace App\Policies;

use App\Models\RegulatoryDocument;
use App\Models\User;

class RegulatoryDocumentPolicy
{
    public function view(User $user, RegulatoryDocument $document): bool
    {
        return $document->uploaded_by_user_id === null
            || $document->uploaded_by_user_id === $user->getKey();
    }

    public function upload(User $user): bool
    {
        return $user->canUploadDocuments();
    }

    public function delete(User $user, RegulatoryDocument $document): bool
    {
        return $document->uploaded_by_user_id !== null
            && $document->uploaded_by_user_id === $user->getKey();
    }
}
