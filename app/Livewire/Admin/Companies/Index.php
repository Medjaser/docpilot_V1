<?php

namespace App\Livewire\Admin\Companies;

use App\Domain\Company\CompanyStatusManager;
use App\Domain\Company\InvalidCompanyTransition;
use App\Enums\CompanyStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Support\Tenancy\CompanyScope;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Entreprises clientes - DocPilot Admin')]
class Index extends Component
{
    public bool $showSupportModal = false;

    public ?int $supportCompanyId = null;

    public string $support_reason = '';

    public int $support_duration_hours = 2;

    public string $selected_company_name = '';

    public bool $showLimitsModal = false;

    public ?int $limitsCompanyId = null;

    public string $limitsCompanyName = '';

    public string $limits_plan = 'essentiel';

    public int $limits_max_users = 5;

    public int $limits_max_subcontractors = 25;

    public bool $showStatusModal = false;

    #[Locked]
    public ?int $statusCompanyId = null;

    #[Locked]
    public string $statusTarget = '';

    public string $status_reason = '';

    public ?string $toastMessage = null;

    public function mount(): void
    {
        $this->ensurePlatformAdmin();
    }

    /** Actions proposées pour un statut ; l'archivage reste automatique (companies:check-trials). */
    public static function statusActions(CompanyStatus $status): array
    {
        $actions = [];

        foreach ($status->allowedTransitions() as $target) {
            $actions[$target->value] = match ($target) {
                CompanyStatus::Active => $status === CompanyStatus::Trial ? 'Activer' : 'Réactiver',
                CompanyStatus::Suspended => 'Suspendre',
                CompanyStatus::Terminated => 'Résilier',
                default => null,
            };
        }

        return array_filter($actions);
    }

    public function openStatusModal(int $companyId, string $target): void
    {
        $this->ensurePlatformAdmin();

        $company = Company::findOrFail($companyId);
        $to = CompanyStatus::tryFrom($target);

        if ($to === null || ! $company->status->canTransitionTo($to)) {
            $this->addError('status', 'Ce changement de statut n\'est pas autorisé.');

            return;
        }

        $this->resetValidation();
        $this->statusCompanyId = $company->id;
        $this->statusTarget = $to->value;
        $this->status_reason = '';
        $this->showStatusModal = true;
    }

    public function confirmStatusChange(CompanyStatusManager $statuses): void
    {
        $this->ensurePlatformAdmin();

        $this->validate([
            'status_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'status_reason.required' => 'Le motif est obligatoire.',
            'status_reason.min' => 'Le motif doit contenir au moins 5 caractères.',
        ]);

        $company = Company::findOrFail($this->statusCompanyId);

        try {
            $statuses->transition($company, CompanyStatus::from($this->statusTarget), $this->status_reason);
        } catch (InvalidCompanyTransition $e) {
            $this->addError('status_reason', $e->getMessage());

            return;
        }

        $this->toastMessage = "{$company->name} : statut « {$company->status->label()} ».";
        $this->closeStatusModal();
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
        $this->reset(['statusCompanyId', 'statusTarget', 'status_reason']);
    }

    public function openLimitsModal(int $companyId): void
    {
        $this->ensurePlatformAdmin();

        $company = Company::findOrFail($companyId);
        $planKey = $company->subscription_plan ?: 'essentiel';
        $plan = config("docpilot.plans.{$planKey}", config('docpilot.plans.essentiel'));
        $customLimits = data_get($company->settings, 'limits', []);

        $this->limitsCompanyId = $company->id;
        $this->limitsCompanyName = $company->name;
        $this->limits_plan = $planKey;
        $this->limits_max_users = (int) ($customLimits['max_users'] ?? $plan['max_users'] ?? 5);
        $this->limits_max_subcontractors = (int) ($customLimits['max_subcontractors'] ?? $plan['max_subcontractors'] ?? 25);
        $this->showLimitsModal = true;
    }

    public function saveLimits(): void
    {
        $this->ensurePlatformAdmin();

        $validated = $this->validate([
            'limits_plan' => ['required', 'string', 'in:mini,essentiel,business'],
            'limits_max_users' => ['required', 'integer', 'min:1', 'max:500'],
            'limits_max_subcontractors' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        $company = Company::findOrFail($this->limitsCompanyId);
        $settings = is_array($company->settings) ? $company->settings : [];
        $settings['limits'] = [
            'max_users' => (int) $validated['limits_max_users'],
            'max_subcontractors' => (int) $validated['limits_max_subcontractors'],
        ];

        $company->update([
            'subscription_plan' => $validated['limits_plan'],
            'settings' => $settings,
        ]);

        AuditLog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'user_type' => 'user',
            'user_id' => auth()->id(),
            'action' => 'platform.company_limits_updated',
            'entity_type' => 'company',
            'entity_id' => $company->id,
            'metadata' => [
                'subscription_plan' => $validated['limits_plan'],
                'max_users' => (int) $validated['limits_max_users'],
                'max_subcontractors' => (int) $validated['limits_max_subcontractors'],
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);

        $this->showLimitsModal = false;
        $this->toastMessage = "Limites et offre mises à jour pour {$company->name}.";
    }

    public function openSupportModal(string $companyName, ?int $companyId = null): void
    {
        $this->ensurePlatformAdmin();
        $this->selected_company_name = $companyName;
        $this->supportCompanyId = $companyId;
        $this->showSupportModal = true;
    }

    public function grantSupportAccess(): void
    {
        $this->ensurePlatformAdmin();
        $this->validate([
            'support_reason' => 'required|string|min:5',
        ], [
            'support_reason.required' => 'Un motif précis est OBLIGATOIRE pour l\'accès support.',
        ]);

        $company = $this->supportCompanyId
            ? Company::find($this->supportCompanyId)
            : Company::where('name', $this->selected_company_name)->first();

        if ($company) {
            $expiresAt = now()->addHours((int) $this->support_duration_hours);
            $settings = is_array($company->settings) ? $company->settings : [];
            $settings['support_access'] = [
                'granted_by' => auth()->user()?->email,
                'reason' => $this->support_reason,
                'expires_at' => $expiresAt->toIso8601String(),
            ];
            $company->update(['settings' => $settings]);

            AuditLog::withoutGlobalScope(CompanyScope::class)->create([
                'company_id' => $company->id,
                'user_type' => 'user',
                'user_id' => auth()->id(),
                'action' => 'platform.support_access_granted',
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'metadata' => [
                    'reason' => $this->support_reason,
                    'duration_hours' => $this->support_duration_hours,
                    'expires_at' => $expiresAt->toIso8601String(),
                    'support_agent' => auth()->user()?->email,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        }

        $this->toastMessage = "Accès support temporaire ({$this->support_duration_hours}h) activé et tracé dans le journal d'audit de {$this->selected_company_name}.";
        $this->showSupportModal = false;
        $this->reset(['support_reason', 'supportCompanyId']);
    }

    public function render()
    {
        $companies = Company::query()
            ->withCount([
                'users',
                'subcontractors' => fn ($query) => $query->withoutGlobalScope(CompanyScope::class),
            ])
            ->orderBy('name')
            ->get();

        return view('livewire.admin.companies.index', [
            'companies' => $companies,
            'plans' => config('docpilot.plans'),
            'statusCompany' => $this->statusCompanyId ? $companies->firstWhere('id', $this->statusCompanyId) : null,
            'statusTargetEnum' => CompanyStatus::tryFrom($this->statusTarget),
        ]);
    }

    /** Les requêtes Livewire ne repassent pas par le middleware platform.admin. */
    private function ensurePlatformAdmin(): void
    {
        abort_unless(auth()->user()?->isPlatformAdmin(), 403);
    }
}
