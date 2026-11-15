<?php

use App\Domain\Company\CompanySettings;
use App\Enums\Role;
use App\Livewire\App\Settings\Index as SettingsIndex;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('s3');
    $this->withoutVite();

    $this->company = Company::factory()->create([
        'name' => 'BatiNova Rénovation',
        'registration_number' => '12345678900015',
        'postal_code' => '69007',
        'city' => 'Lyon',
    ]);
    $this->admin = User::factory()->for($this->company)->create();
    $this->manager = User::factory()->for($this->company)->role(Role::Manager)->create();
});

describe('navigation entre sections', function () {
    it('affiche la section company avec les données de l\'entreprise et le menu actif', function () {
        $this->actingAs($this->admin)
            ->get('/app/settings/company')
            ->assertOk()
            ->assertSeeLivewire(SettingsIndex::class)
            ->assertSee('Informations de l\'entreprise')
            ->assertSee('Enregistrer les informations')
            ->assertSee('aria-current="page"', false);

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->assertSet('section', 'company')
            ->assertSet('name', 'BatiNova Rénovation')
            ->assertSet('registration_number', '12345678900015');
    });

    it('affiche chaque section connue', function (string $section) {
        $this->actingAs($this->admin)
            ->get("/app/settings/{$section}")
            ->assertOk();
    })->with(['company', 'notifications', 'deadlines', 'emails']);

    it('ouvre la section company par défaut', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class)
            ->assertSet('section', 'company');
    });

    it('renvoie 404 pour une section inconnue', function () {
        $this->actingAs($this->admin)->get('/app/settings/info')->assertNotFound();
        $this->actingAs($this->admin)->get('/app/settings/templates')->assertNotFound();
    });
});

describe('sections réservées aux administrateurs', function () {
    it('affiche Abonnement et Modèles d\'e-mails à l\'administrateur', function () {
        $this->actingAs($this->admin)
            ->get('/app/settings/company')
            ->assertOk()
            ->assertSee('Abonnement &amp; Offre', false)
            ->assertSee('Modèles d\'e-mails')
            ->assertSee(route('subscription'), false)
            ->assertSee(route('settings.index', ['section' => 'emails']), false);
    });

    it('masque Abonnement et Modèles d\'e-mails aux gestionnaires et lecteurs', function (Role $role) {
        $user = User::factory()->for($this->company)->role($role)->create();

        $this->actingAs($user)
            ->get('/app/settings/company')
            ->assertOk()
            ->assertSee('Informations de l\'entreprise')
            ->assertSee('Notifications')
            ->assertDontSee('Abonnement &amp; Offre', false)
            ->assertDontSee('Modèles d\'e-mails')
            ->assertDontSee('Voir mon abonnement')
            ->assertDontSee(route('subscription'), false)
            ->assertDontSee(route('settings.index', ['section' => 'emails']), false);
    })->with([Role::Manager, Role::Viewer]);

    it('refuse l\'accès direct à ces sections aux gestionnaires et lecteurs (403)', function (Role $role) {
        $user = User::factory()->for($this->company)->role($role)->create();

        $this->actingAs($user)->get('/app/settings/emails')->assertForbidden();
        $this->actingAs($user)->get('/app/settings/subscription')->assertForbidden();
    })->with([Role::Manager, Role::Viewer]);

    it('laisse les sections non réservées accessibles aux gestionnaires et lecteurs', function (Role $role) {
        $user = User::factory()->for($this->company)->role($role)->create();

        $this->actingAs($user)->get('/app/settings/notifications')->assertOk();
        $this->actingAs($user)->get('/app/settings/deadlines')->assertOk();
    })->with([Role::Manager, Role::Viewer]);
});

describe('informations de l\'entreprise', function () {
    it('enregistre les informations et audite les valeurs avant / après', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('trade_name', 'BatiNova')
            ->set('registration_number', '987 654 321 00023')
            ->set('postal_code', '69003')
            ->set('email', 'contact@batinova.fr')
            ->call('saveCompany')
            ->assertHasNoErrors()
            ->assertSet('toastMessage', 'Les informations de l\'entreprise ont été enregistrées.');

        $company = $this->company->fresh();
        expect($company->trade_name)->toBe('BatiNova')
            ->and($company->registration_number)->toBe('98765432100023')
            ->and($company->postal_code)->toBe('69003');

        $log = AuditLog::acrossAllCompanies()->where('action', 'company.updated')->sole();
        expect($log->company_id)->toBe($this->company->id)
            ->and($log->user_id)->toBe($this->admin->id)
            ->and($log->metadata['before'])->toMatchArray(['registration_number' => '12345678900015', 'postal_code' => '69007'])
            ->and($log->metadata['after'])->toMatchArray(['registration_number' => '98765432100023', 'postal_code' => '69003']);
    });

    it('accepte le SIRET inchangé de l\'entreprise elle-même', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('city', 'Villeurbanne')
            ->call('saveCompany')
            ->assertHasNoErrors();
    });

    it('refuse un SIRET invalide', function (string $siret) {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('registration_number', $siret)
            ->call('saveCompany')
            ->assertHasErrors(['registration_number']);

        expect($this->company->fresh()->registration_number)->toBe('12345678900015');
    })->with([
        'clé de Luhn fausse' => '12345678900012',
        'trop court' => '1234567890',
        'lettres' => 'ABCDEFGHIJKLMN',
    ]);

    it('affiche un résumé des erreurs à côté du bouton d\'enregistrement', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('registration_number', '12345678912345')
            ->call('saveCompany')
            ->assertHasErrors(['registration_number'])
            ->assertSee('Les informations n\'ont pas été enregistrées')
            ->assertSee('Le SIRET doit contenir 14 chiffres et être un numéro valide.');
    });

    it('refuse un SIRET déjà utilisé par une autre entreprise', function () {
        Company::factory()->create(['registration_number' => '98765432100023']);

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('registration_number', '98765432100023')
            ->call('saveCompany')
            ->assertHasErrors(['registration_number' => 'unique']);
    });

    it('refuse un code postal qui n\'a pas 5 chiffres', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('postal_code', '6900')
            ->call('saveCompany')
            ->assertHasErrors(['postal_code']);
    });

    it('refuse l\'enregistrement à un gestionnaire (403) mais lui laisse la lecture seule', function () {
        Livewire::actingAs($this->manager)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->assertSee('Lecture seule')
            ->assertDontSee('Enregistrer les informations')
            ->set('name', 'Piratage')
            ->call('saveCompany')
            ->assertForbidden();

        expect($this->company->fresh()->name)->toBe('BatiNova Rénovation');
    });

    it('refuse les notifications et la suppression du logo à un gestionnaire (403)', function (string $action) {
        Livewire::actingAs($this->manager)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->call($action)
            ->assertForbidden();
    })->with(['saveNotifications', 'removeLogo', 'saveDeadlines', 'saveEmails']);
});

describe('logo', function () {
    it('refuse un logo SVG', function () {
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('logo', $svg)
            ->assertHasErrors(['logo'])
            ->call('saveCompany')
            ->assertHasErrors(['logo']);

        expect($this->company->fresh()->logo_path)->toBeNull()
            ->and(Storage::disk('s3')->allFiles())->toBeEmpty();
    });

    it('refuse un logo de plus de 2 Mo', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('logo', UploadedFile::fake()->image('logo.png')->size(3000))
            ->assertHasErrors(['logo' => 'max']);
    });

    it('stocke le logo redimensionné à 512 px sur s3 dans le dossier de l\'entreprise', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('logo', UploadedFile::fake()->image('logo.png', 1024, 600))
            ->call('saveCompany')
            ->assertHasNoErrors()
            ->assertRedirect(route('settings.index', ['section' => 'company']));

        $company = $this->company->fresh();
        expect($company->logo_path)->toBe("companies/{$company->id}/branding/logo-{$company->logo_uuid}.png");
        Storage::disk('s3')->assertExists($company->logo_path);

        [$width, $height] = getimagesizefromstring(Storage::disk('s3')->get($company->logo_path));
        expect($width)->toBe(512)->and($height)->toBe(300);

        $this->get($company->logoUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    });

    it('supprime l\'ancien fichier quand le logo est remplacé', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('logo', UploadedFile::fake()->image('logo.png', 300, 300))
            ->call('saveCompany');
        $oldPath = $this->company->fresh()->logo_path;

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('logo', UploadedFile::fake()->image('nouveau.jpg', 300, 300))
            ->call('saveCompany')
            ->assertHasNoErrors();
        $newPath = $this->company->fresh()->logo_path;

        expect($newPath)->not->toBe($oldPath)->toEndWith('.jpg');
        Storage::disk('s3')->assertExists($newPath);
        Storage::disk('s3')->assertMissing($oldPath);
        expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
    });

    it('affiche une erreur au lieu de planter si le stockage du logo est indisponible', function () {
        config(['docpilot.branding.disk' => 'disque-inexistant']);

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('trade_name', 'BatiNova')
            ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
            ->call('saveCompany')
            ->assertHasErrors(['logo'])
            ->assertNoRedirect();

        expect($this->company->fresh()->logo_path)->toBeNull()
            ->and($this->company->fresh()->trade_name)->toBeNull();
    });

    it('supprime le logo avec le bouton dédié', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('logo', UploadedFile::fake()->image('logo.webp', 200, 200))
            ->call('saveCompany');
        $path = $this->company->fresh()->logo_path;

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->call('removeLogo')
            ->assertRedirect(route('settings.index', ['section' => 'company']));

        expect($this->company->fresh()->logo_path)->toBeNull()
            ->and($this->company->fresh()->logo_uuid)->toBeNull();
        Storage::disk('s3')->assertMissing($path);
    });

    it('affiche le logo à la place des initiales dans la sidebar', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'company'])
            ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
            ->call('saveCompany');

        $this->actingAs($this->admin->fresh())
            ->get('/app/settings/company')
            ->assertOk()
            ->assertSee($this->company->fresh()->logoUrl(), false);
    });
});

describe('notifications', function () {
    it('affiche tous les événements avec la colonne Destinataires', function () {
        $response = $this->actingAs($this->admin)->get('/app/settings/notifications')->assertOk();

        $response->assertSee('Destinataires');
        foreach (CompanySettings::NOTIFICATION_EVENTS as $label) {
            $response->assertSee($label);
        }
    });

    it('persiste les préférences après rechargement', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'notifications'])
            ->set('notifications.doc_missing.email', true)
            ->set('notifications.new_upload.app', false)
            ->set('notifications.user_invited.recipients', 'managers')
            ->call('saveNotifications')
            ->assertHasNoErrors()
            ->assertSet('toastMessage', 'Les préférences de notifications ont été enregistrées.');

        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'notifications'])
            ->assertSet('notifications.doc_missing.email', true)
            ->assertSet('notifications.new_upload.app', false)
            ->assertSet('notifications.user_invited.recipients', 'managers')
            ->assertSet('notifications.link_expired.recipients', 'owner');

        expect(CompanySettings::for($this->company->fresh())->notifications())
            ->toHaveKeys(array_keys(CompanySettings::NOTIFICATION_EVENTS));
    });

    it('refuse un destinataire inconnu', function () {
        Livewire::actingAs($this->admin)
            ->test(SettingsIndex::class, ['section' => 'notifications'])
            ->set('notifications.new_upload.recipients', 'tout-le-monde')
            ->call('saveNotifications')
            ->assertHasErrors(['notifications.new_upload.recipients']);
    });

    it('fusionne les anciens paramètres avec les valeurs par défaut', function () {
        $this->company->update(['settings' => [
            'notifications' => ['new_upload' => ['email' => false, 'app' => true]],
            'reminder_days' => [30, 7],
        ]]);

        $settings = CompanySettings::for($this->company->fresh());

        expect($settings->notifications()['new_upload'])->toBe(['email' => false, 'app' => true, 'recipients' => 'owner'])
            ->and($settings->notifications()['link_expired'])->toBe(['email' => false, 'app' => true, 'recipients' => 'owner'])
            ->and($settings->get('reminder_days'))->toBe([30, 7]);
    });
});
