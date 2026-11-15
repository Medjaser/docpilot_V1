<?php

/*
 * Régression : la liste /app/subcontractors affichait les sous-traitants d'une autre entreprise
 * (données fictives de BatiNova) et leurs fiches renvoyaient 404.
 */

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Enums\SubcontractorStatus;
use App\Livewire\App\Dashboard;
use App\Livewire\App\GlobalSearch;
use App\Livewire\App\Subcontractors\Index as SubcontractorsIndex;
use App\Livewire\App\Subcontractors\Form as SubcontractorsForm;
use App\Livewire\App\Subcontractors\Show as SubcontractorsShow;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Subcontractor;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

beforeEach(function () {
    $this->withoutVite();

    $this->companyA = Company::factory()->create(['name' => 'Entreprise Alpha']);
    $this->companyB = Company::factory()->create(['name' => 'Entreprise Bravo']);

    $this->userA = User::factory()->for($this->companyA)->create(['first_name' => 'Alice', 'last_name' => 'Alpha']);
    $this->userB = User::factory()->for($this->companyB)->create(['first_name' => 'Bruno', 'last_name' => 'Bravo']);

    $this->ownA = Subcontractor::factory()->for($this->companyA)->create([
        'legal_name' => 'Plomberie Alpha', 'city' => 'Annecy', 'activity' => 'Plomberie',
        'internal_owner_id' => $this->userA->id, 'status' => SubcontractorStatus::Complete,
    ]);
    $this->archivedA = Subcontractor::factory()->for($this->companyA)->create([
        'legal_name' => 'Ancien Alpha', 'city' => 'Annecy', 'activity' => 'Plomberie', 'archived_at' => now(),
    ]);
    $this->foreignB = Subcontractor::factory()->for($this->companyB)->create([
        'legal_name' => 'Plomberie Bravo', 'city' => 'Bordeaux', 'activity' => 'Charpente',
        'internal_owner_id' => $this->userB->id, 'status' => SubcontractorStatus::Incomplete,
    ]);
    Subcontractor::factory()->for($this->companyB)->count(3)->create(['city' => 'Bordeaux']);

    $typeB = DocumentType::factory()->for($this->companyB)->create(['name' => 'Kbis Bravo']);
    Document::factory()->for($this->companyB)->create([
        'subcontractor_id' => $this->foreignB->id,
        'document_type_id' => $typeB->id,
        'status' => DocumentStatus::Pending,
        'expiration_date' => now()->addDays(5),
    ]);
});

/** Rejoue une vraie requête AJAX Livewire (le middleware persistant doit redéfinir l'entreprise). */
function livewireAjax(TestResponse $page, string $component, array $updates = [], array $calls = []): TestResponse
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);

    $snapshot = collect($matches[1])
        ->map(fn ($raw) => html_entity_decode($raw, ENT_QUOTES))
        ->first(fn ($raw) => json_decode($raw, true)['memo']['name'] === $component);

    expect($snapshot)->not->toBeNull("Composant {$component} absent de la page.");

    return test()->withHeaders(['X-Livewire' => 'true'])->postJson(app(HandleRequests::class)->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => $calls]],
    ]);
}

it('n\'affiche dans la liste que les sous-traitants de l\'entreprise connectée', function () {
    $this->actingAs($this->userA)
        ->get('/app/subcontractors')
        ->assertOk()
        ->assertSee('Plomberie Alpha')
        ->assertDontSee('Plomberie Bravo')
        ->assertDontSee('Bordeaux')
        ->assertDontSee('ÉlecPro Services')
        ->assertSee(route('subcontractors.show', $this->ownA->id), false)
        ->assertDontSee(route('subcontractors.show', $this->foreignB->id), false);
});

it('reste isolée pendant les requêtes AJAX Livewire (recherche, onglets, tri)', function () {
    $page = $this->actingAs($this->userA)->get('/app/subcontractors')->assertOk();

    $search = livewireAjax($page, 'app.subcontractors', ['search' => 'Plomberie'])->assertOk();
    expect($search->json('components.0.effects.html'))
        ->toContain('Plomberie Alpha')
        ->not->toContain('Plomberie Bravo');

    $archived = livewireAjax($page, 'app.subcontractors', ['tab' => 'archived'])->assertOk();
    expect($archived->json('components.0.effects.html'))
        ->toContain('Ancien Alpha')
        ->not->toContain('Bravo');

    $sorted = livewireAjax($page, 'app.subcontractors', [], [['method' => 'sortBy', 'params' => ['name'], 'metadata' => []]])->assertOk();
    expect($sorted->json('components.0.effects.html'))->not->toContain('Bravo');
});

it('limite compteurs d\'onglets et filtres (villes, activités, responsables) à l\'entreprise', function () {
    app(CurrentCompany::class)->set($this->companyA->id);

    Livewire::actingAs($this->userA)
        ->test(SubcontractorsIndex::class)
        ->assertViewHas('activeCount', 1)
        ->assertViewHas('archivedCount', 1)
        ->assertViewHas('cities', ['Annecy'])
        ->assertViewHas('activities', ['Plomberie'])
        ->assertViewHas('managers', fn ($managers) => $managers->keys()->all() === [$this->userA->id])
        // Un ID de responsable d'une autre entreprise ne filtre rien et ne révèle rien
        ->set('manager', (string) $this->userB->id)
        ->assertSee('Plomberie Alpha')
        ->assertDontSee('Plomberie Bravo');
});

it('ouvre la fiche d\'un sous-traitant de l\'entreprise et renvoie 404 pour une autre entreprise', function () {
    $this->actingAs($this->userA)
        ->get("/app/subcontractors/{$this->ownA->id}")
        ->assertOk()
        ->assertSee('Plomberie Alpha')
        ->assertDontSee('ÉlecPro Services');

    $this->actingAs($this->userA)
        ->get("/app/subcontractors/{$this->foreignB->id}")
        ->assertNotFound();

    $this->actingAs($this->userA)->get('/app/subcontractors/abc')->assertNotFound();
});

it('verrouille l\'identifiant de la fiche', function () {
    app(CurrentCompany::class)->set($this->companyA->id);

    Livewire::actingAs($this->userA)
        ->test(SubcontractorsShow::class, ['id' => $this->ownA->id])
        ->assertSet('id', $this->ownA->id)
        ->set('id', $this->foreignB->id);
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

it('laisse un lecteur consulter la fiche sans le formulaire d\'écriture', function () {
    $viewer = User::factory()->for($this->companyA)->role(Role::Viewer)->create();

    $this->actingAs($viewer)
        ->get("/app/subcontractors/{$this->ownA->id}")
        ->assertOk()
        ->assertSee('Plomberie Alpha');
});

it('calcule le tableau de bord uniquement sur l\'entreprise connectée', function () {
    app(CurrentCompany::class)->set($this->companyA->id);

    Livewire::actingAs($this->userA)
        ->test(Dashboard::class)
        ->assertViewHas('total', 1)
        ->assertViewHas('byBadge', fn ($counts) => $counts['complete'] === 1 && $counts['incomplete'] === 0)
        ->assertViewHas('documentsTotal', 0)
        ->assertViewHas('deadlines', fn ($deadlines) => $deadlines->isEmpty())
        ->assertDontSee('Kbis Bravo');

    $this->actingAs($this->userA)
        ->get('/app/dashboard')
        ->assertOk()
        ->assertSee('Bonjour Alice')
        ->assertDontSee('Plomberie Bravo');
});

it('refuse de relancer un sous-traitant d\'une autre entreprise depuis le tableau de bord', function () {
    app(CurrentCompany::class)->set($this->companyA->id);

    Livewire::actingAs($this->userA)
        ->test(Dashboard::class)
        ->call('triggerReminder', $this->foreignB->id)
        ->assertNotFound();
});

it('limite la recherche globale à l\'entreprise, y compris en AJAX', function () {
    app(CurrentCompany::class)->set($this->companyA->id);

    Livewire::actingAs($this->userA)
        ->test(GlobalSearch::class)
        ->set('query', 'Plomberie')
        ->assertSee('Plomberie Alpha')
        ->assertDontSee('Plomberie Bravo')
        ->set('query', 'Kbis')
        ->assertDontSee('Kbis Bravo');

    app(CurrentCompany::class)->clear();

    $page = $this->actingAs($this->userA)->get('/app/dashboard')->assertOk();
    $response = livewireAjax($page, 'app.global-search', ['query' => 'Plomberie'])->assertOk();

    expect($response->json('components.0.effects.html'))
        ->toContain('Plomberie Alpha')
        ->not->toContain('Plomberie Bravo');
});

it('n\'affiche dans la cloche que les notifications de l\'entreprise', function () {
    foreach ([[$this->companyA, $this->userA, 'Notif Alpha'], [$this->companyB, $this->userA, 'Notif Bravo']] as [$company, $user, $title]) {
        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => $company->id,
            'type' => 'test',
            'notifiable_type' => 'user',
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => $title]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($this->userA)
        ->get('/app/subcontractors')
        ->assertOk()
        ->assertSee('Notif Alpha')
        ->assertDontSee('Notif Bravo')
        ->assertSee('Notifications (1)');
});

it('n\'affiche que les compteurs de l\'entreprise dans la barre latérale', function () {
    $this->actingAs($this->userA)
        ->get('/app/subcontractors')
        ->assertOk()
        ->assertSee('(1 utilisés)')
        // Le document en attente de Bravo ne doit pas apparaître dans le badge « Documents à valider »
        ->assertDontSee('bg-rose-500 text-white rounded-full', false);
});

it('enregistre un nouveau sous-traitant dans l\'entreprise et redirige vers sa fiche', function () {
    app(CurrentCompany::class)->set($this->companyA->id);

    Livewire::actingAs($this->userA)
        ->test(SubcontractorsForm::class)
        ->set('legal_name', 'Nouveau Sous-Traitant')
        ->set('siret', '12345678901234')
        ->set('activity', 'Électricité')
        ->set('first_name', 'Jean')
        ->set('last_name', 'Valjean')
        ->set('email', 'jean.valjean@example.com')
        ->set('postal_code', '75001')
        ->set('city', 'Paris')
        ->set('country', 'FR')
        ->call('save')
        ->assertHasNoErrors();

    $newSubcontractor = Subcontractor::where('legal_name', 'Nouveau Sous-Traitant')->first();
    expect($newSubcontractor)->not->toBeNull()
        ->and($newSubcontractor->company_id)->toBe($this->companyA->id)
        ->and($newSubcontractor->registration_number)->toBe('12345678901234');

    $this->actingAs($this->userA)
        ->get(route('subcontractors.show', $newSubcontractor->id))
        ->assertOk()
        ->assertSee('Nouveau Sous-Traitant');
});

it('met à jour un sous-traitant existant', function () {
    app(CurrentCompany::class)->set($this->companyA->id);

    Livewire::actingAs($this->userA)
        ->test(SubcontractorsForm::class, ['id' => $this->ownA->id])
        ->assertSet('legal_name', 'Plomberie Alpha')
        ->set('legal_name', 'Plomberie Alpha Modifiée')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('subcontractors.show', $this->ownA->id));

    expect($this->ownA->fresh()->legal_name)->toBe('Plomberie Alpha Modifiée');
});

it('interdit l\'accès au formulaire de modification d\'un sous-traitant étranger', function () {
    $this->actingAs($this->userA)
        ->get("/app/subcontractors/{$this->foreignB->id}/edit")
        ->assertNotFound();
});

