<?php

namespace App\Livewire\Admin;

use App\Domain\Company\CompanyDefaultsSeeder;
use App\Enums\CompanyStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\Subcontractor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Tableau de bord Admin - DocPilot')]
class Dashboard extends Component
{
    public array $stats = [];

    public array $months = [];

    public array $technicalErrors = [];

    public array $globalTemplates = [
        ['name' => 'Sous-traitant BTP (Standard)', 'items' => ['Extrait Kbis (< 3 mois)', 'Attestation de vigilance URSSAF (< 6 mois)', 'Assurance RC Pro', 'Assurance Décennale', 'Attestation fiscale', 'RIB']],
        ['name' => 'Prestataire Intellectuel / Services', 'items' => ['Extrait Kbis (< 3 mois)', 'Attestation de vigilance URSSAF (< 6 mois)', 'Assurance RC Pro', 'RIB']],
        ['name' => 'Transport / Logistique', 'items' => ['Extrait Kbis (< 3 mois)', 'Attestation de vigilance URSSAF (< 6 mois)', 'Assurance RC Pro', 'Licence de transport', 'RIB']],
    ];

    public ?string $toastMessage = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isPlatformAdmin(), 403);
        $this->loadStats();
    }

    public function syncGlobalDefaults(): void
    {
        abort_unless(auth()->user()?->isPlatformAdmin(), 403);

        $synced = 0;
        foreach (Company::all() as $company) {
            $count = DocumentTemplate::acrossAllCompanies()->where('company_id', $company->id)->count();
            if ($count === 0) {
                CompanyDefaultsSeeder::seedDefaultsForCompany($company->id);
                $synced++;
            }
        }

        $this->toastMessage = "Modèles globaux vérifiés et synchronisés sur l'ensemble des entreprises ({$synced} initialisée(s)).";
        $this->loadStats();
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }

    private function loadStats(): void
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::whereIn('status', [CompanyStatus::Active, CompanyStatus::Trial])->count();
        $activeSubscriptions = Company::where('status', CompanyStatus::Active)->count();
        $trialAccounts = Company::where('status', CompanyStatus::Trial)->count();
        $activeUsers = User::whereNotNull('company_id')->where('status', UserStatus::Active)->count();
        $subcontractorsTracked = Subcontractor::acrossAllCompanies()->whereNull('archived_at')->count();
        $documentsStored = Document::acrossAllCompanies()->whereNull('archived_at')->count();
        $bytes = (int) DocumentVersion::acrossAllCompanies()->sum('file_size');

        $this->stats = [
            'total_companies' => $totalCompanies,
            'active_companies' => $activeCompanies,
            'active_users' => $activeUsers,
            'subcontractors_tracked' => $subcontractorsTracked,
            'documents_stored' => $documentsStored,
            'storage_gb' => round($bytes / (1024 * 1024 * 1024), 2),
            'active_subscriptions' => $activeSubscriptions,
            'trial_accounts' => $trialAccounts,
        ];

        $now = CarbonImmutable::now();
        $monthsData = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $now->subMonths($i);
            $count = Company::whereYear('created_at', $m->year)
                ->whereMonth('created_at', $m->month)
                ->count();
            $monthsData[] = [
                'label' => ucfirst($m->locale('fr')->isoFormat('MMM')),
                'val' => $count,
            ];
        }
        $this->months = $monthsData;

        $companiesById = Company::pluck('name', 'id');
        $logs = AuditLog::acrossAllCompanies()
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $this->technicalErrors = $logs->map(function (AuditLog $log) use ($companiesById): array {
            $isWarning = str_contains($log->action, 'failed') || str_contains($log->action, 'rejected') || str_contains($log->action, 'suspended');

            return [
                'date' => $log->created_at?->format('d/m H:i') ?? '-',
                'code' => $isWarning ? 'WARN' : 'INFO',
                'message' => $log->action.($log->metadata ? ' — '.json_encode($log->metadata, JSON_UNESCAPED_UNICODE) : ''),
                'company' => $log->company_id ? ($companiesById[$log->company_id] ?? "Entreprise #{$log->company_id}") : 'Plateforme',
            ];
        })->all();
    }
}
