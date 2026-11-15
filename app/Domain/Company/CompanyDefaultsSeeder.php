<?php

namespace App\Domain\Company;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Support\Tenancy\CurrentCompany;

class CompanyDefaultsSeeder
{
    public const DOCUMENT_TYPES = [
        'kbis' => ["Attestation d'immatriculation (Kbis)", true, true, 3, [30, 7], ['pdf', 'jpg', 'png'], 10],
        'urssaf' => ['Attestation de vigilance Urssaf', true, true, 6, [60, 30, 7], ['pdf'], 10],
        'decennale' => ["Attestation d'assurance décennale", true, true, 12, [60, 30, 15, 7], ['pdf'], 15],
        'rib' => ['RIB Entreprise', false, false, null, [], ['pdf', 'jpg', 'png'], 5],
        'fiscale' => ['Attestation de régularité fiscale', true, true, 6, [30, 7], ['pdf'], 10],
        'salaries' => ['Liste des salariés étrangers', true, true, 6, [30, 7], ['pdf'], 10],
        'rc_pro' => ['Assurance responsabilité civile professionnelle', true, true, 12, [60, 30, 15, 7], ['pdf'], 10],
        'habilitation' => ['Habilitation électrique', true, true, 36, [60, 30], ['pdf', 'jpg', 'png'], 10],
    ];

    public const TEMPLATES = [
        'elec' => [
            'Sous-traitant électricité',
            "Modèle complet recommandé pour les entreprises de travaux électriques et d'automatismes.",
            ['kbis', 'urssaf', 'decennale', 'fiscale', 'salaries', 'rc_pro', 'habilitation'],
        ],
        'plomb' => [
            'Sous-traitant plomberie',
            'Dossier standard pour les lots plomberie, chauffage et sanitaires.',
            ['kbis', 'urssaf', 'decennale', 'fiscale', 'salaries', 'rc_pro'],
        ],
        'peinture' => [
            'Sous-traitant peinture & second œuvre',
            'Dossier allégé pour les travaux de finition et peinture.',
            ['kbis', 'urssaf', 'decennale', 'rc_pro', 'rib'],
        ],
    ];

    public static function seedDefaultsForCompany(int $companyId): void
    {
        $tenant = app(CurrentCompany::class);
        $previousTenantId = $tenant->has() ? $tenant->id() : null;
        $tenant->set($companyId);

        try {
            if (DocumentType::count() === 0) {
                $types = [];
                foreach (self::DOCUMENT_TYPES as $key => [$name, $mandatory, $expires, $validity, $reminders, $formats, $maxMb]) {
                    $types[$key] = DocumentType::create([
                        'name' => $name,
                        'is_mandatory' => $mandatory,
                        'has_expiration_date' => $expires,
                        'default_validity_months' => $validity,
                        'reminder_days' => $reminders,
                        'allowed_extensions' => $formats,
                        'max_file_size_mb' => $maxMb,
                        'is_active' => true,
                    ]);
                }

                if (DocumentTemplate::count() === 0) {
                    foreach (self::TEMPLATES as [$name, $description, $typeKeys]) {
                        $template = DocumentTemplate::create([
                            'name' => $name,
                            'description' => $description,
                            'is_active' => true,
                        ]);

                        foreach ($typeKeys as $position => $key) {
                            if (isset($types[$key])) {
                                $template->items()->create([
                                    'document_type_id' => $types[$key]->id,
                                    'is_required' => $types[$key]->is_mandatory,
                                    'position' => $position,
                                ]);
                            }
                        }
                    }
                }
            }
        } finally {
            if ($previousTenantId !== null) {
                $tenant->set($previousTenantId);
            } else {
                $tenant->clear();
            }
        }
    }
}
