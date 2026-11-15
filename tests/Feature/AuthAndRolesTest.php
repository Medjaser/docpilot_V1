<?php

use App\Enums\CompanyStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Livewire\App\Users\Index as UsersIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\RegisterCompany;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DemoSeeder::class);

    $this->sophie = User::where('email', 'sophie@batinova.fr')->sole();
    $this->marc = User::where('email', 'marc.dubois@batinova.fr')->sole();
    $this->julien = User::where('email', 'julien.moreau@batinova.fr')->sole();
    $this->platformAdmin = User::where('email', 'admin@docpilot.local')->sole();
    $this->batinova = Company::where('name', 'BatiNova Rénovation')->sole();
});

it('permet la connexion OK avec un compte valide et met à jour last_login_at et audit_log', function () {
    Livewire::test(Login::class)
        ->set('email', 'sophie@batinova.fr')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->id)->toBe($this->sophie->id)
        ->and($this->sophie->fresh()->last_login_at)->not->toBeNull();

    expect(AuditLog::acrossAllCompanies()->where('action', 'login')->where('user_id', $this->sophie->id)->exists())->toBeTrue();
});

it('refuse la connexion KO et écrit dans audit_logs', function () {
    Livewire::test(Login::class)
        ->set('email', 'sophie@batinova.fr')
        ->set('password', 'mauvais-mot-de-passe')
        ->call('login')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
    expect(AuditLog::acrossAllCompanies()->where('action', 'login_failed')->exists())->toBeTrue();
});

it('refuse la connexion pour un compte désactivé', function () {
    $this->sophie->update([
        'status' => UserStatus::Deactivated,
        'deactivated_at' => now(),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'sophie@batinova.fr')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

it('refuse la connexion pour une entreprise résiliée', function () {
    $this->batinova->update(['status' => CompanyStatus::Terminated]);

    Livewire::test(Login::class)
        ->set('email', 'sophie@batinova.fr')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

it('autorise la connexion en lecture seule pour une entreprise suspendue', function () {
    $this->batinova->update(['status' => CompanyStatus::Suspended]);

    Livewire::test(Login::class)
        ->set('email', 'sophie@batinova.fr')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    expect(Auth::check())->toBeTrue();
});

it('redirige /app vers /login sans connexion', function () {
    $this->get('/app/dashboard')
        ->assertRedirect(route('login'));
});

it('refuse la validation d\'un document à un viewer (403)', function () {
    app(\App\Support\Tenancy\CurrentCompany::class)->set($this->julien->company_id);
    $document = \App\Models\Document::firstOrFail();

    expect($this->julien->can('view', $document))->toBeTrue()
        ->and($this->julien->can('validate', $document))->toBeFalse();
});

it('refuse l\'invitation d\'un utilisateur à un manager (403)', function () {
    $this->actingAs($this->marc)
        ->get('/app/users')
        ->assertStatus(403);
});

it('refuse l\'accès à /admin pour un utilisateur client (403)', function () {
    $this->actingAs($this->sophie)
        ->get('/admin/dashboard')
        ->assertStatus(403);
});

it('redirige un platform admin de /app vers /admin', function () {
    $this->actingAs($this->platformAdmin)
        ->get('/app/dashboard')
        ->assertRedirect(route('admin.dashboard'));
});

it('empêche la rétrogradation du dernier admin d\'une entreprise', function () {
    // Sophie est le seul admin de BatiNova
    expect(User::where('company_id', $this->batinova->id)->where('role', Role::Admin)->count())->toBe(1);

    Livewire::actingAs($this->sophie)
        ->test(UsersIndex::class)
        ->call('updateRole', $this->sophie->id, Role::Manager->value)
        ->assertStatus(403);
});

it('inscrit une entreprise et son admin dans une transaction avec e-mail de vérification', function () {
    Livewire::test(RegisterCompany::class)
        ->set('company_name', 'Nouvelle Entrep BTP')
        ->set('siret', '98765432100099')
        ->set('industry', 'BTP')
        ->set('city', 'Paris')
        ->set('phone', '01 40 00 00 00')
        ->call('nextStep')
        ->set('first_name', 'Jean')
        ->set('last_name', 'Dupont')
        ->set('email', 'jean.dupont@nouvellebtp.fr')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->set('terms', true)
        ->call('register')
        ->assertRedirect(route('verification.notice'));

    $newCompany = Company::where('registration_number', '98765432100099')->first();
    expect($newCompany)->not->toBeNull()
        ->and($newCompany->status)->toBe(CompanyStatus::Trial)
        ->and($newCompany->trial_ends_at->format('Y-m-d'))->toBe(now()->addDays(14)->format('Y-m-d'));

    $newUser = User::where('email', 'jean.dupont@nouvellebtp.fr')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->company_id)->toBe($newCompany->id)
        ->and($newUser->role)->toBe(Role::Admin);
});
