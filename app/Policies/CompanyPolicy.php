<?php

namespace App\Policies;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /** Tous les membres consultent les paramètres (lecture seule hors administrateurs). */
    public function view(User $user, Company $company): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($company->id);
    }

    /** Action applicative non administrative (ex. marquer ses notifications comme lues). */
    public function interact(User $user, Company $company): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($company->id)
            && $company->status->isWritable();
    }

    /**
     * Sections réservées aux administrateurs (abonnement, modèles d'e-mails), même en consultation.
     * Pas de hasPermission() : un administrateur d'entreprise suspendue garde la lecture.
     */
    public function administer(User $user, Company $company): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($company->id)
            && $user->role?->canAdministerCompany() === true;
    }

    public function update(User $user, Company $company): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($company->id)
            && $user->hasPermission('settings.manage');
    }

    public function manage(User $user, Company $company): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($company->id)
            && $user->hasPermission('settings.manage');
    }

    /** Possible aussi en suspension : ne passe donc pas par hasPermission(). */
    public function cancelSubscription(User $user, Company $company): bool
    {
        return ! $user->isPlatformAdmin()
            && $user->belongsToCompany($company->id)
            && $user->role?->canAdministerCompany() === true
            && $company->status->canTransitionTo(CompanyStatus::Terminated);
    }

    public function changeStatus(User $user, Company $company): bool
    {
        return $user->isPlatformAdmin();
    }
}
