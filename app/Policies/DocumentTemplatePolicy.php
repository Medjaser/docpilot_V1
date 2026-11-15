<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;

class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->member($user) && $user->hasPermission('templates.view');
    }

    public function view(User $user, DocumentTemplate $template): bool
    {
        return $this->tenant($user, $template) && $user->hasPermission('templates.view');
    }

    public function create(User $user): bool
    {
        return $this->member($user) && $user->hasPermission('templates.manage');
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        return $this->tenant($user, $template) && $user->hasPermission('templates.manage');
    }

    public function delete(User $user, DocumentTemplate $template): bool
    {
        return $this->tenant($user, $template) && $user->hasPermission('templates.manage');
    }

    private function member(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->company_id !== null;
    }

    private function tenant(User $user, DocumentTemplate $template): bool
    {
        return $this->member($user) && $user->belongsToCompany($template->company_id);
    }
}
