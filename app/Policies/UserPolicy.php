<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->company_id !== null
            && $user->hasPermission('users.view');
    }

    public function view(User $user, User $targetUser): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($targetUser->company_id)
            && $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->company_id !== null
            && $user->hasPermission('users.invite');
    }

    public function update(User $user, User $targetUser): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($targetUser->company_id)
            && $user->hasPermission('users.edit');
    }

    public function delete(User $user, User $targetUser): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($targetUser->company_id)
            && $user->hasPermission('users.delete');
    }

    /**
     * Vérifie si l'utilisateur peut être rétrogradé de son rôle d'administrateur.
     * Le dernier administrateur d'une entreprise ne peut pas être rétrogradé.
     */
    public function demote(User $user, User $targetUser): bool
    {
        if ($user->isPlatformAdmin() || ! $user->belongsToCompany($targetUser->company_id) || ! $user->hasPermission('users.edit')) {
            return false;
        }

        if ($targetUser->role === Role::Admin) {
            $adminCount = User::where('company_id', $targetUser->company_id)
                ->where('role', Role::Admin)
                ->count();

            if ($adminCount <= 1) {
                return false;
            }
        }

        return true;
    }
}
