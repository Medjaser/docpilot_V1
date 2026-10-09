# Conventions de travail

## Branches Git

| Branche | Usage |
| --- | --- |
| `main` | version stable, déployée en production |
| `develop` | intégration, déployée en recette |
| `feature/<ticket>-<description>` | nouvelle fonctionnalité, ex. `feature/12-subcontractor-form` |
| `fix/<description>` | correction |
| `release/<version>` | préparation d'une version |

Flux : `feature/*` → pull request vers `develop` → revue → merge. `develop` → `main` à chaque version.
Jamais de commit direct sur `main`. La CI doit être verte avant tout merge.

## Messages de commit (Conventional Commits)

```
feat: add subcontractor creation form
feat: implement document expiration scheduler
fix: prevent cross-tenant document access
test: add document validation tests
docs: update deployment procedure
refactor: extract completeness score calculator
chore: bump laravel dependencies
```

## Code

- Contrôleurs et composants Livewire légers ; règles métier dans `app/Domain/`.
- Toute table métier : colonne `company_id` + trait `BelongsToCompany` + Policy.
- Formulaires : validation dans une Form Request (ou attributs Livewire), jamais de `$request->all()`.
- Statuts : toujours via les enums de `app/Enums`, jamais de chaînes en dur.
- Archiver plutôt que supprimer (`archived_at`).
- Actions importantes : `app(AuditLogger::class)->log('subcontractor.created', $subcontractor)`.
- Code en anglais (classes, méthodes, colonnes), interface et documentation en français.
- `vendor/bin/pint` avant chaque commit.

## Définition de « terminé » pour une tâche

- [ ] Critères d'acceptation du ticket remplis
- [ ] Tests ajoutés (unitaires pour les règles, fonctionnels pour le parcours)
- [ ] Test d'isolation multi-tenant si nouvelle ressource
- [ ] Policy vérifiée pour chaque rôle (admin, gestionnaire, lecteur)
- [ ] CI verte, Pint OK
- [ ] Pull request relue et mergée dans `develop`
- [ ] Démontrable en recette

## Backlog

Chaque ticket : titre, description, critères d'acceptation, priorité, estimation, statut
(à faire · en cours · en revue · en test · terminé · bloqué). GitHub Projects suffit.
