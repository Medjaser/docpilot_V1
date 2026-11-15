<?php

namespace App\Domain\Documents;

use App\Enums\DocumentStatus;
use App\Enums\SubcontractorStatus;

/**
 * Score de complétude d'un sous-traitant (§9.18).
 *
 * score = documents obligatoires valides / documents obligatoires attendus.
 * "Bientôt expiré" compte comme valide ; "Expiré" ne compte PAS.
 * Indicateur interne : ce n'est pas une certification juridique.
 */
final class CompletenessScore
{
    /** @param list<DocumentStatus> $requiredStatuses statuts des documents OBLIGATOIRES */
    public function calculate(array $requiredStatuses): int
    {
        $expected = count($requiredStatuses);

        if ($expected === 0) {
            return 100;
        }

        $valid = count(array_filter(
            $requiredStatuses,
            fn (DocumentStatus $s) => in_array($s, [DocumentStatus::Valid, DocumentStatus::ExpiringSoon], true),
        ));

        return (int) floor($valid * 100 / $expected);
    }

    /** Niveau affiché : complet / presque complet / incomplet / critique. */
    public function level(int $score): string
    {
        return match (true) {
            $score >= 100 => 'complete',
            $score >= 75 => 'almost_complete',
            $score >= 50 => 'incomplete',
            default => 'critical',
        };
    }

    /** Statut de dossier déduit des documents obligatoires. */
    public function subcontractorStatus(array $requiredStatuses): SubcontractorStatus
    {
        if (in_array(DocumentStatus::Expired, $requiredStatuses, true)) {
            return SubcontractorStatus::ExpiredDocuments;
        }

        return $this->calculate($requiredStatuses) === 100
            ? SubcontractorStatus::Complete
            : SubcontractorStatus::Incomplete;
    }
}
