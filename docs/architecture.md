# Architecture DocPilot

## 1. Principe : monolithe modulaire

Un seul projet Laravel contient l'interface, la logique métier, l'authentification, l'administration,
les tâches planifiées et les notifications. Pas de microservices, pas de Kubernetes (cadrage §10, §11).

```
Navigateur (entreprise)      Navigateur (sous-traitant, mobile)
          │                              │ lien /depot/{jeton}
          ▼                              ▼
   ┌──────────────────────── Laravel ────────────────────────┐
   │ Livewire / Blade  →  Contrôleurs légers  →  Domain/      │
   │ Policies · Middleware tenant · Jobs · Notifications      │
   └───────┬──────────────────┬──────────────────┬───────────┘
           ▼                  ▼                  ▼
      PostgreSQL        Stockage S3 privé     SMTP (Brevo)
   (métadonnées)        (fichiers, R2)        (e-mails)
           ▲
   queue:work + schedule:work  (relances, alertes, exports ZIP)
```

Règle : **les contrôleurs et composants Livewire restent légers**. Toute règle métier
(statut, score, échéances, droits) vit dans `app/Domain/` ou une Policy, et se teste unitairement.

## 2. Multi-tenant (le point le plus critique)

Une seule base, une colonne `company_id` sur chaque table métier. Trois barrières :

1. **Scope global** — le trait `App\Support\Tenancy\BelongsToCompany` filtre toute requête Eloquent
   sur l'entreprise courante et remplit `company_id` à la création.
2. **Policies** — chaque action vérifie `$user->belongsToCompany($model->company_id)` + le rôle.
3. **Tests** — `tests/Feature/Tenancy/` : un test d'isolation par ressource (sous-traitants, documents,
   téléchargements, exports, liens externes).

L'entreprise courante (`CurrentCompany`) est définie :

- par le middleware `SetCurrentCompany` pour un utilisateur connecté ;
- par le jeton du lien de dépôt pour le portail sous-traitant ;
- explicitement dans les jobs (`app(CurrentCompany::class)->set($companyId)`).

Les tâches multi-entreprises (vérification quotidienne) utilisent `Model::acrossAllCompanies()`
de façon **explicite et volontaire**.

Pièges à éviter :

- `DB::table(...)` et les requêtes SQL brutes **ne passent pas** par le scope global → toujours filtrer `company_id`.
- Les règles de validation `exists:` / `unique:` doivent inclure `company_id`.
- Un job en file d'attente n'a pas d'utilisateur connecté → définir le tenant au début du job.

## 3. Modèle de données (version initiale)

| Table | Rôle | Remarques |
| --- | --- | --- |
| companies | Entreprises clientes (tenants) | `settings` JSONB pour délais / relances |
| users | Utilisateurs | `company_id` NULL = admin plateforme |
| user_invitations | Invitations de collaborateurs | jeton stocké haché |
| subcontractors | Fiches sous-traitants | `completeness_score` dénormalisé |
| document_types | Catégories (Kbis, RC Pro…) | `company_id` NULL = modèle global |
| document_templates / _items | Modèles de dossiers | ex. « Sous-traitant électricité » |
| documents | Document attendu = sous-traitant × catégorie | unique (subcontractor, type) |
| document_versions | Chaque fichier déposé | jamais écrasé ; une version courante |
| document_requests | Demande + lien de dépôt | `token_hash` = SHA-256 du jeton |
| requested_documents | Documents inclus dans une demande | référence `documents` |
| notifications | Notifications dans l'application | table standard Laravel |
| notification_logs | Historique des envois + anti-doublon | `dedup_key` unique |
| audit_logs | Journal d'audit | pas de donnée sensible dans `metadata` |

Écarts volontaires par rapport au modèle du cadrage (§13), à valider avec le tuteur :

- `requested_documents` pointe vers `documents` (et non `document_types`) : un document attendu existe
  dès l'application du modèle, la demande ne fait que le référencer.
- La table `notifications` du cadrage est scindée : table Laravel standard (in-app) + `notification_logs`
  (historique et déduplication des e-mails/relances).
- Le jeton du lien de dépôt n'est **jamais** stocké en clair (`token_hash`).
- Statut et dates d'expiration présents aussi sur `document_versions` pour garder l'historique exact.

## 4. Cycle de vie d'un document

```
manquant → demandé → déposé / en attente de validation ─┬→ valide ⇄ bientôt expiré → expiré
                                                        └→ rejeté (motif obligatoire) → nouvelle version
```

`DocumentStatusResolver` recalcule chaque jour valide / bientôt expiré / expiré (seuil 30 jours configurable).
`CompletenessScore` : documents obligatoires valides ÷ documents obligatoires attendus (un expiré ne compte pas).

## 5. Fichiers et sécurité

- Disque `documents` (config/filesystems.php) : bucket **privé**, jamais d'URL publique permanente.
- Téléchargement : contrôle Policy → `Storage::disk('documents')->temporaryUrl($path, now()->addMinutes(5))`.
- Upload : extensions `pdf jpg jpeg png`, contrôle du **type MIME réel**, 10 Mo max (configurable),
  nom de fichier régénéré : `companies/{company_id}/subcontractors/{id}/{uuid}.{ext}`.
- Liens externes : jeton de 48 caractères aléatoires, expiration, révocation, limitation de débit (`throttle:portal`).
- Connexion : `throttle:login` (5 essais / minute), mots de passe hachés, vérification d'e-mail.
- `.env` jamais commité ; identifiants différents par environnement.

## 6. Environnements

| Environnement | Hébergement | Stockage | E-mails |
| --- | --- | --- | --- |
| Local | Docker Compose | MinIO | Mailpit |
| Recette | VPS (Docker Compose + Caddy) | R2 bucket recette | Brevo (adresses de test) |
| Production | VPS (Docker Compose + Caddy, HTTPS Let's Encrypt) | R2 bucket prod | Brevo |

Sauvegardes (mois 6) : `pg_dump` quotidien → R2, rétention 7 quotidiennes / 4 hebdomadaires / 3 mensuelles,
restauration testée et documentée.
