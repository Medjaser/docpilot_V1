<?php

use App\Enums\CompanyStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Notifications\TrialEndingNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

const CHECK_TRIALS_TODAY = '2026-11-01';

beforeEach(function () {
    Notification::fake();
});

function trialCompany(string $trialEndsAt): array
{
    $company = Company::factory()->create(['status' => CompanyStatus::Trial, 'trial_ends_at' => $trialEndsAt]);
    $admin = User::factory()->for($company)->create();
    $manager = User::factory()->for($company)->role(Role::Manager)->create();

    return [$company, $admin, $manager];
}

it('envoie l\'e-mail J-3 aux administrateurs une seule fois', function () {
    [, $admin, $manager] = trialCompany('2026-11-04 18:00:00');

    $this->artisan('companies:check-trials', ['--date' => CHECK_TRIALS_TODAY])->assertSuccessful();
    $this->artisan('companies:check-trials', ['--date' => CHECK_TRIALS_TODAY])->assertSuccessful();

    Notification::assertSentToTimes($admin, TrialEndingNotification::class, 1);
    Notification::assertSentTo($admin, TrialEndingNotification::class, fn ($n) => $n->daysLeft === 3);
    Notification::assertNotSentTo($manager, TrialEndingNotification::class);
});

it('envoie l\'e-mail J0 le dernier jour de l\'essai', function () {
    [$company, $admin] = trialCompany('2026-11-01 23:00:00');

    $this->artisan('companies:check-trials', ['--date' => CHECK_TRIALS_TODAY])->assertSuccessful();

    Notification::assertSentTo($admin, TrialEndingNotification::class, fn ($n) => $n->daysLeft === 0);
    expect($company->fresh()->status)->toBe(CompanyStatus::Trial);
});

it('n\'envoie rien en dehors des jalons J-3 et J0', function () {
    trialCompany('2026-11-06 12:00:00');
    trialCompany('2026-11-02 12:00:00');

    $this->artisan('companies:check-trials', ['--date' => CHECK_TRIALS_TODAY])->assertSuccessful();

    Notification::assertNothingSent();
});

it('suspend l\'entreprise le lendemain de la fin d\'essai', function () {
    [$company] = trialCompany('2026-10-31 12:00:00');

    $this->artisan('companies:check-trials', ['--date' => CHECK_TRIALS_TODAY])->assertSuccessful();

    expect($company->fresh()->status)->toBe(CompanyStatus::Suspended);

    $log = AuditLog::acrossAllCompanies()->where('action', 'company.status_changed')->sole();
    expect($log->user_type)->toBe('system')
        ->and($log->metadata['from'])->toBe('trial')
        ->and($log->metadata['to'])->toBe('suspended');
});

it('archive 90 jours après la résiliation', function () {
    $old = Company::factory()->create(['status' => CompanyStatus::Terminated, 'terminated_at' => '2026-08-03 15:00:00']);
    $recent = Company::factory()->create(['status' => CompanyStatus::Terminated, 'terminated_at' => '2026-08-04 09:00:00']);

    $this->artisan('companies:check-trials', ['--date' => CHECK_TRIALS_TODAY])->assertSuccessful();

    expect($old->fresh()->status)->toBe(CompanyStatus::Archived)
        ->and($old->fresh()->archived_at)->not->toBeNull()
        ->and($recent->fresh()->status)->toBe(CompanyStatus::Terminated);
});

it('ne touche pas aux entreprises actives', function () {
    $active = Company::factory()->create(['status' => CompanyStatus::Active, 'trial_ends_at' => '2026-10-01 00:00:00']);

    $this->artisan('companies:check-trials', ['--date' => CHECK_TRIALS_TODAY])->assertSuccessful();

    expect($active->fresh()->status)->toBe(CompanyStatus::Active);
});

it('est planifiée chaque jour', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('php artisan companies:check-trials')
        ->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'companies:check-trials'));

    expect($event->expression)->toBe('0 7 * * *')
        ->and($event->timezone)->toBe('Europe/Paris');
});
