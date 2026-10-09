<?php

namespace App\Domain\Documents;

use DateTimeImmutable;

/**
 * Détermine si un jalon d'alerte d'expiration tombe aujourd'hui (§9.14).
 * Jalons par défaut : J-60, J-30, J-15, J-7, jour J ; et "déjà expiré".
 * La tâche planifiée quotidienne appelle cette classe pour chaque document validé.
 */
final class ExpirationAlertSchedule
{
    /** @param list<int> $alertDays */
    public function __construct(private array $alertDays = [60, 30, 15, 7, 0]) {}

    /**
     * Retourne la clé d'alerte du jour ("60d", "30d", ..., "0d", "expired") ou null.
     * La clé sert aussi à la déduplication (une seule alerte par document et par jalon).
     */
    public function alertFor(DateTimeImmutable $expirationDate, DateTimeImmutable $today): ?string
    {
        $today = $today->setTime(0, 0);
        $expiration = $expirationDate->setTime(0, 0);

        if ($expiration < $today) {
            return 'expired';
        }

        $daysLeft = (int) $today->diff($expiration)->days;

        return in_array($daysLeft, $this->alertDays, true) ? $daysLeft.'d' : null;
    }
}
