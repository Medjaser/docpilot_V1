<?php

namespace App\Enums;

/**
 * Statut d'un compte utilisateur (§9.4).
 */
enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Disabled = 'disabled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invité',
            self::Active => 'Actif',
            self::Disabled => 'Désactivé',
            self::Archived => 'Archivé',
        };
    }
}
