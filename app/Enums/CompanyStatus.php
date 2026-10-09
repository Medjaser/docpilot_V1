<?php

namespace App\Enums;

/**
 * Statut d'une entreprise cliente (§9.2).
 */
enum CompanyStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Essai',
            self::Active => 'Active',
            self::Suspended => 'Suspendue',
            self::Terminated => 'Résiliée',
            self::Archived => 'Archivée',
        };
    }
}
