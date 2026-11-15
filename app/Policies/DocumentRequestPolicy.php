<?php

namespace App\Policies;

use App\Models\DocumentRequest;
use App\Models\User;

class DocumentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->member($user)
            && ($user->hasPermission('requests.manage') || $user->hasPermission('requests.create'));
    }

    public function view(User $user, DocumentRequest $request): bool
    {
        return $this->tenant($user, $request)
            && ($user->hasPermission('requests.manage') || $user->hasPermission('requests.create'));
    }

    public function create(User $user): bool
    {
        return $this->member($user) && $user->hasPermission('requests.create');
    }

    public function update(User $user, DocumentRequest $request): bool
    {
        return $this->tenant($user, $request) && $user->hasPermission('requests.manage');
    }

    public function send(User $user, DocumentRequest $request): bool
    {
        return $this->update($user, $request);
    }

    public function delete(User $user, DocumentRequest $request): bool
    {
        return $this->tenant($user, $request) && $user->hasPermission('requests.manage');
    }

    private function member(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->company_id !== null;
    }

    private function tenant(User $user, DocumentRequest $request): bool
    {
        return $this->member($user) && $user->belongsToCompany($request->company_id);
    }
}
