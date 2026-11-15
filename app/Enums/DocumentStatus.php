<?php

namespace App\Enums;

/**
 * Statut d'un document (§9.13). Valid / ExpiringSoon / Expired sont recalculés automatiquement.
 */
enum DocumentStatus: string
{
    case Missing = 'missing';
    case Requested = 'requested';
    case Uploaded = 'uploaded';
    case Pending = 'pending';
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case Rejected = 'rejected';
    case Replaced = 'replaced';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Missing => 'Manquant',
            self::Requested => 'Demandé',
            self::Uploaded => 'Déposé',
            self::Pending => 'En attente de validation',
            self::Valid => 'Valide',
            self::ExpiringSoon => 'Bientôt expiré',
            self::Expired => 'Expiré',
            self::Rejected => 'Rejeté',
            self::Replaced => 'Remplacé',
            self::Archived => 'Archivé',
        };
    }
}
