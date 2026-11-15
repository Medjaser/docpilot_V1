<?php

namespace App\Enums;

/**
 * Rôle d'un utilisateur dans son entreprise (§8).
 * L'équipe AD CLOUD IT n'a pas de rôle dédié : voir users.is_platform_admin.
 */
enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Manager => 'Gestionnaire',
            self::Viewer => 'Lecteur',
        };
    }

    /** Peut créer / modifier sous-traitants, demander, valider, relancer. */
    public function canManage(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    /** Peut gérer l'entreprise : utilisateurs, rôles, paramètres, abonnement. */
    public function canAdministerCompany(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Matrice des permissions par rôle (§8).
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => ['*'],
            self::Manager => [
                'subcontractors.view',
                'subcontractors.create',
                'subcontractors.edit',
                'subcontractors.archive',
                'subcontractors.delete',
                'documents.view',
                'documents.download',
                'documents.upload',
                'documents.validate',
                'documents.reject',
                'documents.edit_dates',
                'documents.archive',
                'requests.create',
                'requests.manage',
                'reminders.send',
                'exports.create',
                'exports.download',
                'deadlines.view',
                'dashboard.view',
            ],
            self::Viewer => [
                'subcontractors.view',
                'documents.view',
                'documents.download',
                'deadlines.view',
                'dashboard.view',
            ],
        };
    }
}
