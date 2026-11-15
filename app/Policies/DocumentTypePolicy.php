<?php

namespace App\Policies;

use App\Models\DocumentType;
use App\Models\User;

class DocumentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->member($user) && $user->hasPermission('document_types.view');
    }

    public function view(User $user, DocumentType $documentType): bool
    {
        return $this->tenant($user, $documentType) && $user->hasPermission('document_types.view');
    }

    public function create(User $user): bool
    {
        return $this->member($user) && $user->hasPermission('document_types.manage');
    }

    public function update(User $user, DocumentType $documentType): bool
    {
        return $this->tenant($user, $documentType) && $user->hasPermission('document_types.manage');
    }

    public function delete(User $user, DocumentType $documentType): bool
    {
        return $this->tenant($user, $documentType) && $user->hasPermission('document_types.manage');
    }

    private function member(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->company_id !== null;
    }

    private function tenant(User $user, DocumentType $documentType): bool
    {
        return $this->member($user) && $user->belongsToCompany($documentType->company_id);
    }
}
