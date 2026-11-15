<?php

namespace Database\Seeders;

use App\Domain\Documents\CompletenessScore;
use App\Enums\CompanyStatus;
use App\Enums\DocumentStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\SubcontractorStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Subcontractor;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Données de démonstration reprenant les maquettes de l'interface (BatiNova Rénovation)
 * + une entreprise « Concurrent BTP » pour vérifier l'isolation entre entreprises.
 *
 * Comptes (mot de passe : "password") :
 *   admin@docpilot.local            administrateur plateforme AD CLOUD IT
 *   sophie@batinova.fr              administratrice BatiNova
 *   marc.dubois@batinova.fr         gestionnaire BatiNova
 *   julien.moreau@batinova.fr       lecteur BatiNova
 *   thomas.leroy@concurrent-btp.fr  administrateur Concurrent BTP
 *
 * Les dates d'expiration sont relatives au jour du seed pour que les statuts
 * (valide, bientôt expiré, expiré) restent ceux des maquettes. Les événements datés
 * des maquettes sont décalés d'autant que leur « aujourd'hui » (MOCK_TODAY).
 */
class DemoSeeder extends Seeder
{
    private const MOCK_TODAY = '2026-10-09';

    /** [nom, obligatoire, avec expiration, validité (mois), relances (J-x), formats, taille max (Mo)] */
    private const DOCUMENT_TYPES = [
        'kbis' => ["Attestation d'immatriculation (Kbis)", true, true, 3, [30, 7], ['pdf', 'jpg', 'png'], 10],
        'urssaf' => ['Attestation de vigilance Urssaf', true, true, 6, [60, 30, 7], ['pdf'], 10],
        'decennale' => ["Attestation d'assurance décennale", true, true, 12, [60, 30, 15, 7], ['pdf'], 15],
        'rib' => ['RIB Entreprise', false, false, null, [], ['pdf', 'jpg', 'png'], 5],
        'fiscale' => ['Attestation de régularité fiscale', true, true, 6, [30, 7], ['pdf'], 10],
        'salaries' => ['Liste des salariés étrangers', true, true, 6, [30, 7], ['pdf'], 10],
        'rc_pro' => ['Assurance responsabilité civile professionnelle', true, true, 12, [60, 30, 15, 7], ['pdf'], 10],
        'habilitation' => ['Habilitation électrique', true, true, 36, [60, 30], ['pdf', 'jpg', 'png'], 10],
    ];

    /** [nom, description, catégories] */
    private const TEMPLATES = [
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

    /**
     * Sous-traitants BatiNova complétant les 6 des maquettes (25 actifs au total, comme le tableau de bord).
     * [raison sociale, activité, ville, modèle, profil, prénom contact, nom contact]
     */
    private const EXTRA_SUBCONTRACTORS = [
        ['Électricité Confluence', 'Électricité générale', 'Lyon', 'elec', 'complete', 'Nadia', 'Haddad'],
        ["Volt'Est Énergies", 'Électricité industrielle', 'Vaulx-en-Velin', 'elec', 'complete', 'Olivier', 'Garnier'],
        ['Lumen Câblage', 'Courants faibles', 'Écully', 'elec', 'complete', 'Sarah', 'Fontaine'],
        ['Domotique Saône', 'Domotique & automatismes', 'Caluire-et-Cuire', 'elec', 'complete', 'Hugo', 'Chevalier'],
        ['Ampère Rénovation', 'Électricité générale', 'Oullins', 'elec', 'complete', 'Camille', 'Perrin'],
        ['Rhône Électricité Services', 'Électricité générale', 'Décines-Charpieu', 'elec', 'complete', 'Mehdi', 'Ait Ali'],
        ['Thermo Chauffage Lyonnais', 'Chauffage / Climatisation', 'Lyon', 'plomb', 'complete', 'Laurent', 'Blanc'],
        ['Aqua Services 69', 'Plomberie / Sanitaire', 'Villeurbanne', 'plomb', 'complete', 'Émilie', 'Robin'],
        ['Sanitaires du Beaujolais', 'Plomberie / Sanitaire', 'Villefranche-sur-Saône', 'plomb', 'complete', 'Bruno', 'Masson'],
        ['Climat Confort Est', 'Climatisation', 'Meyzieu', 'plomb', 'complete', 'Inès', 'Mercier'],
        ['Hydro Pro Bron', 'Plomberie / Sanitaire', 'Bron', 'plomb', 'complete', 'Yann', 'Faure'],
        ['Atelier Couleurs & Murs', 'Peinture & Finitions', 'Lyon', 'peinture', 'complete', 'Chloé', 'André'],
        ['Plâtrerie Moderne Rhône', 'Plâtrerie / Cloisons', 'Vénissieux', 'peinture', 'complete', 'Rachid', 'Belkacem'],
        ['Sols & Revêtements Lumière', 'Revêtements de sols', 'Tassin-la-Demi-Lune', 'peinture', 'complete', 'Manon', 'Girard'],
        ['Menuiserie Croix-Rousse', 'Menuiserie intérieure', 'Lyon', 'peinture', 'complete', 'Fabrice', 'Bonnet'],
        ['Électro Fourvière', 'Électricité générale', 'Lyon', 'elec', 'incomplete', 'Damien', 'Lefèvre'],
        ['Câbles & Réseaux Givors', 'Réseaux & fibre', 'Givors', 'elec', 'incomplete', 'Aurélie', 'Roche'],
        ['Plomberie Gerland', 'Plomberie / Sanitaire', 'Lyon', 'plomb', 'incomplete', 'Kevin', 'Marchand'],
        ['Watt Isère Électricité', 'Électricité générale', 'Vienne', 'elec', 'critical', 'Stéphane', 'Gauthier'],
        ['Chauffage Express Sud', 'Chauffage', 'Saint-Fons', 'plomb', 'critical', 'Julie', 'Lemoine'],
    ];

    private CarbonImmutable $today;

    private int $shiftDays;

    private CurrentCompany $tenant;

    private CompletenessScore $completeness;

    /** Valideur par défaut des documents de l'entreprise en cours de seed. */
    private User $validator;

    public function run(): void
    {
        $this->today = CarbonImmutable::today();
        $this->shiftDays = (int) round(CarbonImmutable::parse(self::MOCK_TODAY)->diffInDays($this->today, false));
        $this->tenant = app(CurrentCompany::class);
        $this->completeness = new CompletenessScore;

        User::factory()->platformAdmin()->create([
            'first_name' => 'Admin',
            'last_name' => 'AD CLOUD IT',
            'email' => 'admin@docpilot.local',
            'role' => Role::Admin,
        ]);

        $this->seedBatiNova();
        $this->seedConcurrentBtp();

        $this->tenant->clear();
    }

    private function seedBatiNova(): void
    {
        $company = Company::create([
            'name' => 'BatiNova Rénovation',
            'trade_name' => 'BatiNova',
            'registration_number' => '12345678900015',
            'sector' => 'BTP — Rénovation',
            'email' => 'contact@batinova.fr',
            'phone' => '04 78 00 11 22',
            'address' => "14 rue de l'Industrie",
            'postal_code' => '69007',
            'city' => 'Lyon',
            'country' => 'FR',
            'status' => CompanyStatus::Trial,
            'subscription_plan' => 'essentiel',
            'trial_ends_at' => $this->today->addDays(9),
            'settings' => [
                'notifications' => [
                    'new_upload' => ['email' => true, 'app' => true, 'recipients' => 'owner'],
                    'doc_validated' => ['email' => false, 'app' => true, 'recipients' => 'owner'],
                    'doc_rejected' => ['email' => true, 'app' => true, 'recipients' => 'owner'],
                    'doc_expiring' => ['email' => true, 'app' => true, 'recipients' => 'owner'],
                    'doc_expired' => ['email' => true, 'app' => true, 'recipients' => 'managers'],
                ],
                'expiring_soon_days' => 30,
                'reminder_days' => [60, 30, 15, 7, 0],
                'request_email' => [
                    'subject' => 'Documents administratifs requis pour {nom_entreprise}',
                    'body' => "Bonjour {nom_contact},\n\nDans le cadre de nos interventions sur chantier, nous vous prions de bien vouloir mettre à jour les documents suivants :\n\n{documents_manquants}\n\nLien de dépôt sécurisé : {lien_depot}\nDate limite : {date_limite}\n\nCordialement,\n{coordonnees_gestionnaire}",
                ],
            ],
        ]);

        $this->tenant->set($company->id);

        $sophie = $this->createUser($company, 'Sophie', 'Martin', 'sophie@batinova.fr', Role::Admin, $this->at('2026-10-09 14:15'));
        $marc = $this->createUser($company, 'Marc', 'Dubois', 'marc.dubois@batinova.fr', Role::Manager, $this->at('2026-10-08 09:30'));
        $julien = $this->createUser($company, 'Julien', 'Moreau', 'julien.moreau@batinova.fr', Role::Viewer, $this->at('2026-10-06 17:00'));
        $this->validator = $sophie;

        $invitation = new UserInvitation([
            'email' => 'claire.dupont@batinova.fr',
            'role' => Role::Manager,
            'invited_by' => $sophie->id,
            'expires_at' => $this->days(7),
        ]);
        $invitation->issueToken();
        $invitation->save();

        $types = $this->createDocumentTypes(array_keys(self::DOCUMENT_TYPES));
        foreach (self::TEMPLATES as [$name, $description, $typeKeys]) {
            $this->createTemplate($name, $description, $typeKeys, $types);
        }

        // --- Les 6 sous-traitants des maquettes (statut et complétude tels qu'affichés) ---

        $elecpro = Subcontractor::create([
            'legal_name' => 'ÉlecPro Services',
            'trade_name' => 'ÉlecPro',
            'type' => 'Sous-traitant direct',
            'activity' => 'Électricité générale',
            'contact_first_name' => 'Karim',
            'contact_last_name' => 'Benali',
            'contact_email' => 'k.benali@elecpro.fr',
            'contact_phone' => '06 12 34 56 78',
            'address' => "14 rue de l'Industrie",
            'postal_code' => '69007',
            'city' => 'Lyon',
            'registration_number' => '12345678900012',
            'status' => SubcontractorStatus::Incomplete,
            'completeness_score' => 71,
            'internal_owner_id' => $sophie->id,
            'start_date' => $this->days(-70),
            'tags' => ['Électricité', 'Lyon', 'Urgent'],
            'notes' => 'Intervient sur le chantier Résidence Les Ormes.',
        ]);

        $this->addDocument($elecpro, $types['kbis'], DocumentStatus::Valid, ['expires' => $this->days(65), 'previous' => DocumentStatus::Replaced]);
        $this->addDocument($elecpro, $types['urssaf'], DocumentStatus::ExpiringSoon, ['expires' => $this->days(7)]);
        $decennale = $this->addDocument($elecpro, $types['decennale'], DocumentStatus::Pending, [
            'expires' => $this->days(83),
            'uploaded' => $this->at('2026-10-08 14:20'),
            'original_name' => 'Assurance décennale 2026.pdf',
            'comment' => "Veuillez trouver ci-joint l'attestation décennale renouvelée pour l'année 2026.",
            'previous' => DocumentStatus::Rejected,
            'previous_uploaded' => $this->days(-12)->setTime(9, 40),
        ]);
        $this->addDocument($elecpro, $types['fiscale'], DocumentStatus::Valid, ['expires' => $this->days(80)]);
        $salaries = $this->addDocument($elecpro, $types['salaries'], DocumentStatus::Missing);
        $this->addDocument($elecpro, $types['rc_pro'], DocumentStatus::Valid, ['expires' => $this->days(200)]);
        $this->addDocument($elecpro, $types['habilitation'], DocumentStatus::Valid, ['expires' => $this->days(500)]);

        $this->addComment($decennale, $sophie, 'Attestation Décennale 2026 rejetée car la mention du lot Électricité était illisible. Demande de renvoi transmise.', $this->days(-10)->setTime(14, 30));
        $this->addComment($decennale, $sophie, "La date d'expiration et la signature de la compagnie sont floues.", $this->days(-10)->setTime(14, 31), DocumentComment::VISIBILITY_EXTERNAL);
        $this->addComment($decennale, $marc, 'Lien de dépôt généré et envoyé à Karim Benali.', $this->days(-9)->setTime(10, 15));

        $this->createRequest($elecpro, $marc, [
            'token' => 'tok_elecpro_987654',
            'message' => 'Bonjour Karim, merci de nous faire parvenir vos attestations à jour pour le chantier Résidence Les Ormes.',
            'sent_at' => $this->days(-9)->setTime(10, 15),
            'due_date' => $this->days(5),
            'expires_at' => $this->days(10)->endOfDay(),
        ], [
            [$decennale, DocumentStatus::Pending],
            [$salaries, DocumentStatus::Requested],
        ]);

        $plomberie = Subcontractor::create([
            'legal_name' => 'Plomberie Martin',
            'type' => 'Sous-traitant direct',
            'activity' => 'Plomberie / Sanitaire',
            'contact_first_name' => 'Jean',
            'contact_last_name' => 'Martin',
            'contact_email' => 'contact@plomberie-martin.fr',
            'city' => 'Villeurbanne',
            'postal_code' => '69100',
            'status' => SubcontractorStatus::Complete,
            'completeness_score' => 100,
            'internal_owner_id' => $marc->id,
        ]);

        $this->addDocument($plomberie, $types['kbis'], DocumentStatus::Valid, ['expires' => $this->days(40), 'validator' => $marc]);
        $plomberieUrssaf = $this->addDocument($plomberie, $types['urssaf'], DocumentStatus::Pending, [
            'expires' => $this->at('2027-01-31'),
            'uploaded' => $this->at('2026-10-07 09:15'),
            'original_name' => 'Attestation Urssaf Q3.pdf',
            'previous' => DocumentStatus::Expired,
        ]);
        $this->addDocument($plomberie, $types['decennale'], DocumentStatus::ExpiringSoon, ['expires' => $this->days(7), 'validator' => $marc]);
        $this->addDocument($plomberie, $types['fiscale'], DocumentStatus::Valid, ['expires' => $this->days(150), 'validator' => $marc]);
        $this->addDocument($plomberie, $types['salaries'], DocumentStatus::Valid, ['expires' => $this->days(90), 'validator' => $marc]);
        $this->addDocument($plomberie, $types['rc_pro'], DocumentStatus::Valid, ['expires' => $this->days(220), 'validator' => $marc]);

        $plomberieRequest = $this->createRequest($plomberie, $marc, [
            'message' => 'Merci de renouveler votre attestation de vigilance Urssaf.',
            'sent_at' => $this->at('2026-09-30 09:00'),
            'last_reminder_at' => $this->at('2026-10-08 09:00'),
            'due_date' => $this->at('2026-10-15'),
            'expires_at' => $this->at('2026-10-31')->endOfDay(),
        ], [
            [$plomberieUrssaf, DocumentStatus::Pending],
        ]);

        $peinture = Subcontractor::create([
            'legal_name' => 'Peinture Sud',
            'type' => 'Sous-traitant direct',
            'activity' => 'Peinture & Finitions',
            'contact_first_name' => 'Lucie',
            'contact_last_name' => 'Bernard',
            'contact_email' => 'l.bernard@peinturesud.fr',
            'city' => 'Bron',
            'postal_code' => '69500',
            'status' => SubcontractorStatus::Complete,
            'completeness_score' => 85,
            'internal_owner_id' => $sophie->id,
        ]);

        $this->addDocument($peinture, $types['kbis'], DocumentStatus::Pending, [
            'expires' => $this->at('2027-01-05'),
            'uploaded' => $this->at('2026-10-06 16:45'),
            'original_name' => 'Extrait Kbis.pdf',
            'previous' => DocumentStatus::Replaced,
        ]);
        $this->addDocument($peinture, $types['urssaf'], DocumentStatus::Valid, ['expires' => $this->days(120)]);
        $this->addDocument($peinture, $types['decennale'], DocumentStatus::Valid, ['expires' => $this->days(250)]);
        $this->addDocument($peinture, $types['rc_pro'], DocumentStatus::Valid, ['expires' => $this->days(180)]);
        $this->addDocument($peinture, $types['rib'], DocumentStatus::Valid);

        $isolation = Subcontractor::create([
            'legal_name' => 'Isolation Plus',
            'type' => 'Sous-traitant direct',
            'activity' => 'Isolation thermique',
            'contact_first_name' => 'Antoine',
            'contact_last_name' => 'Roux',
            'contact_email' => 'a.roux@isolationplus.fr',
            'city' => 'Vénissieux',
            'postal_code' => '69200',
            'status' => SubcontractorStatus::ExpiredDocuments,
            'completeness_score' => 35,
            'internal_owner_id' => $julien->id,
        ]);

        $this->addDocument($isolation, $types['kbis'], DocumentStatus::Valid, ['expires' => $this->days(45)]);
        $this->addDocument($isolation, $types['urssaf'], DocumentStatus::Missing);
        $this->addDocument($isolation, $types['decennale'], DocumentStatus::Expired, ['expires' => $this->days(-29)]);
        $this->addDocument($isolation, $types['fiscale'], DocumentStatus::Pending, [
            'expires' => $this->at('2027-06-30'),
            'uploaded' => $this->at('2026-10-05 11:30'),
            'previous' => DocumentStatus::Expired,
        ]);
        $this->addDocument($isolation, $types['rc_pro'], DocumentStatus::Missing);

        $maconnerie = Subcontractor::create([
            'legal_name' => 'Maçonnerie Dupont',
            'type' => 'Sous-traitant direct',
            'activity' => 'Gros œuvre & Maçonnerie',
            'contact_first_name' => 'Pierre',
            'contact_last_name' => 'Dupont',
            'contact_email' => 'dupont.maconnerie@orange.fr',
            'city' => 'Saint-Priest',
            'postal_code' => '69800',
            'status' => SubcontractorStatus::Incomplete,
            'completeness_score' => 60,
            'internal_owner_id' => $sophie->id,
        ]);

        $this->addDocument($maconnerie, $types['kbis'], DocumentStatus::Valid, ['expires' => $this->days(70)]);
        $this->addDocument($maconnerie, $types['urssaf'], DocumentStatus::Valid, ['expires' => $this->days(100)]);
        $this->addDocument($maconnerie, $types['decennale'], DocumentStatus::Valid, ['expires' => $this->days(300)]);
        $this->addDocument($maconnerie, $types['salaries'], DocumentStatus::Pending, [
            'expires' => $this->at('2026-12-31'),
            'uploaded' => $this->at('2026-10-04 17:10'),
        ]);
        $this->addDocument($maconnerie, $types['rc_pro'], DocumentStatus::Missing);

        $batimentRa = Subcontractor::create([
            'legal_name' => 'Bâtiment Rhône-Alpes',
            'type' => 'Sous-traitant direct',
            'activity' => 'Charpente & Couverture',
            'contact_first_name' => 'Michel',
            'contact_last_name' => 'Lambert',
            'contact_email' => 'contact@batiment-ra.fr',
            'city' => 'Lyon',
            'postal_code' => '69003',
            'status' => SubcontractorStatus::Archived,
            'completeness_score' => 90,
            'internal_owner_id' => $marc->id,
            'archived_at' => $this->days(-30),
        ]);

        $this->addDocument($batimentRa, $types['kbis'], DocumentStatus::Valid, ['expires' => $this->days(50), 'validator' => $marc]);
        $this->addDocument($batimentRa, $types['urssaf'], DocumentStatus::Valid, ['expires' => $this->days(80), 'validator' => $marc]);
        $this->addDocument($batimentRa, $types['decennale'], DocumentStatus::Valid, ['expires' => $this->days(200), 'validator' => $marc]);
        $this->addDocument($batimentRa, $types['rc_pro'], DocumentStatus::Valid, ['expires' => $this->days(54), 'validator' => $marc]);
        $this->addDocument($batimentRa, $types['rib'], DocumentStatus::Pending, ['uploaded' => $this->at('2026-10-03 08:50')]);

        // --- Les autres sous-traitants actifs ---

        $owners = [$sophie, $marc, $julien];
        foreach (self::EXTRA_SUBCONTRACTORS as $i => [$name, $activity, $city, $template, $profile, $firstName, $lastName]) {
            $subcontractor = Subcontractor::create([
                'legal_name' => $name,
                'type' => 'Sous-traitant direct',
                'activity' => $activity,
                'contact_first_name' => $firstName,
                'contact_last_name' => $lastName,
                'contact_email' => 'contact@'.Str::slug($name).'.fr',
                'contact_phone' => sprintf('04 72 %02d %02d %02d', 10 + $i, 30 + $i, 50 + $i),
                'city' => $city,
                'registration_number' => sprintf('%09d%05d', 412000000 + $i * 1371, 17 + $i),
                'status' => SubcontractorStatus::Draft,
                'internal_owner_id' => $owners[$i % 3]->id,
                'start_date' => $this->days(-400 + $i * 9),
            ]);

            $typeKeys = self::TEMPLATES[$template][2];
            $last = count($typeKeys) - 1;
            $statuses = [];

            // Répartition calée sur les compteurs du tableau de bord
            // (132 valides, 6 à valider, 8 bientôt expirés, 4 expirés, 11 manquants)
            foreach ($typeKeys as $j => $key) {
                $statuses[$key] = match ($profile) {
                    'complete' => ($i % 2 === 0 && $i <= 10 && $j === 1) ? DocumentStatus::ExpiringSoon : DocumentStatus::Valid,
                    'incomplete' => match ($j) {
                        $last => DocumentStatus::Missing,
                        $last - 1 => DocumentStatus::Requested,
                        default => DocumentStatus::Valid,
                    },
                    'critical' => match (true) {
                        $j === 1, $j === 3 && $i === 18 => DocumentStatus::Expired,
                        $j === 2 => DocumentStatus::Missing,
                        default => DocumentStatus::Valid,
                    },
                };
            }

            // RIB facultatif (sans effet sur la complétude) pour les premiers sous-traitants électricité
            if ($i < 6) {
                $statuses['rib'] = $i < 4 ? DocumentStatus::Valid : DocumentStatus::Missing;
            }

            $this->seedDocuments($subcontractor, $types, $statuses, $i);
        }

        // --- Journal d'audit et notifications des maquettes ---

        $this->audit($sophie, 'document.validated', $decennale, $this->at('2026-10-09 14:15:33'), '192.168.1.45', [
            'summary' => 'Document validé après contrôle de la date de fin au 31/12/2026.',
        ]);
        $this->audit($elecpro, 'document.uploaded', $decennale, $this->at('2026-10-09 11:20:05'), '82.120.44.12', [
            'summary' => 'Fichier "Assurance_2026.pdf" téléversé via le portail sans compte.',
            'file' => 'Assurance_2026.pdf',
        ]);
        $this->audit($sophie, 'document.rejected', $plomberieUrssaf, $this->at('2026-10-08 16:40:12'), '192.168.1.45', [
            'summary' => 'Motif: Document illisible. Message transmis par e-mail.',
            'reason' => 'illegible',
        ]);
        $this->audit(null, 'request.reminder_sent', $plomberieRequest, $this->at('2026-10-08 09:00:00'), '127.0.0.1', [
            'summary' => 'Relance automatique J-7 envoyée à contact@plomberie-martin.fr.',
            'milestone' => 'J-7',
        ]);

        $this->notify($sophie, 'document.uploaded', 'Nouveau document déposé', "ÉlecPro Services a déposé l'Attestation Urssaf 2026.", 'document', 'purple', $this->at('2026-10-09 14:10'), false);
        $this->notify($sophie, 'document.expiring', 'Échéance imminente', 'Assurance Décennale de Plomberie Martin expire dans 7 jours.', 'exclamation-triangle', 'amber', $this->at('2026-10-09 11:30'), false);
        $this->notify($sophie, 'document.validated', 'Document validé', 'Kbis validé par Sophie Martin pour Peinture Sud.', 'check-circle', 'green', $this->at('2026-10-08 16:45'), true);
        $this->notify($sophie, 'document.rejected', 'Document rejeté', 'Assurance Décennale de ÉlecPro Services rejetée (Document illisible).', 'x-circle', 'red', $this->at('2026-10-08 14:20'), true);
    }

    /** Seconde entreprise, avec des noms identiques à BatiNova pour vérifier l'isolation. */
    private function seedConcurrentBtp(): void
    {
        $company = Company::create([
            'name' => 'Concurrent BTP',
            'registration_number' => '98765432100023',
            'sector' => 'BTP — Gros œuvre',
            'email' => 'contact@concurrent-btp.fr',
            'phone' => '04 76 00 22 33',
            'address' => '8 avenue Alsace-Lorraine',
            'postal_code' => '38000',
            'city' => 'Grenoble',
            'country' => 'FR',
            'status' => CompanyStatus::Active,
            'subscription_plan' => 'business',
        ]);

        $this->tenant->set($company->id);

        $thomas = $this->createUser($company, 'Thomas', 'Leroy', 'thomas.leroy@concurrent-btp.fr', Role::Admin, $this->days(-1)->setTime(8, 45));
        $this->validator = $thomas;

        $types = $this->createDocumentTypes(['kbis', 'urssaf', 'decennale']);
        $this->createTemplate('Sous-traitant électricité', 'Modèle standard Concurrent BTP.', ['kbis', 'urssaf', 'decennale'], $types);

        $subcontractors = [
            ['Alpes Électricité', 'Électricité générale', 'Grenoble', 'Lucas', 'Vidal', ['kbis' => DocumentStatus::Valid, 'urssaf' => DocumentStatus::Valid, 'decennale' => DocumentStatus::Valid]],
            ['Plomberie Dauphiné', 'Plomberie / Sanitaire', 'Échirolles', 'Léa', 'Morel', ['kbis' => DocumentStatus::Valid, 'urssaf' => DocumentStatus::Missing, 'decennale' => DocumentStatus::Pending]],
            ['Isère Peinture', 'Peinture & Finitions', 'Voiron', 'Paul', 'Rey', ['kbis' => DocumentStatus::Valid, 'urssaf' => DocumentStatus::Expired, 'decennale' => DocumentStatus::Valid]],
        ];

        foreach ($subcontractors as $i => [$name, $activity, $city, $firstName, $lastName, $statuses]) {
            $subcontractor = Subcontractor::create([
                'legal_name' => $name,
                'type' => 'Sous-traitant direct',
                'activity' => $activity,
                'contact_first_name' => $firstName,
                'contact_last_name' => $lastName,
                'contact_email' => 'contact@'.Str::slug($name).'.fr',
                'city' => $city,
                'status' => SubcontractorStatus::Draft,
                'internal_owner_id' => $thomas->id,
            ]);

            $documents = $this->seedDocuments($subcontractor, $types, $statuses, $i);

            if ($name === 'Plomberie Dauphiné') {
                $this->addComment($documents['decennale'], $thomas, 'À contrôler avant le démarrage du chantier de Voiron.', $this->days(-2)->setTime(11, 0));
                $this->createRequest($subcontractor, $thomas, [
                    'message' => 'Merci de déposer votre attestation Urssaf.',
                    'sent_at' => $this->days(-4)->setTime(9, 0),
                    'due_date' => $this->days(10),
                    'expires_at' => $this->days(26)->endOfDay(),
                ], [
                    [$documents['urssaf'], DocumentStatus::Requested],
                ]);
            }

            if ($name === 'Alpes Électricité') {
                $this->audit($thomas, 'document.validated', $documents['kbis'], $this->days(-3)->setTime(10, 5), '10.0.0.12', [
                    'summary' => 'Kbis validé.',
                ]);
            }
        }
    }

    // ------------------------------------------------------------------ helpers

    private function days(int $offset): CarbonImmutable
    {
        return $this->today->addDays($offset);
    }

    /** Date/heure d'une maquette, décalée pour rester à la même distance d'aujourd'hui. */
    private function at(string $mockDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($mockDateTime)->addDays($this->shiftDays);
    }

    private function createUser(Company $company, string $firstName, string $lastName, string $email, Role $role, CarbonImmutable $lastLogin): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'role' => $role,
            'last_login_at' => $lastLogin,
        ]);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, DocumentType>
     */
    private function createDocumentTypes(array $keys): array
    {
        $types = [];

        foreach ($keys as $key) {
            [$name, $mandatory, $expires, $validity, $reminders, $formats, $maxMb] = self::DOCUMENT_TYPES[$key];

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

        return $types;
    }

    /**
     * @param  list<string>  $typeKeys
     * @param  array<string, DocumentType>  $types
     */
    private function createTemplate(string $name, string $description, array $typeKeys, array $types): DocumentTemplate
    {
        $template = DocumentTemplate::create(['name' => $name, 'description' => $description]);

        foreach ($typeKeys as $position => $key) {
            $template->items()->create([
                'document_type_id' => $types[$key]->id,
                'is_required' => $types[$key]->is_mandatory,
                'position' => $position,
            ]);
        }

        return $template;
    }

    /**
     * Crée les documents d'un sous-traitant puis calcule son statut et sa complétude.
     *
     * @param  array<string, DocumentType>  $types
     * @param  array<string, DocumentStatus>  $statuses
     * @return array<string, Document>
     */
    private function seedDocuments(Subcontractor $subcontractor, array $types, array $statuses, int $seed): array
    {
        $documents = [];
        $mandatoryStatuses = [];

        foreach ($statuses as $key => $status) {
            $type = $types[$key];
            $j = count($documents);

            $expires = ! $type->has_expiration_date ? null : match ($status) {
                DocumentStatus::ExpiringSoon => $this->days(12 + $seed),
                DocumentStatus::Expired => $this->days(-(10 + $seed)),
                DocumentStatus::Valid, DocumentStatus::Pending => $this->days(45 + 17 * $j + 3 * $seed),
                default => null,
            };

            $documents[$key] = $this->addDocument($subcontractor, $type, $status, [
                'expires' => $expires,
                'uploaded' => $status === DocumentStatus::Pending ? $this->days(-2)->setTime(15, 0) : null,
            ]);

            if ($type->is_mandatory) {
                $mandatoryStatuses[] = $status;
            }
        }

        $subcontractor->update([
            'status' => $this->completeness->subcontractorStatus($mandatoryStatuses),
            'completeness_score' => $this->completeness->calculate($mandatoryStatuses),
        ]);

        return $documents;
    }

    /**
     * Crée un document et ses versions.
     *
     * Options : expires, uploaded (version courante), original_name, comment, validator,
     * previous (statut d'une v1 remplacée par la version courante), previous_uploaded.
     */
    private function addDocument(Subcontractor $subcontractor, DocumentType $type, DocumentStatus $status, array $options = []): Document
    {
        $expires = $options['expires'] ?? null;
        $issued = null;

        if ($expires !== null) {
            $issued = $type->default_validity_months
                ? $expires->subMonths($type->default_validity_months)->min($this->days(-5))
                : $this->days(-120);
        }

        $document = Document::create([
            'subcontractor_id' => $subcontractor->id,
            'document_type_id' => $type->id,
            'status' => $status,
            'issue_date' => $issued,
            'expiration_date' => $expires,
        ]);

        if (in_array($status, [DocumentStatus::Missing, DocumentStatus::Requested], true)) {
            return $document;
        }

        $uploaded = $options['uploaded'] ?? ($issued ?? $this->days(-60))->addDays(2)->setTime(10, 0);
        $originalName = $options['original_name'] ?? $type->name.'.pdf';

        $previous = null;
        if (isset($options['previous'])) {
            $previousUploaded = $options['previous_uploaded'] ?? $uploaded->subDays(90);
            $previous = $this->addVersion($document, 1, $options['previous'], $originalName, $previousUploaded, $subcontractor);
        }

        $current = $this->addVersion($document, $previous ? 2 : 1, $status, $originalName, $uploaded, $subcontractor, $options['comment'] ?? null);

        $previous?->update([
            'replaced_at' => $uploaded,
            'replaced_by' => $current->id,
            'replacement_reason' => match ($options['previous']) {
                DocumentStatus::Rejected => 'Nouvelle version après rejet',
                DocumentStatus::Expired => 'Renouvellement après expiration',
                default => 'Mise à jour du document',
            },
        ]);

        $attributes = ['current_version_id' => $current->id];

        if (in_array($status, [DocumentStatus::Valid, DocumentStatus::ExpiringSoon, DocumentStatus::Expired], true)) {
            $attributes['validated_at'] = $uploaded->addDay();
            $attributes['validated_by'] = ($options['validator'] ?? $this->validator)->id;
        }

        $document->update($attributes);

        return $document;
    }

    private function addVersion(
        Document $document,
        int $number,
        DocumentStatus $status,
        string $originalName,
        CarbonImmutable $uploadedAt,
        Subcontractor $uploader,
        ?string $comment = null,
    ): DocumentVersion {
        $fileName = 'v'.$number.'-'.Str::uuid().'.pdf';
        $path = "companies/{$document->company_id}/documents/{$document->id}/{$fileName}";

        $version = new DocumentVersion([
            'document_id' => $document->id,
            'version_number' => $number,
            'file_name' => $fileName,
            'original_name' => $originalName,
            'file_path' => $path,
            'disk' => 'documents',
            'mime_type' => 'application/pdf',
            'file_size' => 150_000 + ($document->id * 7919 + $number * 104_729) % 900_000,
            'sha256' => hash('sha256', $path),
            'status' => $status,
            'uploaded_by_type' => $uploader->getMorphClass(),
            'uploaded_by_id' => $uploader->id,
            'comment' => $comment,
        ]);
        $version->created_at = $uploadedAt;
        $version->updated_at = $uploadedAt;
        $version->save();

        return $version;
    }

    private function addComment(Document $document, User $author, string $body, CarbonImmutable $at, string $visibility = DocumentComment::VISIBILITY_INTERNAL): void
    {
        $comment = new DocumentComment([
            'document_id' => $document->id,
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->id,
            'body' => $body,
            'visibility' => $visibility,
        ]);
        $comment->created_at = $at;
        $comment->updated_at = $at;
        $comment->save();
    }

    /**
     * @param  array{token?: string, message: string, sent_at: CarbonImmutable, due_date: CarbonImmutable, expires_at: CarbonImmutable, last_reminder_at?: CarbonImmutable}  $attributes
     * @param  list<array{0: Document, 1: DocumentStatus}>  $lines
     */
    private function createRequest(Subcontractor $subcontractor, User $creator, array $attributes, array $lines): DocumentRequest
    {
        $request = new DocumentRequest([
            'subcontractor_id' => $subcontractor->id,
            'message' => $attributes['message'],
            'due_date' => $attributes['due_date'],
            'expires_at' => $attributes['expires_at'],
            'status' => RequestStatus::Sent,
            'auto_reminders' => true,
            'sent_at' => $attributes['sent_at'],
            'last_reminder_at' => $attributes['last_reminder_at'] ?? null,
            'created_by' => $creator->id,
        ]);

        // Jeton de démo fixe pour ÉlecPro (lien des maquettes) ; aléatoire sinon
        if (isset($attributes['token'])) {
            $request->token_hash = hash('sha256', $attributes['token']);
        } else {
            $request->issueToken();
        }
        $request->save();

        foreach ($lines as [$document, $status]) {
            $request->requestedDocuments()->create([
                'document_type_id' => $document->document_type_id,
                'document_id' => $document->id,
                'status' => $status,
                'due_date' => $attributes['due_date'],
            ]);
        }

        return $request;
    }

    /** @param User|Subcontractor|null $actor null = système */
    private function audit(User|Subcontractor|null $actor, string $action, Document|DocumentRequest $entity, CarbonImmutable $at, string $ip, array $metadata): void
    {
        AuditLog::create([
            'user_type' => $actor?->getMorphClass() ?? 'system',
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'metadata' => $metadata,
            'ip_address' => $ip,
            'user_agent' => $actor instanceof User ? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154.0 Safari/537.36' : null,
            'created_at' => $at,
        ]);
    }

    private function notify(User $user, string $type, string $title, string $text, string $icon, string $color, CarbonImmutable $at, bool $read): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => ['title' => $title, 'text' => $text, 'icon' => $icon, 'color' => $color],
            'read_at' => $read ? $at->addHour() : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
