<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\Subcontractor;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test de sécurité n°1 du cadrage (§19.3) :
 * un utilisateur de l'entreprise A ne voit JAMAIS les données de l'entreprise B.
 * Ajoutez un test de ce type pour chaque nouvelle ressource (documents, exports, liens...).
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_are_scoped_to_the_current_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Subcontractor::factory()->for($companyA)->create(['legal_name' => 'ÉlecPro A']);
        Subcontractor::factory()->for($companyB)->create(['legal_name' => 'Plomberie B']);

        app(CurrentCompany::class)->set($companyA->id);

        $names = Subcontractor::pluck('legal_name')->all();

        $this->assertSame(['ÉlecPro A'], $names);
    }

    public function test_company_id_is_filled_automatically_on_creation(): void
    {
        $company = Company::factory()->create();
        app(CurrentCompany::class)->set($company->id);

        $sub = Subcontractor::create(['legal_name' => 'Peinture Sud', 'contact_email' => 'contact@peinture-sud.fr']);

        $this->assertSame($company->id, $sub->company_id);
    }

    public function test_record_of_another_company_cannot_be_found_by_id(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $foreign = Subcontractor::factory()->for($companyB)->create();

        app(CurrentCompany::class)->set($companyA->id);

        $this->assertNull(Subcontractor::find($foreign->id));
    }
}
