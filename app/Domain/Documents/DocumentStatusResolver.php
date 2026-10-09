<?php

namespace App\Domain\Documents;

use App\Enums\DocumentStatus;
use DateTimeImmutable;

/**
 * Calcule le statut "temporel" d'un document validé (§9.13).
 *
 *  - expiration > N jours        → Valide
 *  - expiration dans ≤ N jours   → Bientôt expiré
 *  - date dépassée               → Expiré
 *
 * Les statuts de workflow (manquant, déposé, rejeté...) ne sont pas modifiés.
 * Classe sans dépendance à la base : testable unitairement.
 */
final class DocumentStatusResolver
{
    public function __construct(private int $expiringSoonDays = 30) {}

    public function resolve(
        DocumentStatus $current,
        ?DateTimeImmutable $expirationDate,
        DateTimeImmutable $today,
    ): DocumentStatus {
        $temporal = [DocumentStatus::Valid, DocumentStatus::ExpiringSoon, DocumentStatus::Expired];

        if (! in_array($current, $temporal, true) || $expirationDate === null) {
            return $current;
        }

        $today = $today->setTime(0, 0);
        $expiration = $expirationDate->setTime(0, 0);

        if ($expiration < $today) {
            return DocumentStatus::Expired;
        }

        $daysLeft = (int) $today->diff($expiration)->days;

        return $daysLeft <= $this->expiringSoonDays
            ? DocumentStatus::ExpiringSoon
            : DocumentStatus::Valid;
    }
}
