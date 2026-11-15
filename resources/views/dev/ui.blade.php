<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DocPilot - Design System (/dev/ui)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="p-8 text-slate-800 space-y-10 max-w-7xl mx-auto">
    <div class="border-b border-slate-200 pb-5 flex items-center justify-between">
        <div>
            <x-ui.logo size="lg" />
            <p class="text-sm text-slate-500 mt-1">Guide et catalogue des composants Blade anonymes réutilisables</p>
        </div>
        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">Environnement Local</span>
    </div>

    @php
        $uploadLimits = \App\Support\UploadLimitsChecker::inspect();
    @endphp
    @if (! $uploadLimits['ok'])
        <x-ui.alert type="warning" title="Attention : Limites d'upload PHP insuffisantes ({{ $uploadLimits['upload_max_filesize'] }} / {{ $uploadLimits['post_max_size'] }})">
            {{ $uploadLimits['warning'] }}
            <div class="mt-2 font-mono text-xs bg-white/80 p-2 rounded border border-amber-300">
                upload_max_filesize = {{ $uploadLimits['required_mb'] + 2 }}M<br>
                post_max_size = {{ $uploadLimits['required_mb'] + 5 }}M
            </div>
        </x-ui.alert>
    @else
        <x-ui.alert type="info" title="Configuration PHP Upload OK ({{ $uploadLimits['required_mb'] }} Mo autorisés)">
            upload_max_filesize = {{ $uploadLimits['upload_max_filesize'] }} | post_max_size = {{ $uploadLimits['post_max_size'] }} ({{ $uploadLimits['php_ini_path'] }})
        </x-ui.alert>
    @endif

    <!-- Logos -->
    <section class="space-y-4">
        <h2 class="text-lg font-bold text-slate-900 border-b pb-2">1. Logo DocPilot</h2>
        <div class="flex items-center gap-8 bg-white p-6 rounded-xl border border-slate-200">
            <div>
                <span class="text-xs text-slate-400 block mb-2">Variant Dark (light bg)</span>
                <x-ui.logo variant="dark" />
            </div>
            <div class="bg-slate-900 p-4 rounded-xl">
                <span class="text-xs text-slate-400 block mb-2">Variant Light (dark bg)</span>
                <x-ui.logo variant="light" />
            </div>
        </div>
    </section>

    <!-- Boutons -->
    <section class="space-y-4">
        <h2 class="text-lg font-bold text-slate-900 border-b pb-2">2. Boutons (<x-ui.button />)</h2>
        <div class="flex flex-wrap items-center gap-4 bg-white p-6 rounded-xl border border-slate-200">
            <x-ui.button variant="primary">Bouton Primaire</x-ui.button>
            <x-ui.button variant="secondary">Bouton Secondaire</x-ui.button>
            <x-ui.button variant="danger">Bouton Danger</x-ui.button>
            <x-ui.button variant="success">Bouton Succès</x-ui.button>
            <x-ui.button variant="ghost">Bouton Ghost</x-ui.button>
            <x-ui.button variant="primary" icon="plus">Avec Icône Gauche</x-ui.button>
            <x-ui.button variant="secondary" icon="arrow-right" iconPosition="right">Icône Droite</x-ui.button>
            <x-ui.button variant="primary" size="sm">Taille Small</x-ui.button>
            <x-ui.button variant="secondary" href="#">Mode Lien (<a>)</x-ui.button>
        </div>
    </section>

    <!-- Champs de formulaire -->
    <section class="space-y-4">
        <h2 class="text-lg font-bold text-slate-900 border-b pb-2">3. Formulaires & Saisie</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white p-6 rounded-xl border border-slate-200">
            <x-ui.input label="Nom de l'entreprise" required icon="building-office" placeholder="ex. ÉlecPro Services" help="Raison sociale officielle." />
            <x-ui.password label="Mot de passe" required help="Au moins 8 caractères." />
            <x-ui.select label="Secteur d'activité" required icon="building-office" :options="['btp' => 'BTP - Construction / Rénovation', 'elec' => 'Électricité', 'plumb' => 'Plomberie']" />
            <x-ui.textarea label="Commentaires internes" placeholder="Notes confidentielles..." rows="2" />
            <div class="space-y-4 pt-2">
                <x-ui.checkbox label="J'accepte les Conditions Générales d'Utilisation" />
                <x-ui.toggle label="Activer les relances automatiques" checked />
            </div>
        </div>
    </section>

    <!-- Badges -->
    <section class="space-y-4">
        <h2 class="text-lg font-bold text-slate-900 border-b pb-2">4. Badges de Statut (<x-ui.badge />)</h2>
        <div class="flex flex-wrap items-center gap-3 bg-white p-6 rounded-xl border border-slate-200">
            <x-ui.badge status="valid" />
            <x-ui.badge status="complete" />
            <x-ui.badge status="expiring_soon" />
            <x-ui.badge status="expired" />
            <x-ui.badge status="rejected" />
            <x-ui.badge status="incomplete" />
            <x-ui.badge status="critical" />
            <x-ui.badge status="pending" />
            <x-ui.badge status="requested" />
            <x-ui.badge status="missing" />
            <x-ui.badge status="active" />
            <x-ui.badge status="disabled" />
        </div>
    </section>

    <!-- Stat Cards & Progress -->
    <section class="space-y-4">
        <h2 class="text-lg font-bold text-slate-900 border-b pb-2">5. Stat Cards & Complétude</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-ui.stat-card icon="user-group" color="blue" label="Sous-traitants suivis" value="25" subtext="+3 ce mois-ci" />
            <x-ui.stat-card icon="check-circle" color="green" label="Dossiers complets" value="17" subtext="68% du total" />
            <x-ui.stat-card icon="exclamation-circle" color="amber" label="Dossiers incomplets" value="5" subtext="20% du total" />
            <x-ui.stat-card icon="x-circle" color="red" label="Dossiers critiques" value="3" subtext="12% du total" />
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
            <div>
                <p class="text-sm font-semibold mb-2">Barres de progression</p>
                <div class="space-y-3">
                    <x-ui.progress :percentage="85" />
                    <x-ui.progress :percentage="60" />
                    <x-ui.progress :percentage="30" />
                </div>
            </div>
            <div class="flex items-center gap-6 justify-center">
                <div>
                    <p class="text-sm font-semibold mb-2 text-center">Cercle Complétude</p>
                    <x-ui.progress-ring :percentage="71" :size="72" />
                </div>
                <div>
                    <p class="text-sm font-semibold mb-2 text-center">Cercle Alerte</p>
                    <x-ui.progress-ring :percentage="35" :size="72" />
                </div>
            </div>
        </div>
    </section>

    <!-- Alertes -->
    <section class="space-y-4">
        <h2 class="text-lg font-bold text-slate-900 border-b pb-2">6. Alertes (<x-ui.alert />)</h2>
        <div class="space-y-3">
            <x-ui.alert type="success" title="Succès">Le document a été validé avec succès.</x-ui.alert>
            <x-ui.alert type="error" title="Erreur d'authentification">Identifiants incorrects. Veuillez réessayer.</x-ui.alert>
            <x-ui.alert type="warning" title="Attention">8 documents expirent sous 30 jours.</x-ui.alert>
            <x-ui.alert type="info" title="Information">Lien de dépôt envoyé au sous-traitant.</x-ui.alert>
        </div>
    </section>

    <!-- Empty State -->
    <section class="space-y-4">
        <h2 class="text-lg font-bold text-slate-900 border-b pb-2">7. État vide (<x-ui.empty-state />)</h2>
        <x-ui.empty-state icon="document" title="Aucun document à valider" description="Tous les documents sous-traitants sont à jour. Excellent travail !">
            <x-ui.button variant="primary">Ajouter un sous-traitant</x-ui.button>
        </x-ui.empty-state>
    </section>
</body>
</html>
