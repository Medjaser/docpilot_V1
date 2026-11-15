<?php

namespace App\Livewire\Auth;

use App\Domain\Audit\AuditLogger;
use App\Enums\CompanyStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Créer votre espace entreprise - DocPilot')]
class RegisterCompany extends Component
{
    public int $step = 1;

    // Étape 1: Entreprise
    public string $company_name = '';
    public string $siret = '';
    public string $industry = 'BTP - Construction / Rénovation';
    public string $city = '';
    public string $phone = '';

    // Étape 2: Administrateur
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $terms = false;

    public function nextStep(): void
    {
        $this->validate([
            'company_name' => 'required|string|min:2',
            'siret' => 'required|string|size:14|regex:/^[0-9]{14}$/|unique:companies,registration_number',
            'industry' => 'required|string',
            'city' => 'required|string',
            'phone' => 'required|string',
        ], [
            'siret.size' => 'Le SIRET doit contenir exactement 14 chiffres.',
            'siret.regex' => 'Le SIRET doit contenir uniquement des chiffres.',
            'siret.unique' => 'Une entreprise est déjà inscrite avec ce SIRET.',
            'company_name.required' => 'La raison sociale est obligatoire.',
            'city.required' => 'La ville est obligatoire.',
            'phone.required' => 'Le téléphone est obligatoire.',
        ]);

        $this->step = 2;
    }

    public function previousStep(): void
    {
        $this->step = 1;
    }

    public function register()
    {
        $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|same:password_confirmation',
            'terms' => 'accepted',
        ], [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.same' => 'Les mots de passe ne correspondent pas.',
            'terms.accepted' => 'Vous devez accepter les conditions générales.',
        ]);

        $user = null;
        $company = null;

        DB::transaction(function () use (&$company, &$user) {
            $company = Company::create([
                'name' => $this->company_name,
                'registration_number' => $this->siret,
                'sector' => $this->industry,
                'city' => $this->city,
                'phone' => $this->phone,
                'email' => $this->email,
                'status' => CompanyStatus::Trial,
                'trial_ends_at' => now()->addDays(14),
                'subscription_plan' => 'essentiel',
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'password' => $this->password,
                'role' => Role::Admin,
                'status' => UserStatus::Active,
            ]);

            \App\Domain\Company\CompanyDefaultsSeeder::seedDefaultsForCompany($company->id);
        });

        Auth::login($user);

        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            logger()->error('Impossible d\'envoyer l\'e-mail de vérification lors de l\'inscription : '.$e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $e,
            ]);
            session()->flash('warning', 'Votre compte a été créé avec succès, mais l\'envoi du courriel de vérification a rencontré un problème temporaire. Vous pouvez utiliser le bouton "Renvoyer l\'e-mail" sur la page suivante.');
        }

        app(AuditLogger::class)->log(
            action: 'company.registered',
            entity: $company,
            userType: 'user',
            userId: $user->id
        );

        return redirect()->route('verification.notice');
    }

    public function render()
    {
        return view('livewire.auth.register-company');
    }
}
