<?php

namespace App\Enums;

/**
 * Motifs de rejet d'un document (§9.12). Le motif est obligatoire.
 */
enum RejectionReason: string
{
    case Illegible = 'illegible';
    case Incomplete = 'incomplete';
    case Expired = 'expired';
    case Incorrect = 'incorrect';
    case WrongCompany = 'wrong_company';
    case MissingDate = 'missing_date';
    case NotSigned = 'not_signed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Illegible => 'Document illisible',
            self::Incomplete => 'Document incomplet',
            self::Expired => 'Document expiré',
            self::Incorrect => 'Document incorrect',
            self::WrongCompany => 'Mauvaise entreprise',
            self::MissingDate => 'Date absente',
            self::NotSigned => 'Document non signé',
            self::Other => 'Autre',
        };
    }
}
