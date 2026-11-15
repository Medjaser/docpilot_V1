<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->company_id !== null
            && $user->hasPermission('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($document->company_id)
            && $user->hasPermission('documents.view');
    }

    public function download(User $user, Document $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($document->company_id)
            && $user->hasPermission('documents.download');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->company_id !== null
            && $user->hasPermission('documents.upload');
    }

    public function validate(User $user, Document $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($document->company_id)
            && $user->hasPermission('documents.validate');
    }

    public function reject(User $user, Document $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($document->company_id)
            && $user->hasPermission('documents.reject');
    }

    public function updateDates(User $user, Document $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($document->company_id)
            && $user->hasPermission('documents.edit_dates');
    }

    public function requestNewVersion(User $user, Document $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($document->company_id)
            && $user->hasPermission('documents.upload');
    }

    public function remind(User $user, Document $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($document->company_id)
            && $user->hasPermission('reminders.send');
    }

    public function archive(User $user, Document $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($document->company_id)
            && $user->hasPermission('documents.archive');
    }

    public function delete(User $user, Document $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($document->company_id)
            && $user->hasPermission('documents.archive');
    }
}
