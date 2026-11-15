<?php

namespace App\Enums;

/**
 * Statut d'un compte utilisateur (§9.4).
 * Les invitations en attente sont dans user_invitations, pas dans users.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Deactivated => 'Désactivé',
        };
    }
}
