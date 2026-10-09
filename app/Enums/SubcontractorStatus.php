<?php

namespace App\Enums;

/**
 * Statut du dossier d'un sous-traitant (§9.5).
 */
enum SubcontractorStatus: string
{
    case Draft = 'draft';
    case AwaitingDocuments = 'awaiting_documents';
    case Incomplete = 'incomplete';
    case Complete = 'complete';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::AwaitingDocuments => 'En attente de documents',
            self::Incomplete => 'Dossier incomplet',
            self::Complete => 'Dossier complet',
            self::Expired => 'Documents expirés',
            self::Suspended => 'Suspendu',
            self::Archived => 'Archivé',
        };
    }
}
