<?php

namespace App\Enums;

/**
 * Statut du dossier d'un sous-traitant (§9.5).
 */
enum SubcontractorStatus: string
{
    case Draft = 'draft';
    case WaitingDocuments = 'waiting_documents';
    case Incomplete = 'incomplete';
    case Complete = 'complete';
    case ExpiredDocuments = 'expired_documents';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::WaitingDocuments => 'En attente de documents',
            self::Incomplete => 'Dossier incomplet',
            self::Complete => 'Dossier complet',
            self::ExpiredDocuments => 'Documents expirés',
            self::Suspended => 'Suspendu',
            self::Archived => 'Archivé',
        };
    }
}
