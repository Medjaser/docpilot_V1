# Plan de travail DocPilot — semaines 7 à 26

Suite de [demarrage.md](demarrage.md) (mois 1). Ce plan part de l'état réel du code au 14/11/2026
et va jusqu'à la soutenance.

Hypothèse de calendrier : stage de 6 mois (26 semaines), semaine 1 = lundi 5 octobre 2026,
fin le vendredi 2 avril 2027. Décaler toutes les dates si le calendrier réel diffère.

---

## 1. État des lieux (14/11/2026)

Le prototype est très avancé côté interface : toutes les routes du backlog existent (authentification,
sous-traitants, documents, échéances, modèles, utilisateurs, paramètres, audit, portail, admin plateforme).
En revanche, plusieurs briques sont **simulées** ou **non testées**, ce qui empêche une mise en recette.

### Écarts bloquants

| # | Constat | Où | Conséquence |
| --- | --- | --- | --- |
| E1 | Aucun e-mail n'est réellement envoyé (sauf fin d'essai) : le dispatcher écrit seulement dans `notification_logs` | `app/Domain/Notifications/CompanyNotificationDispatcher.php` | Invitations, demandes de documents, relances, rejets n'arrivent jamais |
| E2 | Paiement Stripe simulé : un admin active une offre payante sans payer | `app/Livewire/App/Settings/Subscription.php` (`confirmStripeCheckout`) | Faille métier, à désactiver avant toute recette |
| E3 | SDK S3 absent de `composer.json` (`league/flysystem-aws-s3-v3`) : le disque `documents` retombe en local | `config/filesystems.php` | MinIO / R2 inutilisés, pas d'URL temporaire |
| E4 | Le téléchargement génère un faux PDF si le fichier est introuvable | `app/Http/Controllers/DocumentDownloadController.php` | Masque les fichiers perdus en production |
| E5 | `QUEUE_CONNECTION=sync` et export ZIP synchrone dans Livewire | `.env.example`, `Subcontractors/Show.php` | Timeouts sur gros dossiers, pas de file d'attente réelle |
| E6 | Limites PHP locales trop basses (2 Mo / 8 Mo au lieu de 10 Mo) | `storage/logs/laravel.log` | Uploads > 2 Mo refusés en local |

### Couverture de tests

Environ 136 tests existent (auth, isolation, entreprise, portail, catégories, règles métier).
**Aucun test** sur : validation / rejet, demandes de documents, échéances, commande
`docpilot:check-expirations`, téléchargement, exports, invitations, audit, abonnement.
Trois fichiers d'isolation se recouvrent (`tests/Feature/TenantIsolationTest.php`,
`tests/Feature/Tenancy/TenantIsolationTest.php`, `tests/Feature/Subcontractors/...`).

### Avancement du backlog

| # | User story | État | Reste à faire |
| --- | --- | --- | --- |
| 1 | Création du compte entreprise | Fait | — |
| 2 | Connexion / déconnexion / mot de passe | Fait, testé | — |
| 3 | Invitation d'un collaborateur | Partiel | E-mail réel, tests |
| 4 | Fiche sous-traitant | Fait, testé | — |
| 5 | Catégories de documents | Fait, testé | — |
| 6 | Modèle de dossier | Fait | Tests |
| 7 | Dépôt et téléchargement sécurisé | Partiel | URL temporaire S3, retirer le faux PDF, tests |
| 8 | Documents expirant bientôt | Fait | Tests |
| 9 | Alerte avant expiration | Partiel | E-mail réel, test de la commande |
| 10 | Relances automatiques | Partiel | E-mail réel en file d'attente, tests |
| 11 | Demande avec lien sécurisé | Partiel | E-mail réel, tests (expiration, révocation) |
| 12 | Dépôt mobile sans compte | Fait, testé | Recette sur vrais téléphones |
| 13 | Valider / rejeter avec motif | Partiel | E-mail au sous-traitant, tests des critères |
| 14 | Score de complétude | Fait, testé | — |
| 15 | Export ZIP et PDF | Partiel | Job asynchrone, vrai moteur PDF |
| 16 | Historique des actions | Fait | Tests |
| 17 | Suspension d'une entreprise | Fait, testé | — |
| 18 | Offre payante Stripe | Simulé | Stripe réel (Cashier) ou désactivation |

---

## 2. Vue d'ensemble

| Phase | Semaines | Dates | Objectif | Jalon |
| --- | --- | --- | --- | --- |
| A. Consolidation | S7 – S11 | 16/11 → 18/12 | Corriger E1 à E6, tester le cycle documentaire | J1 : démo tuteur, `develop` vert |
| — Congés / tampon | S12 – S13 | 21/12 → 01/01 | Retard éventuel + rapport (chap. 1-2) | — |
| B. Recette | S14 – S16 | 04/01 → 22/01 | Déploiement VPS, entreprise pilote | J2 : recette en ligne |
| C. Fonctionnalités Should / Could | S17 – S20 | 25/01 → 19/02 | Retours pilote, exports, Stripe, admin | J3 : version bêta |
| D. Production et clôture | S21 – S26 | 22/02 → 02/04 | Sauvegardes, RGPD, mise en prod, rapport, soutenance | J4 : v1.0 en production, J5 : soutenance |

Rituels : point tuteur hebdomadaire (30 min, le lundi), démo à chaque jalon,
tableau GitHub Projects à jour, une pull request par ticket (voir [conventions.md](conventions.md)).

---

## 3. Détail semaine par semaine

### Phase A — Consolidation

#### S7 (16/11 → 20/11) — Socle fiable

- [ ] Pousser le dépôt sur GitHub (`main`, `develop`), protection de branche, CI verte
- [ ] Ajouter `league/flysystem-aws-s3-v3`, vérifier le disque `documents` sur MinIO (E3)
- [ ] `upload_max_filesize` / `post_max_size` à 12M dans `docker/php/Dockerfile` (E6)
- [ ] `QUEUE_CONNECTION=database` en local et en CI (E5)
- [ ] Désactiver le faux paiement derrière un drapeau `DOCPILOT_BILLING_ENABLED=false` (E2)
- [ ] Fusionner les trois fichiers de tests d'isolation en un seul dossier `tests/Feature/Tenancy/`

Livrable : `develop` vert, uploads de 10 Mo fonctionnels, S3 local opérationnel.

#### S8 (23/11 → 27/11) — E-mails réels (1/2)

- [ ] Brancher l'envoi réel dans `CompanyNotificationDispatcher` via des Notifications Laravel en file (`ShouldQueue`), en gardant `notification_logs` comme trace et anti-doublon
- [ ] Gabarit commun (réutiliser `Notifications/Concerns/BrandedMail`)
- [ ] E-mail d'invitation collaborateur (story 3)
- [ ] E-mail de demande de documents avec lien de dépôt (story 11)
- [ ] Tests avec `Notification::fake()` : envoi, destinataire, absence de doublon

Livrable : invitations et demandes visibles dans Mailpit.

#### S9 (30/11 → 04/12) — E-mails réels (2/2)

- [ ] Relances automatiques et manuelles (story 10)
- [ ] Alertes d'expiration internes J-60/30/15/7/0 (story 9)
- [ ] E-mail de rejet avec motif + e-mail de validation (story 13)
- [ ] Tenant défini en début de chaque job (`CurrentCompany::set`)
- [ ] Test de la commande `docpilot:check-expirations --date=...` (statuts, alertes, anti-doublon, score)

Livrable : tout le parcours e-mail démontrable dans Mailpit.

#### S10 (07/12 → 11/12) — Tests du cycle documentaire

- [ ] Validation / rejet : critères d'acceptation de la story 13 (motif obligatoire, historique, 403 pour le lecteur)
- [ ] Demandes : jeton haché, expiration, révocation, limitation de débit du portail
- [ ] Téléchargement : Policy par rôle, URL temporaire 5 min, suppression du faux PDF (E4)
- [ ] Modèles de dossier, échéances, audit, invitations
- [ ] Un test d'isolation multi-tenant par ressource (documents, téléchargements, exports, liens)

Livrable : chaque story « Must » couverte par au moins un test fonctionnel.

#### S11 (14/12 → 18/12) — Sécurité et jalon J1

- [ ] Revue OWASP : validations `exists:` / `unique:` filtrées par `company_id`, requêtes `DB::table` (`Profile.php`), MIME réel, en-têtes HTTP
- [ ] Matrice rôles × actions vérifiée par tests (admin, gestionnaire, lecteur, sous-traitant)
- [ ] Vérifier que l'accès support temporaire expire réellement
- [ ] **J1** : démo au tuteur du parcours BatiNova complet, mise à jour de `docs/architecture.md`

### Congés / tampon — S12 et S13 (21/12 → 01/01)

- [ ] Rattraper les tâches de la phase A non terminées
- [ ] Rapport de stage : contexte, cadrage, analyse concurrentielle, conception (UML, modèle de données)

### Phase B — Recette

#### S14 (04/01 → 08/01) — Conteneurs de production

- [ ] `docker-compose.prod.yml` : app (php-fpm ou FrankenPHP), Caddy HTTPS, worker de file, scheduler
- [ ] Image construite en CI (assets compilés, `composer install --no-dev`, caches Laravel)
- [ ] Fichiers `.env` par environnement, secrets hors dépôt

#### S15 (11/01 → 15/01) — Déploiement de la recette

- [ ] VPS européen, bucket R2 recette, compte Brevo (adresses de test)
- [ ] Déploiement continu : merge sur `develop` → recette (GitHub Actions + SSH)
- [ ] Supervision des workers, logs centralisés, page de santé `/up`

#### S16 (18/01 → 22/01) — Entreprise pilote et jalon J2

- [ ] Créer le compte pilote, importer quelques sous-traitants réels
- [ ] Tester le portail de dépôt sur iPhone et Android
- [ ] Recueillir les retours (formulaire court + entretien)
- [ ] **J2** : recette en ligne, retours pilote priorisés dans le backlog

### Phase C — Fonctionnalités Should / Could

#### S17 (25/01 → 29/01) — Retours pilote et exports

- [ ] Corrections prioritaires issues du pilote
- [ ] Export ZIP en job asynchrone + notification « export prêt » (story 15)
- [ ] Remplacer `SimplePdfGenerator` par un vrai moteur PDF (ex. `barryvdh/laravel-dompdf`)

#### S18 (01/02 → 05/02) — Abonnement Stripe (story 18, Could)

- [ ] Décision avec le tuteur : Stripe réel ou offre manuelle par l'admin plateforme
- [ ] Si Stripe : Laravel Cashier, Checkout en mode test, webhooks (paiement, échec, résiliation)
- [ ] Tests des webhooks et des transitions de statut d'entreprise

#### S19 (08/02 → 12/02) — Administration plateforme

- [ ] Tableau de bord admin : entreprises actives, essais, documents traités
- [ ] Tests de l'espace admin (accès refusé aux non-admins, limites d'offre)

#### S20 (15/02 → 19/02) — Performance et ergonomie

- [ ] Chasse aux requêtes N+1 (tableau de bord, échéances, liste des sous-traitants), index PostgreSQL
- [ ] Portail mobile : accessibilité, messages d'erreur, reprise après échec d'upload
- [ ] **J3** : version bêta, tag `v0.9`

### Phase D — Production et clôture

#### S21 (22/02 → 26/02) — Exploitation et RGPD

- [ ] Sauvegarde `pg_dump` quotidienne vers R2 (7 / 4 / 3), restauration testée et documentée
- [ ] Surveillance : disponibilité, erreurs (ex. Sentry), alerte si le scheduler ne tourne plus
- [ ] RGPD : mentions légales, durée de conservation, export et suppression des données d'une entreprise

#### S22 (01/03 → 05/03) — Mise en production

- [ ] Domaine `app.docpilot.fr`, bucket R2 production, Brevo production
- [ ] Merge `develop` → `main`, tag `v1.0`, procédure de retour arrière
- [ ] **J4** : v1.0 en production

#### S23 (08/03 → 12/03) — Gel fonctionnel et documentation

- [ ] Uniquement des corrections de bugs
- [ ] Guide utilisateur (entreprise et sous-traitant), guide d'exploitation, procédure de déploiement

#### S24 – S25 (15/03 → 26/03) — Rapport de stage

- [ ] Chapitres réalisation, tests, déploiement, bilan et perspectives
- [ ] Annexes : diagrammes, captures, extraits de code, résultats de tests
- [ ] Relecture par le tuteur

#### S26 (29/03 → 02/04) — Soutenance

- [ ] Support de présentation (15 à 20 diapositives)
- [ ] Démo scénarisée BatiNova (vidéo de secours en cas de panne réseau)
- [ ] Deux répétitions chronométrées
- [ ] **J5** : soutenance

---

## 4. Risques et parades

| Risque | Probabilité | Impact | Parade |
| --- | --- | --- | --- |
| Fuite de données entre entreprises | Faible | Critique | Test d'isolation obligatoire par ressource, revue S11 |
| Retard sur les e-mails (S8-S9) | Moyenne | Élevé | Tampon S12-S13 ; Stripe repoussé ou abandonné en premier |
| VPS / R2 / Brevo non fournis à temps | Moyenne | Élevé | Demander les accès dès S7 au tuteur |
| Pas d'entreprise pilote | Moyenne | Moyen | Test avec des collègues d'AD CLOUD IT jouant les rôles |
| Rapport commencé trop tard | Élevée | Élevé | Rédaction continue : une page par semaine dès S12 |

Ordre de sacrifice si le temps manque : story 18 (Stripe) → tableau de bord admin → vrai moteur PDF.
Les stories « Must » et l'isolation multi-tenant ne sont jamais sacrifiées.

---

## 5. Indicateurs de suivi

- Stories « Must » terminées selon la définition de « terminé » : objectif 14 / 14 à J2
- CI verte sur `develop` : en permanence
- Tests : chaque story couverte, un test d'isolation par ressource
- Retours pilote traités : 100 % des bloquants avant J4

## 6. Questions à régler avec le tuteur dès S7

1. Calendrier exact du stage et date de soutenance
2. Accès VPS, Cloudflare R2 et Brevo : qui les crée et quand ?
3. Stripe réel ou activation manuelle des offres par l'admin plateforme ?
4. Entreprise pilote identifiée pour la semaine 16 ?
