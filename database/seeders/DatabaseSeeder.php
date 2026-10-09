<?php

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Enums\DocumentStatus;
use App\Enums\SubcontractorStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Subcontractor;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration reprenant l'exemple du cadrage : BatiNova Rénovation.
 * Comptes (mot de passe : "password") :
 *   admin@docpilot.local        → administrateur plateforme AD CLOUD IT
 *   sophie@batinova.local       → administratrice BatiNova
 *   marc@batinova.local         → gestionnaire
 *   julien@batinova.local       → lecteur (conducteur de travaux)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'company_id' => null,
            'first_name' => 'Admin',
            'last_name' => 'AD CLOUD IT',
            'email' => 'admin@docpilot.local',
            'role' => UserRole::PlatformAdmin,
        ]);

        $batinova = Company::create([
            'name' => 'BatiNova Rénovation',
            'email' => 'contact@batinova.local',
            'city' => 'Montpellier',
            'country' => 'FR',
            'sector' => 'BTP — Rénovation',
            'status' => CompanyStatus::Active,
            'subscription_plan' => 'business',
        ]);

        app(CurrentCompany::class)->set($batinova->id);

        $sophie = User::factory()->for($batinova)->create([
            'first_name' => 'Sophie', 'last_name' => 'Martin', 'email' => 'sophie@batinova.local', 'role' => UserRole::Admin,
        ]);
        User::factory()->for($batinova)->create([
            'first_name' => 'Marc', 'last_name' => 'Durand', 'email' => 'marc@batinova.local', 'role' => UserRole::Manager,
        ]);
        User::factory()->for($batinova)->create([
            'first_name' => 'Julien', 'last_name' => 'Petit', 'email' => 'julien@batinova.local', 'role' => UserRole::Reader,
        ]);

        // Catégories de documents (§9.6) : [nom, obligatoire, expiration, validité en mois]
        $types = collect([
            ['Extrait Kbis', true, true, 3],
            ['Attestation de vigilance', true, true, 6],
            ['Assurance RC professionnelle', true, true, 12],
            ['Assurance décennale', true, true, 12],
            ['Habilitation électrique', true, true, 36],
            ['RIB', true, false, null],
            ['Contrat de sous-traitance', true, false, null],
            ['Pièce d\'identité du dirigeant', false, true, 120],
        ])->mapWithKeys(fn ($t) => [$t[0] => DocumentType::create([
            'name' => $t[0],
            'is_mandatory' => $t[1],
            'has_expiration_date' => $t[2],
            'default_validity_months' => $t[3],
            'reminder_days' => [60, 30, 15, 7, 0],
            'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
            'max_file_size_kb' => 10240,
        ])]);

        // Modèle « Sous-traitant électricité » (§9.7)
        $template = DocumentTemplate::create(['name' => 'Sous-traitant électricité']);
        foreach ($types->values() as $i => $type) {
            $template->items()->create([
                'document_type_id' => $type->id,
                'is_required' => $type->is_mandatory,
                'position' => $i,
            ]);
        }

        $elecpro = Subcontractor::create([
            'legal_name' => 'ÉlecPro Services',
            'activity' => 'Électricité générale',
            'contact_first_name' => 'Karim',
            'contact_last_name' => 'Benali',
            'contact_email' => 'contact@elecpro.local',
            'city' => 'Montpellier',
            'internal_owner_id' => $sophie->id,
            'start_date' => '2026-08-01',
            'status' => SubcontractorStatus::AwaitingDocuments,
            'notes' => 'Intervient sur les chantiers de Montpellier et Nîmes.',
        ]);

        // Application du modèle : génère la liste des documents attendus
        foreach ($template->items as $item) {
            Document::create([
                'subcontractor_id' => $elecpro->id,
                'document_type_id' => $item->document_type_id,
                'is_required' => $item->is_required,
                'status' => DocumentStatus::Missing,
            ]);
        }

        foreach (['Plomberie Martin', 'Peinture Sud', 'Isolation Plus'] as $name) {
            Subcontractor::factory()->create(['company_id' => $batinova->id, 'legal_name' => $name]);
        }

        app(CurrentCompany::class)->clear();
    }
}
