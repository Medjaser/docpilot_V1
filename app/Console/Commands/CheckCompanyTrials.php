<?php

namespace App\Console\Commands;

use App\Domain\Company\CompanyStatusManager;
use App\Enums\CompanyStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\TrialEndingNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Tâche quotidienne du cycle de vie des entreprises :
 *  - e-mail aux administrateurs à J-3 et J0 de la fin d'essai (une seule fois, via notification_logs) ;
 *  - suspension le lendemain de la fin d'essai ;
 *  - archivage 90 jours après la résiliation.
 * Planifiée dans routes/console.php. Lancement manuel : php artisan companies:check-trials
 */
class CheckCompanyTrials extends Command
{
    public const TRIAL_REMINDER_DAYS = [3, 0];

    public const ARCHIVE_AFTER_DAYS = 90;

    protected $signature = 'companies:check-trials {--date= : Date de référence AAAA-MM-JJ (tests)}';

    protected $description = 'Relance les fins d\'essai, suspend les essais expirés et archive les résiliations anciennes';

    public function handle(CompanyStatusManager $statuses): int
    {
        $today = CarbonImmutable::parse($this->option('date') ?? 'today')->startOfDay();
        $reminders = $suspended = $archived = 0;

        Company::query()
            ->where('status', CompanyStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->chunkById(200, function ($companies) use ($statuses, $today, &$reminders, &$suspended) {
                foreach ($companies as $company) {
                    $daysLeft = (int) $today->diffInDays($company->trial_ends_at->toImmutable()->startOfDay(), false);

                    if ($daysLeft < 0) {
                        $statuses->transition($company, CompanyStatus::Suspended, 'Fin de la période d\'essai', 'system');
                        $suspended++;
                    } elseif (in_array($daysLeft, self::TRIAL_REMINDER_DAYS, true) && $this->remindTrialEnding($company, $daysLeft)) {
                        $reminders++;
                    }
                }
            });

        Company::query()
            ->where('status', CompanyStatus::Terminated)
            ->whereNotNull('terminated_at')
            ->where('terminated_at', '<', $today->subDays(self::ARCHIVE_AFTER_DAYS - 1))
            ->chunkById(200, function ($companies) use ($statuses, &$archived) {
                foreach ($companies as $company) {
                    $statuses->transition($company, CompanyStatus::Archived, 'Archivage automatique '.self::ARCHIVE_AFTER_DAYS.' jours après la résiliation', 'system');
                    $archived++;
                }
            });

        $this->info("Relances d'essai : {$reminders} — suspensions : {$suspended} — archivages : {$archived}");

        return self::SUCCESS;
    }

    private function remindTrialEnding(Company $company, int $daysLeft): bool
    {
        $dedupKey = "trial_ending:company:{$company->id}:J-{$daysLeft}";

        if (NotificationLog::acrossAllCompanies()->where('dedup_key', $dedupKey)->exists()) {
            return false;
        }

        $admins = User::query()
            ->where('company_id', $company->id)
            ->where('role', Role::Admin)
            ->where('status', UserStatus::Active)
            ->get();

        if ($admins->isEmpty()) {
            return false;
        }

        Notification::send($admins, new TrialEndingNotification($company, $daysLeft));

        NotificationLog::create([
            'company_id' => $company->id,
            'channel' => 'mail',
            'type' => 'trial_ending',
            'recipient' => Str::limit($admins->pluck('email')->implode(','), 250, ''),
            'dedup_key' => $dedupKey,
            'sent_at' => now(),
        ]);

        return true;
    }
}
