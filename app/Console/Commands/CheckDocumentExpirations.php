<?php

namespace App\Console\Commands;

use App\Domain\Documents\DocumentStatusResolver;
use App\Domain\Documents\ExpirationAlertSchedule;
use App\Enums\DocumentStatus;
use App\Models\Document;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * Tâche quotidienne (§9.14) : recalcule les statuts temporels et détecte les jalons d'alerte.
 * Planifiée dans routes/console.php. Lancement manuel : php artisan docpilot:check-expirations
 *
 * TODO mois 4 (semaines 13-15) :
 *  - envoyer les notifications (via un Job en file d'attente) ;
 *  - enregistrer chaque envoi dans notification_logs avec une dedup_key ;
 *  - recalculer le score de complétude des sous-traitants concernés.
 */
class CheckDocumentExpirations extends Command
{
    protected $signature = 'docpilot:check-expirations {--date= : Date de référence AAAA-MM-JJ (tests)}';

    protected $description = 'Met à jour les statuts d\'expiration des documents et détecte les alertes du jour';

    public function handle(DocumentStatusResolver $resolver, ExpirationAlertSchedule $schedule): int
    {
        $today = new DateTimeImmutable($this->option('date') ?? 'today');
        $updated = 0;
        $alerts = 0;

        Document::acrossAllCompanies()
            ->whereIn('status', [DocumentStatus::Valid, DocumentStatus::ExpiringSoon, DocumentStatus::Expired])
            ->whereNotNull('expiration_date')
            ->chunkById(500, function ($documents) use ($resolver, $schedule, $today, &$updated, &$alerts) {
                foreach ($documents as $document) {
                    $expiration = DateTimeImmutable::createFromInterface($document->expiration_date);
                    $newStatus = $resolver->resolve($document->status, $expiration, $today);

                    if ($newStatus !== $document->status) {
                        $document->update(['status' => $newStatus]);
                        $updated++;
                    }

                    if ($schedule->alertFor($expiration, $today) !== null) {
                        $alerts++; // TODO : dispatch(new SendExpirationAlert($document, $alertKey));
                    }
                }
            });

        $this->info("Statuts mis à jour : {$updated} — alertes détectées : {$alerts}");

        return self::SUCCESS;
    }
}
