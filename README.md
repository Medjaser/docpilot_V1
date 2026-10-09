# DocPilot

Plateforme SaaS multi-tenant de gestion, de collecte et de suivi des documents administratifs des sous-traitants.
Projet de stage PFE — AD CLOUD IT.

> Centralisez les documents de vos sous-traitants, suivez les échéances et automatisez les relances.

## Stack

| Couche | Technologie |
| --- | --- |
| Application (monolithe modulaire) | Laravel 13 · PHP 8.3 |
| Interface | Blade · Livewire · Tailwind CSS 4 (Vite) |
| Base de données | PostgreSQL 16 |
| Stockage des fichiers | S3 privé : MinIO en local, Cloudflare R2 en recette / production |
| E-mails | Mailpit en local, Brevo en production |
| Conteneurs | Docker · Docker Compose |
| CI | GitHub Actions (Pint + tests sur PostgreSQL) |

## Démarrage rapide

Prérequis : Docker Desktop et Git.

```bash
git clone <url-du-depot-prive> docpilot
cd docpilot
bash scripts/setup.sh
```

Le script installe les dépendances, crée la base, charge les données de démonstration et compile le front-end.
Ensuite, **committez `composer.json` et `composer.lock`** (Livewire et le driver S3 y ont été ajoutés).

| Service | URL |
| --- | --- |
| Application | http://localhost:8000 |
| Mailpit (e-mails de test) | http://localhost:8025 |
| MinIO (console S3) | http://localhost:9001 — `docpilot` / `docpilot-secret` |

Comptes de démonstration (mot de passe `password`) :

| E-mail | Rôle |
| --- | --- |
| admin@docpilot.local | Administrateur plateforme AD CLOUD IT |
| sophie@batinova.local | Administratrice BatiNova Rénovation |
| marc@batinova.local | Gestionnaire |
| julien@batinova.local | Lecteur |

> Les écrans de connexion arrivent au mois 2 (semaine 5) : les comptes existent déjà en base.

## Commandes utiles

```bash
docker compose up -d                                   # démarrer
docker compose down                                    # arrêter
docker compose exec app php artisan test               # tests
docker compose exec app vendor/bin/pint                # formater le code
docker compose exec app php artisan migrate:fresh --seed   # réinitialiser la base
docker compose exec app php artisan docpilot:check-expirations   # tâche quotidienne à la main
docker compose exec app npm run dev                    # Vite en mode développement
docker compose logs -f app                             # logs
```

## Structure

```
app/
├── Domain/                 # règles métier, par domaine fonctionnel (pas dans les contrôleurs)
│   ├── Audit/              # AuditLogger
│   ├── Documents/          # DocumentStatusResolver, CompletenessScore, ExpirationAlertSchedule
│   ├── Companies/ Users/ Subcontractors/ DocumentTypes/
│   └── Invitations/ Notifications/ Subscriptions/
├── Enums/                  # statuts et rôles du cadrage (libellés en français)
├── Http/Middleware/        # SetCurrentCompany (tenant courant)
├── Models/                 # modèles Eloquent
├── Policies/               # droits par rôle + 2e barrière d'isolation
├── Support/Tenancy/        # CurrentCompany + trait BelongsToCompany
└── Console/Commands/       # docpilot:check-expirations
config/docpilot.php         # paramètres métier (taille max, délais, offres…)
docker/                     # Dockerfile PHP, Caddy (prod), init PostgreSQL
docs/                       # architecture, conventions, plan de démarrage
tests/Unit/Domain/          # tests des règles métier
tests/Feature/Tenancy/      # tests d'isolation multi-tenant
```

## Documentation

- [docs/architecture.md](docs/architecture.md) — architecture, multi-tenant, modèle de données, sécurité
- [docs/conventions.md](docs/conventions.md) — Git, commits, code, définition de « terminé »
- [docs/demarrage.md](docs/demarrage.md) — plan des 4 premières semaines et backlog initial
