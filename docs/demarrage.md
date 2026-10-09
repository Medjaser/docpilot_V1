# Plan de démarrage — Mois 1

Objectif du mois : cahier des charges, backlog priorisé, maquettes, modèle de données validé,
dépôt initial et environnement local opérationnel (cadrage §20).

## Semaine 1 — Comprendre le besoin

- [ ] Relire le cadrage et le scénario BatiNova ; lister les questions pour le tuteur
- [ ] Analyse rapide de 3 à 5 concurrents (fonctionnalités, prix, cible)
- [ ] Fiches persona : assistante administrative, dirigeant, conducteur de travaux, sous-traitant
- [ ] Définir le périmètre MVP (inclus / exclu) et le faire valider

## Semaine 2 — Spécifier

- [ ] Diagramme de cas d'utilisation (UML) par acteur
- [ ] Rédiger les user stories ci-dessous avec critères d'acceptation
- [ ] Matrice rôles × actions (admin / gestionnaire / lecteur / sous-traitant)
- [ ] Diagrammes d'états : document, sous-traitant, demande

## Semaine 3 — Maquetter

- [ ] Maquettes (Figma ou Penpot) : connexion, tableau de bord, liste et fiche sous-traitant,
      validation d'un document, portail de dépôt mobile
- [ ] Valider le parcours principal avec le tuteur
- [ ] Montrer les maquettes à une première entreprise pilote (risque n°5 du cadrage)

## Semaine 4 — Socle technique (ce dépôt)

- [ ] Créer le dépôt GitHub privé, pousser `main` et `develop`
- [ ] Lancer `bash scripts/setup.sh`, vérifier les 3 URL locales
- [ ] `php artisan test` vert (règles métier + isolation multi-tenant)
- [ ] Revoir le modèle de données avec le tuteur (`docs/architecture.md` §3)
- [ ] Activer la CI GitHub Actions et la protection de branche sur `main`
- [ ] Choisir le kit d'authentification (Livewire starter kit ou Laravel Fortify) pour le mois 2

## Backlog initial (extrait, à compléter)

| # | User story | Priorité | Mois |
| --- | --- | --- | --- |
| 1 | En tant que dirigeant, je crée le compte de mon entreprise pour démarrer un essai | Must | 2 |
| 2 | En tant qu'utilisateur, je me connecte, me déconnecte et réinitialise mon mot de passe | Must | 2 |
| 3 | En tant qu'admin, j'invite un collaborateur et je lui attribue un rôle | Must | 2 |
| 4 | En tant que gestionnaire, je crée une fiche sous-traitant | Must | 3 |
| 5 | En tant qu'admin, je configure les catégories de documents de mon entreprise | Must | 3 |
| 6 | En tant que gestionnaire, j'applique un modèle de dossier à un sous-traitant | Must | 3 |
| 7 | En tant que gestionnaire, je dépose un document et je le télécharge de façon sécurisée | Must | 3 |
| 8 | En tant que gestionnaire, je vois les documents expirant bientôt | Must | 4 |
| 9 | En tant que gestionnaire, je reçois une alerte avant l'expiration d'un document | Must | 4 |
| 10 | En tant que gestionnaire, j'active les relances automatiques d'une demande | Must | 4 |
| 11 | En tant que gestionnaire, j'envoie une demande de documents avec un lien sécurisé | Must | 5 |
| 12 | En tant que sous-traitant, je dépose mes documents depuis mon téléphone sans compte | Must | 5 |
| 13 | En tant que gestionnaire, je valide ou rejette un document avec un motif | Must | 5 |
| 14 | En tant que gestionnaire, je consulte le score de complétude d'un sous-traitant | Must | 5 |
| 15 | En tant que gestionnaire, j'exporte le dossier d'un sous-traitant en ZIP et en PDF | Should | 5 |
| 16 | En tant qu'admin, je consulte l'historique des actions | Should | 5 |
| 17 | En tant qu'admin plateforme, je suspends une entreprise | Should | 6 |
| 18 | En tant que dirigeant, je souscris une offre payante (Stripe) | Could | 6 |

Exemple de critères d'acceptation (story 13) :

- Le rejet est impossible sans motif sélectionné.
- Le sous-traitant reçoit un e-mail et voit le motif sur son portail.
- L'ancienne version reste dans l'historique avec le statut « Rejeté ».
- Un lecteur ne voit pas les boutons Valider / Rejeter, et l'action renvoie 403 s'il l'appelle directement.

## Questions à poser au tuteur

1. Le dépôt GitHub est-il créé par AD CLOUD IT ou par moi ? Qui fait les revues de code ?
2. Un VPS de recette est-il déjà disponible ? Quel fournisseur (hébergement en Europe) ?
3. Compte Cloudflare R2 et Brevo : fournis par l'entreprise ?
4. Livewire starter kit ou authentification sur mesure : préférence ?
5. Des entreprises pilotes sont-elles déjà identifiées ?
6. Le nom « DocPilot » est-il confirmé pour le domaine `app.docpilot.fr` ?
