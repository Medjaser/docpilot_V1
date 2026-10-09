<?php

namespace App\Enums;

/**
 * Rôles utilisateur (§8). PlatformAdmin = équipe AD CLOUD IT, sans entreprise.
 */
enum UserRole: string
{
    case PlatformAdmin = 'platform_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Reader = 'reader';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Administrateur plateforme',
            self::Admin => 'Administrateur',
            self::Manager => 'Gestionnaire',
            self::Reader => 'Lecteur',
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
}
