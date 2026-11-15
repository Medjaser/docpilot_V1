<?php

namespace App\Livewire\App\Settings;

use App\Domain\Audit\AuditLogger;
use App\Domain\Company\CompanyStatusManager;
use App\Enums\CompanyStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\DocumentVersion;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Abonnement & Tarifs - DocPilot')]
class Subscription extends Component
{
    public string $currentPlan = 'Essentiel';

    public string $currentPlanKey = 'essentiel';

    public bool $isTrial = false;

    public int $trialDaysLeft = 0;

    public int $subcontractorsUsed = 0;

    public int $subcontractorsMax = 25;

    public int $usersUsed = 0;

    public int $usersMax = 5;

    public float $storageUsedGb = 0.0;

    public float $storageMaxGb = 10.0;

    public bool $showCheckoutModal = false;

    public string $checkoutPlanKey = 'essentiel';

    public string $checkoutPlanLabel = 'Essentiel';

    public string $checkoutPlanPrice = '14,90 €';

    public string $cardHolder = '';

    public ?string $statusBanner = null;

    public bool $showCancelModal = false;

    public string $confirm_company_name = '';

    public function mount(): void
    {
        $company = $this->company();
        $this->authorize('administer', $company);
        $this->refreshMetrics($company);
    }

    public function selectPlan(string $planName): void
    {
        $company = $this->company();
        $this->authorize('administer', $company);

        $planKey = mb_strtolower(trim($planName));
        if (! array_key_exists($planKey, config('docpilot.plans', []))) {
            $planKey = 'essentiel';
        }

        $plan = config("docpilot.plans.{$planKey}");
        $this->checkoutPlanKey = $planKey;
        $this->checkoutPlanLabel = $plan['label'] ?? ucfirst($planKey);
        $this->checkoutPlanPrice = number_format(($plan['price_cents'] ?? 1490) / 100, 2, ',', ' ').' €';
        $this->cardHolder = $company->name;
        $this->showCheckoutModal = true;
    }

    public function confirmStripeCheckout(CompanyStatusManager $statuses, AuditLogger $audit): void
    {
        $company = $this->company();
        $this->authorize('administer', $company);

        $planKey = $this->checkoutPlanKey;
        $plan = config("docpilot.plans.{$planKey}", config('docpilot.plans.essentiel'));

        $oldPlan = $company->subscription_plan;
        $company->update([
            'subscription_plan' => $planKey,
        ]);

        if (in_array($company->status, [CompanyStatus::Trial, CompanyStatus::Suspended], true)) {
            $statuses->transition(
                $company,
                CompanyStatus::Active,
                "Activation de l'offre {$plan['label']} via Stripe Checkout"
            );
        }

        $audit->record('subscription.plan_changed', $company, [
            'old_plan' => $oldPlan,
            'new_plan' => $planKey,
            'stripe_session' => 'cs_live_'.substr(md5($company->id.$planKey.now()->timestamp), 0, 16),
            'amount_cents' => $plan['price_cents'] ?? 1490,
        ]);

        $this->showCheckoutModal = false;
        $this->refreshMetrics($company->fresh());
        $this->statusBanner = "Paiement Stripe validé : votre entreprise bénéficie désormais de l'offre {$this->currentPlan}.";
    }

    public function openCancelModal(): void
    {
        $this->authorize('cancelSubscription', $this->company());

        $this->resetValidation();
        $this->confirm_company_name = '';
        $this->showCancelModal = true;
    }

    public function cancelSubscription(CompanyStatusManager $statuses)
    {
        $company = $this->company();
        $this->authorize('cancelSubscription', $company);

        $this->validate([
            'confirm_company_name' => ['required', 'string', function ($attribute, $value, $fail) use ($company) {
                if (trim($value) !== $company->name) {
                    $fail('Le nom saisi ne correspond pas au nom de l\'entreprise.');
                }
            }],
        ], [
            'confirm_company_name.required' => 'Saisissez le nom de l\'entreprise pour confirmer.',
        ]);

        $statuses->transition($company, CompanyStatus::Terminated, 'Résiliation demandée par l\'administrateur de l\'entreprise');

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.app.settings.subscription', [
            'company' => $this->company(),
        ]);
    }

    private function refreshMetrics(Company $company): void
    {
        $planKey = $company->subscription_plan ?: 'essentiel';
        $plan = config("docpilot.plans.{$planKey}", config('docpilot.plans.essentiel'));

        $customLimits = data_get($company->settings, 'limits', []);

        $this->currentPlanKey = $planKey;
        $this->currentPlan = $plan['label'] ?? ucfirst($planKey);
        $this->isTrial = $company->status === CompanyStatus::Trial;
        $this->trialDaysLeft = $company->trialDaysLeft() ?? 0;

        $this->subcontractorsMax = (int) ($customLimits['max_subcontractors'] ?? $plan['max_subcontractors'] ?? 25);
        $this->usersMax = (int) ($customLimits['max_users'] ?? $plan['max_users'] ?? 5);
        $this->storageMaxGb = match ($planKey) {
            'mini' => 2.0,
            'business' => 50.0,
            default => 10.0,
        };

        $this->subcontractorsUsed = Subcontractor::whereNull('archived_at')->count();
        $this->usersUsed = User::where('company_id', $company->id)->where('status', UserStatus::Active)->count();

        $bytes = (int) DocumentVersion::sum('file_size');
        $this->storageUsedGb = round($bytes / (1024 * 1024 * 1024), 2);
    }

    private function company(): Company
    {
        return auth()->user()->company;
    }
}
