<?php

namespace App\Policies;

use App\Models\RegulatoryDocument;
use App\Models\User;

class RegulatoryDocumentPolicy
{
    public function view(User $user, RegulatoryDocument $document): bool
    {
        if ($document->uploaded_by_user_id !== null) {
            return $document->uploaded_by_user_id === $user->getKey();
        }

        return $user->isAdmin()
            || ($document->is_public && $document->publishedVersions()->exists());
    }

    public function upload(User $user): bool
    {
        return $user->canUploadDocuments();
    }

    public function uploadShared(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, RegulatoryDocument $document): bool
    {
        return $document->uploaded_by_user_id !== null
            && $document->uploaded_by_user_id === $user->getKey();
    }

    public function generateExplainerVideo(User $user, RegulatoryDocument $document): bool
    {
        return config('sahkarai.video.enabled')
            && $user->canUseExplainerVideos()
            && $document->uploaded_by_user_id === $user->getKey();
    }
}
