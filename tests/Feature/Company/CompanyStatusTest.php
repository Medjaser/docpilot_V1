<?php

use App\Domain\Company\CompanyStatusManager;
use App\Domain\Company\InvalidCompanyTransition;
use App\Enums\CompanyStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Livewire\Admin\Companies\Index as AdminCompaniesIndex;
use App\Livewire\App\Settings\Index as SettingsIndex;
use App\Livewire\App\Settings\Subscription;
use App\Livewire\Portal\Upload as PortalUpload;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();

    $this->company = Company::factory()->create(['name' => 'BatiNova Rénovation']);
    $this->admin = User::factory()->for($this->company)->create();
    $this->manager = User::factory()->for($this->company)->role(Role::Manager)->create();
    $this->platformAdmin = User::factory()->platformAdmin()->create();
});

describe('transitions', function () {
    it('applique une transition valide et l\'audite', function () {
        $this->actingAs($this->platformAdmin);

        app(CompanyStatusManager::class)->transition($this->company, CompanyStatus::Suspended, 'Impayé depuis 30 jours');

        $company = $this->company->fresh();
        expect($company->status)->toBe(CompanyStatus::Suspended)
            ->and($company->suspended_at)->not->toBeNull()
            ->and($company->status_reason)->toBe('Impayé depuis 30 jours');

        $log = AuditLog::acrossAllCompanies()->where('action', 'company.status_changed')->sole();
        expect($log->company_id)->toBe($this->company->id)
            ->and($log->user_id)->toBe($this->platformAdmin->id)
            ->and($log->metadata)->toEqual(['from' => 'active', 'to' => 'suspended', 'reason' => 'Impayé depuis 30 jours']);
    });

    it('refuse une transition invalide sans rien modifier', function (CompanyStatus $from, CompanyStatus $to) {
        $this->company->update(['status' => $from]);

        expect(fn () => app(CompanyStatusManager::class)->transition($this->company, $to, 'Motif de test'))
            ->toThrow(InvalidCompanyTransition::class);

        expect($this->company->fresh()->status)->toBe($from)
            ->and(AuditLog::acrossAllCompanies()->where('action', 'company.status_changed')->exists())->toBeFalse();
    })->with([
        'archivée → active' => [CompanyStatus::Archived, CompanyStatus::Active],
        'active → essai' => [CompanyStatus::Active, CompanyStatus::Trial],
        'active → archivée' => [CompanyStatus::Active, CompanyStatus::Archived],
        'suspendue → suspendue' => [CompanyStatus::Suspended, CompanyStatus::Suspended],
    ]);

    it('exige un motif', function () {
        expect(fn () => app(CompanyStatusManager::class)->transition($this->company, CompanyStatus::Suspended, '   '))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('admin plateforme', function () {
    it('suspend une entreprise avec un motif obligatoire', function () {
        Livewire::actingAs($this->platformAdmin)
            ->test(AdminCompaniesIndex::class)
            ->assertSee('BatiNova Rénovation')
            ->call('openStatusModal', $this->company->id, 'suspended')
            ->assertSet('showStatusModal', true)
            ->call('confirmStatusChange')
            ->assertHasErrors(['status_reason' => 'required'])
            ->set('status_reason', 'Impayé depuis 30 jours')
            ->call('confirmStatusChange')
            ->assertHasNoErrors()
            ->assertSet('showStatusModal', false);

        expect($this->company->fresh()->status)->toBe(CompanyStatus::Suspended);
    });

    it('réactive puis résilie une entreprise', function () {
        $this->company->update(['status' => CompanyStatus::Suspended]);

        Livewire::actingAs($this->platformAdmin)
            ->test(AdminCompaniesIndex::class)
            ->call('openStatusModal', $this->company->id, 'active')
            ->set('status_reason', 'Paiement régularisé')
            ->call('confirmStatusChange')
            ->call('openStatusModal', $this->company->id, 'terminated')
            ->set('status_reason', 'Demande écrite du client')
            ->call('confirmStatusChange')
            ->assertHasNoErrors();

        expect($this->company->fresh()->status)->toBe(CompanyStatus::Terminated)
            ->and(AuditLog::acrossAllCompanies()->where('action', 'company.status_changed')->count())->toBe(2);
    });

    it('refuse une transition non autorisée depuis l\'interface', function () {
        $this->company->update(['status' => CompanyStatus::Archived]);

        Livewire::actingAs($this->platformAdmin)
            ->test(AdminCompaniesIndex::class)
            ->call('openStatusModal', $this->company->id, 'active')
            ->assertHasErrors(['status'])
            ->assertSet('showStatusModal', false);

        expect($this->company->fresh()->status)->toBe(CompanyStatus::Archived);
    });

    it('propose les actions selon le statut', function () {
        expect(AdminCompaniesIndex::statusActions(CompanyStatus::Trial))->toBe(['active' => 'Activer', 'suspended' => 'Suspendre', 'terminated' => 'Résilier'])
            ->and(AdminCompaniesIndex::statusActions(CompanyStatus::Suspended))->toBe(['active' => 'Réactiver', 'terminated' => 'Résilier'])
            ->and(AdminCompaniesIndex::statusActions(CompanyStatus::Archived))->toBe([]);
    });

    it('refuse la page à un utilisateur client (403)', function () {
        Livewire::actingAs($this->admin)
            ->test(AdminCompaniesIndex::class)
            ->assertForbidden();
    });
});

describe('résiliation par l\'administrateur de l\'entreprise', function () {
    it('exige de saisir exactement le nom de l\'entreprise', function () {
        Livewire::actingAs($this->admin)
            ->test(Subscription::class)
            ->assertSee('Résilier mon abonnement')
            ->call('openCancelModal')
            ->set('confirm_company_name', 'BatiNova')
            ->call('cancelSubscription')
            ->assertHasErrors(['confirm_company_name']);

        expect($this->company->fresh()->status)->toBe(CompanyStatus::Active);
    });

    it('résilie, audite et déconnecte', function () {
        Livewire::actingAs($this->admin)
            ->test(Subscription::class)
            ->call('openCancelModal')
            ->set('confirm_company_name', 'BatiNova Rénovation')
            ->call('cancelSubscription')
            ->assertRedirect(route('login'));

        expect($this->company->fresh()->status)->toBe(CompanyStatus::Terminated)
            ->and($this->company->fresh()->terminated_at)->not->toBeNull()
            ->and(Auth::check())->toBeFalse()
            ->and(AuditLog::acrossAllCompanies()->where('action', 'company.status_changed')->sole()->metadata['to'])->toBe('terminated');
    });

    it('refuse la page abonnement, donc la résiliation, à un gestionnaire (403)', function () {
        Livewire::actingAs($this->manager)
            ->test(Subscription::class)
            ->assertForbidden();
    });
});

describe('bandeau de statut', function () {
    it('affiche les jours d\'essai restants en bleu', function () {
        $this->company->update(['status' => CompanyStatus::Trial, 'trial_ends_at' => now()->addDays(9)]);

        $this->actingAs($this->admin)
            ->get('/app/settings/company')
            ->assertOk()
            ->assertSee('Essai gratuit : 9 jours restants')
            ->assertSee('bg-blue-600 text-white px-4', false);
    });

    it('n\'affiche pas de bandeau pour une entreprise active', function () {
        $this->actingAs($this->admin)
            ->get('/app/settings/company')
            ->assertOk()
            ->assertDontSee('Essai gratuit')
            ->assertDontSee('Compte suspendu');
    });
});

describe('entreprise suspendue', function () {
    beforeEach(function () {
        $this->company->update(['status' => CompanyStatus::Suspended]);
    });

    it('reste consultable avec un bandeau rouge et des boutons désactivés', function () {
        $this->actingAs($this->admin)
            ->get('/app/settings/company')
            ->assertOk()
            ->assertSee('Compte suspendu')
            ->assertSee('bg-rose-600', false)
            ->assertSee('Lecture seule')
            ->assertDontSee('Enregistrer les informations');

        $this->actingAs($this->admin)
            ->get('/app/settings/subscription')
            ->assertOk()
            ->assertSee('title="Compte suspendu : lecture seule"', false);
    });

    it('refuse les écritures, même à un administrateur', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('name', 'Nouveau nom')
            ->call('saveCompany')
            ->assertForbidden();

        $admin = $this->admin->fresh();
        expect($admin->can('view', $this->company))->toBeTrue()
            ->and($admin->can('update', $this->company))->toBeFalse()
            ->and($admin->can('create', Subcontractor::class))->toBeFalse()
            ->and($admin->hasPermission('subcontractors.view'))->toBeTrue()
            ->and($admin->hasPermission('documents.download'))->toBeTrue()
            ->and($admin->hasPermission('documents.validate'))->toBeFalse()
            ->and($admin->hasPermission('users.invite'))->toBeFalse();
    });

    it('bloque les requêtes d\'écriture hors Livewire', function () {
        Route::middleware(['web', 'auth', 'company', 'company.active'])
            ->post('/app/_test-write', fn () => 'ok');

        $this->actingAs($this->admin)->post('/app/_test-write')->assertForbidden();
    });

    it('désactive le portail de dépôt', function () {
        $subcontractor = Subcontractor::factory()->for($this->company)->create();
        $request = new DocumentRequest([
            'subcontractor_id' => $subcontractor->id,
            'status' => RequestStatus::Sent,
            'expires_at' => now()->addDays(10),
        ]);
        $request->company_id = $this->company->id;
        $token = $request->issueToken();
        $request->save();

        Livewire::test(PortalUpload::class, ['token' => $token])
            ->assertSet('portalDisabled', true)
            ->assertSee('Portail de dépôt indisponible')
            ->call('submitAll')
            ->assertForbidden();
    });
});

it('refuse l\'accès à /app pour une entreprise résiliée', function () {
    $this->company->update(['status' => CompanyStatus::Terminated]);

    $this->actingAs($this->admin)->get('/app/settings/company')->assertForbidden();
});
