<?php

namespace App\Policies;

use App\Models\Subcontractor;
use App\Models\User;

/**
 * Seconde barrière d'isolation (après le scope global) + droits par rôle (§8).
 * Modèle à reproduire pour Document, DocumentType, DocumentRequest...
 * Découverte automatique par Laravel (convention App\Policies\{Model}Policy).
 */
class SubcontractorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function view(User $user, Subcontractor $subcontractor): bool
    {
        return $user->belongsToCompany($subcontractor->company_id);
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null && $user->role->canManage();
    }

    public function update(User $user, Subcontractor $subcontractor): bool
    {
        return $this->view($user, $subcontractor) && $user->role->canManage();
    }

    public function archive(User $user, Subcontractor $subcontractor): bool
    {
        return $this->update($user, $subcontractor);
    }

    public function delete(User $user, Subcontractor $subcontractor): bool
    {
        // Suppression définitive réservée à l'administrateur de l'entreprise (§9.21)
        return $this->view($user, $subcontractor) && $user->role->canAdministerCompany();
    }
}
