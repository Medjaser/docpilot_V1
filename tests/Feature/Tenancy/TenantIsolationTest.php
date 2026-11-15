<?php

/*
 * Test de sécurité n°1 du cadrage (§19.3) :
 * un utilisateur de l'entreprise A ne voit JAMAIS les données de l'entreprise B.
 * Ajoutez chaque nouveau modèle métier au dataset « tenant models ».
 */

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateItem;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\RequestedDocument;
use App\Models\Subcontractor;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(DemoSeeder::class);

    $this->batinova = Company::where('name', 'BatiNova Rénovation')->sole();
    $this->concurrent = Company::where('name', 'Concurrent BTP')->sole();
    $this->sophie = User::where('email', 'sophie@batinova.fr')->sole();
    $this->thomas = User::where('email', 'thomas.leroy@concurrent-btp.fr')->sole();

    // Mêmes middlewares que le groupe /app : liaison implicite des modèles + entreprise courante
    Route::middleware(['web', 'company'])->prefix('app/_isolation')->group(function () {
        Route::get('subcontractors/{subcontractor}', fn (Subcontractor $subcontractor) => $subcontractor->legal_name);
        Route::get('documents/{document}', fn (Document $document) => (string) $document->id);
    });
});

dataset('tenant models', [
    Subcontractor::class,
    Document::class,
    DocumentVersion::class,
    DocumentComment::class,
    DocumentType::class,
    DocumentTemplate::class,
    DocumentTemplateItem::class,
    DocumentRequest::class,
    RequestedDocument::class,
    UserInvitation::class,
    AuditLog::class,
]);

it('ne montre que les lignes de BatiNova pour chaque modèle métier', function (string $model) {
    app(CurrentCompany::class)->set($this->batinova->id);

    $expected = DB::table((new $model)->getTable())->where('company_id', $this->batinova->id)->count();

    expect($expected)->toBeGreaterThan(0)
        ->and($model::count())->toBe($expected)
        ->and($model::distinct()->pluck('company_id')->all())->toBe([$this->batinova->id]);
})->with('tenant models');

it('ne montre aucun sous-traitant ni document de Concurrent BTP à un utilisateur de BatiNova', function () {
    app(CurrentCompany::class)->set($this->sophie->company_id);

    $concurrentNames = ['Alpes Électricité', 'Plomberie Dauphiné', 'Isère Peinture'];

    expect(Subcontractor::count())->toBe(26)
        ->and(Subcontractor::whereIn('legal_name', $concurrentNames)->exists())->toBeFalse()
        ->and(Document::where('company_id', $this->concurrent->id)->exists())->toBeFalse()
        ->and(Document::whereHas('subcontractor', fn ($q) => $q->whereIn('legal_name', $concurrentNames))->exists())->toBeFalse();
});

it('renvoie 404 quand un utilisateur de BatiNova ouvre un sous-traitant de Concurrent BTP par son ID', function () {
    $foreign = Subcontractor::acrossAllCompanies()->where('company_id', $this->concurrent->id)->firstOrFail();
    $own = Subcontractor::acrossAllCompanies()->where('legal_name', 'ÉlecPro Services')->firstOrFail();

    $this->actingAs($this->sophie)
        ->get("/app/_isolation/subcontractors/{$foreign->id}")
        ->assertNotFound();

    $this->actingAs($this->sophie)
        ->get("/app/_isolation/subcontractors/{$own->id}")
        ->assertOk()
        ->assertSee('ÉlecPro Services');
});

it('renvoie 404 quand un utilisateur de BatiNova ouvre un document de Concurrent BTP par son ID', function () {
    $foreign = Document::acrossAllCompanies()->where('company_id', $this->concurrent->id)->firstOrFail();

    $this->actingAs($this->sophie)
        ->get("/app/_isolation/documents/{$foreign->id}")
        ->assertNotFound();
});

it("isole aussi dans l'autre sens : Concurrent BTP ne voit pas ÉlecPro Services", function () {
    $elecpro = Subcontractor::acrossAllCompanies()->where('legal_name', 'ÉlecPro Services')->firstOrFail();

    $this->actingAs($this->thomas)
        ->get("/app/_isolation/subcontractors/{$elecpro->id}")
        ->assertNotFound();
});

it("lève ModelNotFoundException sur findOrFail d'un ID d'une autre entreprise", function () {
    $foreign = Subcontractor::acrossAllCompanies()->where('company_id', $this->concurrent->id)->firstOrFail();

    app(CurrentCompany::class)->set($this->batinova->id);

    expect(fn () => Subcontractor::findOrFail($foreign->id))->toThrow(ModelNotFoundException::class);
});

it("ne renvoie aucune donnée d'entreprise quand aucune entreprise courante n'est définie", function () {
    app(CurrentCompany::class)->clear();

    expect(Subcontractor::count())->toBe(0)
        ->and(Document::count())->toBe(0);
});

it("remplit company_id automatiquement avec l'entreprise courante", function () {
    app(CurrentCompany::class)->set($this->concurrent->id);

    $subcontractor = Subcontractor::create(['legal_name' => 'Nouveau Lot', 'contact_email' => 'contact@nouveau-lot.fr']);

    expect($subcontractor->company_id)->toBe($this->concurrent->id);
});

it('applique le middleware company à toutes les routes /app', function () {
    $appRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'app/') && ! str_starts_with($route->uri(), 'app/_isolation'));

    expect($appRoutes)->not->toBeEmpty();

    $appRoutes->each(fn ($route) => expect($route->gatherMiddleware())->toContain('company'));
});
