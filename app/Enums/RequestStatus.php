<?php

namespace App\Enums;

/**
 * Statut d'une demande de documents / lien de dépôt (§9.8).
 */
enum RequestStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Completed = 'completed';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoyée',
            self::Completed => 'Terminée',
            self::Expired => 'Expirée',
            self::Revoked => 'Révoquée',
        };
    }
}
