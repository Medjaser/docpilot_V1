<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DatabaseNotification;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateItem;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\NotificationLog;
use App\Models\RequestedDocument;
use App\Models\Subcontractor;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->companyA = Company::where('name', 'BatiNova Rénovation')->sole();
    $this->companyB = Company::where('name', 'Concurrent BTP')->sole();
    $this->adminA = User::where('email', 'sophie@batinova.fr')->sole();
    $this->adminB = User::where('email', 'thomas.leroy@concurrent-btp.fr')->sole();

    foreach ([[$this->companyA, $this->adminA], [$this->companyB, $this->adminB]] as [$company, $admin]) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'type' => 'tenant.test',
            'notifiable_type' => 'user',
            'notifiable_id' => $admin->id,
            'data' => json_encode(['title' => 'Test tenant']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('notification_logs')->insert([
            'company_id' => $company->id,
            'channel' => 'mail',
            'type' => 'tenant.test',
            'recipient' => $admin->email,
            'dedup_key' => "tenant-test:{$company->id}",
            'sent_at' => now(),
            'created_at' => now(),
        ]);
        DB::table('user_invitations')->insert([
            'company_id' => $company->id,
            'email' => "invite-{$company->id}@example.test",
            'role' => Role::Manager->value,
            'token_hash' => hash('sha256', Str::random(48)),
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
});

dataset('strict tenant tables', [
    'subcontractors',
    'user_invitations',
    'document_types',
    'document_templates',
    'document_template_items',
    'documents',
    'document_versions',
    'document_comments',
    'document_requests',
    'requested_documents',
    'notifications',
    'notification_logs',
]);

dataset('tenant model classes', [
    Subcontractor::class,
    UserInvitation::class,
    DocumentType::class,
    DocumentTemplate::class,
    DocumentTemplateItem::class,
    Document::class,
    DocumentVersion::class,
    DocumentComment::class,
    DocumentRequest::class,
    RequestedDocument::class,
    DatabaseNotification::class,
    NotificationLog::class,
    AuditLog::class,
]);

it('impose company_id NOT NULL, une FK companies et un index sur chaque table métier tenant', function (string $table) {
    $column = DB::selectOne(
        'select is_nullable from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
        [$table, 'company_id'],
    );
    $foreignKey = DB::selectOne(
        <<<'SQL'
            select 1
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu
              on tc.constraint_name = kcu.constraint_name and tc.table_schema = kcu.table_schema
            join information_schema.constraint_column_usage ccu
              on ccu.constraint_name = tc.constraint_name and ccu.table_schema = tc.table_schema
            where tc.constraint_type = 'FOREIGN KEY'
              and tc.table_schema = current_schema()
              and tc.table_name = ?
              and kcu.column_name = 'company_id'
              and ccu.table_name = 'companies'
            SQL,
        [$table],
    );
    $indexed = collect(DB::select(
        'select indexdef from pg_indexes where schemaname = current_schema() and tablename = ?',
        [$table],
    ))->contains(fn (object $index) => str_contains($index->indexdef, '(company_id')
        || str_contains($index->indexdef, '(company_id,'));

    expect($column)->not->toBeNull()
        ->and($column->is_nullable)->toBe('NO')
        ->and($foreignKey)->not->toBeNull()
        ->and($indexed)->toBeTrue();
})->with('strict tenant tables');

it('documente les deux seules colonnes company_id métier volontairement nullables', function () {
    // users : l'administrateur plateforme n'appartient à aucune entreprise.
    // audit_logs : événements plateforme et échecs de connexion avant résolution du tenant.
    foreach (['users', 'audit_logs'] as $table) {
        $column = DB::selectOne(
            'select is_nullable from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
            [$table, 'company_id'],
        );

        expect($column->is_nullable)->toBe('YES');
    }
});

it('fait utiliser BelongsToCompany à chaque modèle métier tenant', function (string $model) {
    expect(class_uses_recursive($model))->toHaveKey(BelongsToCompany::class);
})->with('tenant model classes');

it('fournit une factory à chaque modèle métier', function (string $model) {
    expect(method_exists($model, 'factory'))->toBeTrue();
})->with('tenant model classes');

it('applique SetCurrentCompany à toutes les routes /app et platform.admin à toutes les routes /admin', function () {
    $appRoutes = collect(Route::getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'app/'));
    $adminRoutes = collect(Route::getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'admin/'));

    expect($appRoutes)->not->toBeEmpty()
        ->and($adminRoutes)->not->toBeEmpty();

    $appRoutes->each(fn ($route) => expect($route->gatherMiddleware())->toContain('company'));
    $adminRoutes->each(fn ($route) => expect($route->gatherMiddleware())->toContain('platform.admin'));
});

it('isole les requêtes Eloquent dans les deux sens pour tous les modèles alimentés', function (string $model) {
    $table = (new $model)->getTable();
    $countA = DB::table($table)->where('company_id', $this->companyA->id)->count();
    $countB = DB::table($table)->where('company_id', $this->companyB->id)->count();

    expect($countA)->toBeGreaterThan(0)
        ->and($countB)->toBeGreaterThan(0);

    app(CurrentCompany::class)->set($this->companyA->id);
    expect($model::query()->distinct()->pluck('company_id')->all())->toBe([$this->companyA->id]);

    app(CurrentCompany::class)->set($this->companyB->id);
    expect($model::query()->distinct()->pluck('company_id')->all())->toBe([$this->companyB->id]);
})->with('tenant model classes');

it('refuse par policy toutes les ressources d une autre entreprise', function () {
    $foreignSubcontractor = Subcontractor::acrossAllCompanies()
        ->where('company_id', $this->companyB->id)->firstOrFail();
    $foreignDocument = Document::acrossAllCompanies()
        ->where('company_id', $this->companyB->id)->firstOrFail();
    $foreignType = DocumentType::acrossAllCompanies()
        ->where('company_id', $this->companyB->id)->firstOrFail();
    $foreignTemplate = DocumentTemplate::acrossAllCompanies()
        ->where('company_id', $this->companyB->id)->firstOrFail();
    $foreignRequest = DocumentRequest::acrossAllCompanies()
        ->where('company_id', $this->companyB->id)->firstOrFail();

    Gate::forUser($this->adminA);

    expect($this->adminA->can('view', $foreignSubcontractor))->toBeFalse()
        ->and($this->adminA->can('view', $foreignDocument))->toBeFalse()
        ->and($this->adminA->can('view', $foreignType))->toBeFalse()
        ->and($this->adminA->can('view', $foreignTemplate))->toBeFalse()
        ->and($this->adminA->can('view', $foreignRequest))->toBeFalse()
        ->and($this->adminA->can('update', $this->adminB))->toBeFalse();
});

it('verrouille les identifiants publics des composants Livewire', function () {
    $properties = [
        [App\Livewire\App\Subcontractors\Form::class, 'subcontractorId'],
        [App\Livewire\App\Subcontractors\Show::class, 'id'],
        [App\Livewire\App\Documents\Review::class, 'id'],
        [App\Livewire\App\Documents\Review::class, 'subcontractor_id'],
        [App\Livewire\App\DocumentTypes\Index::class, 'editingId'],
        [App\Livewire\App\Templates\Index::class, 'editingId'],
        [App\Livewire\Admin\Companies\Index::class, 'statusCompanyId'],
        [App\Livewire\Portal\Upload::class, 'token'],
        [App\Livewire\Auth\AcceptInvitation::class, 'token'],
        [App\Livewire\Auth\ResetPassword::class, 'token'],
    ];

    foreach ($properties as [$component, $property]) {
        $attributes = (new ReflectionProperty($component, $property))->getAttributes(Locked::class);
        expect($attributes)->not->toBeEmpty("{$component}::\${$property} doit être #[Locked].");
    }
});

it('interdit /admin à un utilisateur client et /app à un administrateur plateforme', function () {
    $this->actingAs($this->adminA)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs(User::factory()->platformAdmin()->create())
        ->get('/app/dashboard')
        ->assertRedirect(route('admin.dashboard'));
});

it('ne permet pas de modifier le rôle d un utilisateur d une autre entreprise', function () {
    Livewire\Livewire::actingAs($this->adminA)
        ->test(App\Livewire\App\Users\Index::class)
        ->call('updateRole', $this->adminB->id, Role::Viewer->value)
        ->assertNotFound();
});
