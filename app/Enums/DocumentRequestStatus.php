<?php

namespace App\Enums;

/**
 * Statut d'une demande de documents / lien de dépôt (§9.8).
 */
enum DocumentRequestStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoyée',
            self::Completed => 'Terminée',
            self::Expired => 'Expirée',
            self::Cancelled => 'Annulée',
        };
    }
}
