<?php

namespace App\Policies;

use App\Models\Subcontractor;
use App\Models\User;

class SubcontractorPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->company_id !== null
            && $user->hasPermission('subcontractors.view');
    }

    public function view(User $user, Subcontractor $subcontractor): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($subcontractor->company_id)
            && $user->hasPermission('subcontractors.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->company_id !== null
            && $user->hasPermission('subcontractors.create');
    }

    public function update(User $user, Subcontractor $subcontractor): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($subcontractor->company_id)
            && $user->hasPermission('subcontractors.edit');
    }

    public function archive(User $user, Subcontractor $subcontractor): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($subcontractor->company_id)
            && $user->hasPermission('subcontractors.archive');
    }

    public function remind(User $user, Subcontractor $subcontractor): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($subcontractor->company_id)
            && $user->hasPermission('reminders.send');
    }

    public function delete(User $user, Subcontractor $subcontractor): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($subcontractor->company_id)
            && $user->hasPermission('subcontractors.delete');
    }
}
