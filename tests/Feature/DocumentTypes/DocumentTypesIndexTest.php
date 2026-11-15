<?php

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Livewire\App\DocumentTypes\Index;
use App\Livewire\App\Requests\SendRequestModal;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Subcontractor;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();

    $this->companyA = Company::factory()->create(['name' => 'Entreprise Alpha']);
    $this->companyB = Company::factory()->create(['name' => 'Entreprise Bravo']);

    $this->adminA = User::factory()->for($this->companyA)->role(Role::Admin)->create();
    $this->managerA = User::factory()->for($this->companyA)->role(Role::Manager)->create();
    $this->viewerA = User::factory()->for($this->companyA)->role(Role::Viewer)->create();

    $this->adminB = User::factory()->for($this->companyB)->role(Role::Admin)->create();
});

it('l\'admin crée, modifie, désactive, archive et restaure une catégorie (vérifié en base)', function () {
    app(CurrentCompany::class)->set($this->companyA->id);

    // 1. Création
    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('openCreateSlideOver')
        ->set('name', 'Attestation de régularité fiscale')
        ->set('description', 'À fournir chaque semestre.')
        ->set('required', true)
        ->set('has_expiration', true)
        ->set('duration_months', 6)
        ->set('reminders', [60, 30, 7])
        ->set('allowed_formats', ['pdf'])
        ->set('max_size_mb', 5)
        ->set('message_template', 'Bonjour {nom_contact}, merci de transmettre votre attestation fiscale.')
        ->set('active', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showSlideOver', false);

    $type = DocumentType::where('name', 'Attestation de régularité fiscale')->first();
    expect($type)->not->toBeNull()
        ->and($type->company_id)->toBe($this->companyA->id)
        ->and($type->is_mandatory)->toBeTrue()
        ->and($type->has_expiration_date)->toBeTrue()
        ->and($type->default_validity_months)->toBe(6)
        ->and($type->reminder_days)->toBe([60, 30, 7])
        ->and($type->allowed_extensions)->toBe(['pdf'])
        ->and($type->max_file_size_mb)->toBe(5)
        ->and($type->is_active)->toBeTrue();

    expect(AuditLog::where('action', 'document_type.created')->where('entity_id', $type->id)->exists())->toBeTrue();

    // 2. Modification
    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('edit', $type->id)
        ->assertSet('name', 'Attestation de régularité fiscale')
        ->assertSet('duration_months', 6)
        ->set('name', 'Attestation fiscale annuelle')
        ->set('duration_months', 12)
        ->call('save')
        ->assertHasNoErrors();

    $type->refresh();
    expect($type->name)->toBe('Attestation fiscale annuelle')
        ->and($type->default_validity_months)->toBe(12);

    expect(AuditLog::where('action', 'document_type.updated')->where('entity_id', $type->id)->exists())->toBeTrue();

    // 3. Désactivation / Réactivation
    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('toggleStatus', $type->id);

    expect($type->fresh()->is_active)->toBeFalse();
    expect(AuditLog::where('action', 'document_type.deactivated')->where('entity_id', $type->id)->exists())->toBeTrue();

    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('toggleStatus', $type->id);

    expect($type->fresh()->is_active)->toBeTrue();
    expect(AuditLog::where('action', 'document_type.activated')->where('entity_id', $type->id)->exists())->toBeTrue();

    // 4. Archivage
    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('archive', $type->id);

    $type->refresh();
    expect($type->archived_at)->not->toBeNull()
        ->and($type->is_active)->toBeFalse();
    expect(AuditLog::where('action', 'document_type.archived')->where('entity_id', $type->id)->exists())->toBeTrue();

    // 5. Restauration
    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('restore', $type->id);

    $type->refresh();
    expect($type->archived_at)->toBeNull()
        ->and($type->is_active)->toBeTrue();
    expect(AuditLog::where('action', 'document_type.restored')->where('entity_id', $type->id)->exists())->toBeTrue();
});

it('un gestionnaire et un lecteur reçoivent 403 sur l\'écran et sur chaque action', function () {
    app(CurrentCompany::class)->set($this->companyA->id);
    $type = DocumentType::factory()->for($this->companyA)->create(['name' => 'Kbis']);

    // HTTP 403 sur la route
    $this->actingAs($this->managerA)->get('/app/document-types')->assertForbidden();
    $this->actingAs($this->viewerA)->get('/app/document-types')->assertForbidden();

    // Livewire interdiction au mount
    Livewire::actingAs($this->managerA)->test(Index::class)->assertForbidden();
    Livewire::actingAs($this->viewerA)->test(Index::class)->assertForbidden();

    // Vérification des Policies
    expect($this->managerA->can('viewAny', DocumentType::class))->toBeFalse()
        ->and($this->managerA->can('create', DocumentType::class))->toBeFalse()
        ->and($this->managerA->can('update', $type))->toBeFalse()
        ->and($this->managerA->can('delete', $type))->toBeFalse();

    expect($this->viewerA->can('viewAny', DocumentType::class))->toBeFalse()
        ->and($this->viewerA->can('create', DocumentType::class))->toBeFalse()
        ->and($this->viewerA->can('update', $type))->toBeFalse()
        ->and($this->viewerA->can('delete', $type))->toBeFalse();
});

it('valide les erreurs (nom en double, formats vides, durée manquante avec expiration)', function () {
    app(CurrentCompany::class)->set($this->companyA->id);
    $existing = DocumentType::factory()->for($this->companyA)->create(['name' => 'Assurance Décennale']);

    // 1. Nom en double
    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('openCreateSlideOver')
        ->set('name', 'Assurance Décennale')
        ->call('save')
        ->assertHasErrors(['name']);

    // 2. Formats vides
    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('openCreateSlideOver')
        ->set('name', 'Autre Document')
        ->set('allowed_formats', [])
        ->call('save')
        ->assertHasErrors(['allowed_formats']);

    // 3. Durée manquante quand has_expiration est vrai
    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('openCreateSlideOver')
        ->set('name', 'Document avec expiration')
        ->set('has_expiration', true)
        ->set('duration_months', null)
        ->call('save')
        ->assertHasErrors(['duration_months']);
});

it('l\'entreprise A ne peut pas modifier une catégorie de B via edit($id) ou toggleStatus($id)', function () {
    app(CurrentCompany::class)->set($this->companyB->id);
    $foreignType = DocumentType::factory()->for($this->companyB)->create(['name' => 'Secret Bravo']);

    app(CurrentCompany::class)->set($this->companyA->id);

    // CompanyScope filtre : findOrFail sur ID étranger donne 404 (non trouvé)
    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('edit', $foreignType->id)
        ->assertNotFound();

    Livewire::actingAs($this->adminA)
        ->test(Index::class)
        ->call('toggleStatus', $foreignType->id)
        ->assertNotFound();

    // Vérifier que le statut n'a pas été changé chez B
    expect($foreignType->fresh()->is_active)->toBeTrue();
});

it('une catégorie archivée n\'est plus proposée dans le formulaire de demande', function () {
    app(CurrentCompany::class)->set($this->companyA->id);

    $activeType = DocumentType::factory()->for($this->companyA)->create([
        'name' => 'Kbis Actif',
        'is_active' => true,
        'archived_at' => null,
    ]);

    $archivedType = DocumentType::factory()->for($this->companyA)->create([
        'name' => 'Ancien Document Archivé',
        'is_active' => false,
        'archived_at' => now(),
    ]);

    $subcontractor = Subcontractor::factory()->for($this->companyA)->create();

    Document::factory()->for($this->companyA)->create([
        'subcontractor_id' => $subcontractor->id,
        'document_type_id' => $activeType->id,
        'status' => DocumentStatus::Missing,
    ]);

    Document::factory()->for($this->companyA)->create([
        'subcontractor_id' => $subcontractor->id,
        'document_type_id' => $archivedType->id,
        'status' => DocumentStatus::Missing,
    ]);

    Livewire::actingAs($this->adminA)
        ->test(SendRequestModal::class, ['subcontractorId' => $subcontractor->id])
        ->assertSee('Kbis Actif')
        ->assertDontSee('Ancien Document Archivé')
        ->assertSet('available_docs', fn ($docs) => array_key_exists('Kbis Actif', $docs) && ! array_key_exists('Ancien Document Archivé', $docs));
});
