<?php

use App\Enums\RequestStatus;
use App\Livewire\Admin\Companies\Index as AdminCompaniesIndex;
use App\Livewire\App\Settings\Index as SettingsIndex;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Subcontractor;
use App\Models\User;
use App\Notifications\TrialEndingNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('s3');
    $this->withoutVite();

    $this->company = Company::factory()->create(['name' => 'BatiNova Rénovation', 'registration_number' => '12345678900015']);
    $this->admin = User::factory()->for($this->company)->create();

    Livewire::actingAs($this->admin)
        ->test(SettingsIndex::class, ['section' => 'company'])
        ->set('logo', UploadedFile::fake()->image('logo.png', 120, 120))
        ->call('saveCompany');

    $this->company->refresh();
});

it('sert le logo par son UUID avec les bons en-têtes', function () {
    expect($this->company->logo_uuid)->toBeString()
        ->and(Str::isUuid($this->company->logo_uuid))->toBeTrue()
        ->and($this->company->logoUrl())->toBe(url("/branding/logo/{$this->company->logo_uuid}"));

    $response = $this->get("/branding/logo/{$this->company->logo_uuid}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->getCacheControlDirective('public'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('max-age'))->toBe('86400');
});

it('sert un logo JPG avec le Content-Type image/jpeg', function () {
    Livewire::actingAs($this->admin)
        ->test(SettingsIndex::class, ['section' => 'company'])
        ->set('logo', UploadedFile::fake()->image('logo.jpg', 120, 120))
        ->call('saveCompany');

    $this->get($this->company->fresh()->logoUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('ne permet plus d\'énumérer les entreprises par identifiant', function () {
    $this->get("/branding/companies/{$this->company->id}/logo")->assertNotFound();
    $this->get("/branding/logo/{$this->company->id}")->assertNotFound();
});

it('renvoie 404 pour un UUID inconnu ou mal formé', function (string $uuid) {
    $this->get("/branding/logo/{$uuid}")->assertNotFound();
})->with([
    'uuid inconnu' => fn () => (string) Str::uuid(),
    'pas un uuid' => 'abc',
    'injection' => "1' OR '1'='1",
]);

it('renvoie 404 pour l\'ancien UUID après remplacement ou suppression du logo', function () {
    $oldUuid = $this->company->logo_uuid;

    Livewire::actingAs($this->admin)
        ->test(SettingsIndex::class, ['section' => 'company'])
        ->set('logo', UploadedFile::fake()->image('nouveau.png', 120, 120))
        ->call('saveCompany');
    $newUuid = $this->company->fresh()->logo_uuid;

    expect($newUuid)->not->toBe($oldUuid);
    $this->get("/branding/logo/{$oldUuid}")->assertNotFound();
    $this->get("/branding/logo/{$newUuid}")->assertOk();

    Livewire::actingAs($this->admin)
        ->test(SettingsIndex::class, ['section' => 'company'])
        ->call('removeLogo');

    $this->get("/branding/logo/{$newUuid}")->assertNotFound();
});

it('utilise l\'URL par UUID dans la sidebar', function () {
    $this->actingAs($this->admin)
        ->get('/app/settings/company')
        ->assertOk()
        ->assertSee($this->company->logoUrl(), false)
        ->assertDontSee('/branding/companies/', false);
});

it('utilise l\'URL par UUID sur le portail', function () {
    $subcontractor = Subcontractor::factory()->for($this->company)->create();
    $request = new DocumentRequest([
        'subcontractor_id' => $subcontractor->id,
        'status' => RequestStatus::Sent,
        'expires_at' => now()->addDays(10),
    ]);
    $request->company_id = $this->company->id;
    $token = $request->issueToken();
    $request->save();

    $this->get("/depot/{$token}")
        ->assertOk()
        ->assertSee($this->company->logoUrl(), false)
        ->assertDontSee('/branding/companies/', false);
});

it('utilise l\'URL par UUID dans l\'admin plateforme', function () {
    Livewire::actingAs(User::factory()->platformAdmin()->create())
        ->test(AdminCompaniesIndex::class)
        ->assertSee($this->company->logoUrl(), false)
        ->assertDontSee('/branding/companies/', false);
});

it('affiche le logo par UUID dans les e-mails', function () {
    $html = (string) (new TrialEndingNotification($this->company, 3))->toMail($this->admin)->render();

    expect($html)->toContain($this->company->logoUrl())
        ->not->toContain('/branding/companies/')
        ->toContain('se termine dans 3 jours');
});

it('affiche le nom de l\'entreprise dans l\'e-mail quand il n\'y a pas de logo', function () {
    $company = Company::factory()->create(['name' => 'Sans Logo SARL']);
    $html = (string) (new TrialEndingNotification($company, 0))->toMail($this->admin)->render();

    expect($html)->toContain('Sans Logo SARL')->not->toContain('/branding/logo/');
});
