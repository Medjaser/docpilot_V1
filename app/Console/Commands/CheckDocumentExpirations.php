<?php

namespace App\Console\Commands;

use App\Domain\Documents\DocumentStatusResolver;
use App\Domain\Documents\ExpirationAlertSchedule;
use App\Domain\Documents\SubcontractorCompletenessUpdater;
use App\Domain\Notifications\CompanyNotificationDispatcher;
use App\Enums\DocumentStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\Subcontractor;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * Tâche quotidienne (§9.14) : recalcule les statuts temporels, envoie les alertes J-60/30/15/7/0
 * avec anti-doublon (dedup_key) et recalcule le score de complétude des sous-traitants concernés.
 * Planifiée dans routes/console.php. Lancement manuel : php artisan docpilot:check-expirations
 */
class CheckDocumentExpirations extends Command
{
    protected $signature = 'docpilot:check-expirations {--date= : Date de référence AAAA-MM-JJ (tests)}';

    protected $description = 'Met à jour les statuts d\'expiration des documents et détecte les alertes du jour';

    public function handle(
        DocumentStatusResolver $resolver,
        ExpirationAlertSchedule $schedule,
        CompanyNotificationDispatcher $dispatcher,
        SubcontractorCompletenessUpdater $completenessUpdater,
    ): int {
        $today = new DateTimeImmutable($this->option('date') ?? 'today');
        $updated = 0;
        $alerts = 0;
        $affectedSubcontractors = [];

        Document::acrossAllCompanies()
            ->with(['subcontractor', 'documentType'])
            ->whereNull('archived_at')
            ->whereIn('status', [DocumentStatus::Valid, DocumentStatus::ExpiringSoon, DocumentStatus::Expired])
            ->whereNotNull('expiration_date')
            ->chunkById(500, function ($documents) use (
                $resolver,
                $schedule,
                $dispatcher,
                $today,
                &$updated,
                &$alerts,
                &$affectedSubcontractors
            ) {
                foreach ($documents as $document) {
                    $expiration = DateTimeImmutable::createFromInterface($document->expiration_date);
                    $newStatus = $resolver->resolve($document->status, $expiration, $today);

                    if ($newStatus !== $document->status) {
                        $document->update(['status' => $newStatus]);
                        $updated++;
                        $affectedSubcontractors[$document->subcontractor_id] = $document->company_id;
                    }

                    $alertKey = $schedule->alertFor($expiration, $today);
                    if ($alertKey !== null) {
                        $alerts++;
                        $company = Company::find($document->company_id);
                        $subcontractor = $document->subcontractor
                            ?? Subcontractor::acrossAllCompanies()->find($document->subcontractor_id);
                        $typeName = $document->documentType?->name ?? 'Document';

                        if ($company && $subcontractor) {
                            $dedupBase = "expiration:doc:{$document->id}:{$alertKey}:".$today->format('Y-m-d');
                            $isExpired = $alertKey === 'expired';
                            $prefKey = $isExpired ? 'expired' : 'expiring';

                            $title = $isExpired
                                ? "Document expiré — {$subcontractor->name}"
                                : "Échéance J-".rtrim($alertKey, 'd')." — {$subcontractor->name}";
                            $body = $isExpired
                                ? "Le document « {$typeName} » de {$subcontractor->name} a expiré le {$document->expiration_date->format('d/m/Y')}."
                                : "Le document « {$typeName} » de {$subcontractor->name} expire le {$document->expiration_date->format('d/m/Y')} ({$alertKey}).";

                            $dispatcher->notifyCompanyUsers(
                                $company,
                                "document.{$prefKey}",
                                $title,
                                $body,
                                [
                                    'document_id' => $document->id,
                                    'subcontractor_id' => $subcontractor->id,
                                    'alert_key' => $alertKey,
                                ],
                                $prefKey,
                                $dedupBase
                            );

                            $settings = $company->settings();
                            if ($settings->autoRemindersEnabled()) {
                                $dispatcher->sendEmailToSubcontractor(
                                    $company,
                                    $subcontractor,
                                    $isExpired ? 'reminder.expired' : 'reminder.expiring',
                                    [
                                        'nom_document' => $typeName,
                                        'date_expiration' => $document->expiration_date->format('d/m/Y'),
                                    ],
                                    "{$dedupBase}:subcontractor"
                                );
                            }
                        }
                    }
                }
            });

        foreach (array_keys($affectedSubcontractors) as $subId) {
            $sub = Subcontractor::acrossAllCompanies()->find($subId);
            if ($sub) {
                $completenessUpdater->refresh($sub);
            }
        }

        $this->info("Statuts mis à jour : {$updated} — alertes détectées : {$alerts}");

        return self::SUCCESS;
    }
}
