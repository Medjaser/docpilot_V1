<div class="space-y-6">
    @if ($toastMessage)
        <x-ui.alert type="success" title="Opération effectuée" dismissible>
            {{ $toastMessage }}
        </x-ui.alert>
    @endif

    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Tableau de bord Administration</h1>
        <p class="text-sm text-slate-500 mt-0.5">Vue globale de la plateforme SaaS DocPilot (Métriques globales, modèles par défaut & journaux techniques)</p>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.stat-card icon="building-office" color="blue" label="Entreprises actives" :value="$stats['active_companies']" :subtext="$stats['total_companies'] . ' enregistrées au total'" />
        <x-ui.stat-card icon="user" color="purple" label="Utilisateurs actifs" :value="$stats['active_users']" subtext="Comptes entreprises actifs" />
        <x-ui.stat-card icon="user-group" color="green" label="Sous-traitants suivis" :value="$stats['subcontractors_tracked']" subtext="Tous tenants confondus" />
        <x-ui.stat-card icon="document" color="amber" label="Documents stockés" :value="$stats['documents_stored']" :subtext="$stats['storage_gb'] . ' Go de stockage'" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-ui.stat-card icon="crown" color="green" label="Abonnements payants actifs" :value="$stats['active_subscriptions']" subtext="Entreprises au statut Actif" />
        <x-ui.stat-card icon="clock" color="amber" label="Comptes en période d'essai" :value="$stats['trial_accounts']" subtext="Essais gratuits en cours" />
    </div>

    <!-- Charts & Technical Logs Section -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- SVG Monthly Registration Bar Chart (7 cols) -->
        <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Inscriptions d'entreprises par mois</h3>

            @php
                $maxVal = max(1, collect($months)->max('val') ?? 1);
            @endphp
            <div class="h-48 flex items-end justify-between gap-4 pt-4 px-2">
                @foreach ($months as $m)
                    <div class="flex-1 flex flex-col items-center gap-2 h-full justify-end">
                        <span class="text-xs font-bold text-slate-700">{{ $m['val'] }}</span>
                        <div class="w-full bg-blue-600 rounded-t-lg transition-all hover:bg-blue-700" style="height: {{ max(8, ($m['val'] / $maxVal) * 100) }}%"></div>
                        <span class="text-[11px] text-slate-400 font-medium">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Technical / System Logs List (5 cols) -->
        <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center justify-between">
                <span>Journaux techniques & événements</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">Temps réel</span>
            </h3>

            <div class="space-y-3 text-xs max-h-64 overflow-y-auto">
                @forelse ($technicalErrors as $err)
                    <div class="p-3 rounded-xl {{ $err['code'] === 'WARN' ? 'bg-rose-50/50 border border-rose-200/80' : 'bg-slate-50 border border-slate-200' }} space-y-1">
                        <div class="flex items-center justify-between font-mono text-[11px]">
                            <span class="font-bold {{ $err['code'] === 'WARN' ? 'text-rose-700' : 'text-blue-700' }}">{{ $err['code'] }}</span>
                            <span class="text-slate-400">{{ $err['date'] }}</span>
                        </div>
                        <p class="font-medium text-slate-800 break-all">{{ $err['message'] }}</p>
                        <p class="text-[10px] text-slate-400">Tenant : {{ $err['company'] }}</p>
                    </div>
                @empty
                    <p class="text-slate-400 text-center py-4">Aucun événement système récent.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Global Templates Section -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Modèles de dossiers globaux (Standards Plateforme)</h3>
                <p class="text-xs text-slate-500">Ces modèles et catégories réglementaires sont provisionnés automatiquement à la création de chaque entreprise.</p>
            </div>
            <x-ui.button variant="secondary" size="sm" wire:click="syncGlobalDefaults" icon="arrow-path">
                Synchroniser les entreprises
            </x-ui.button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($globalTemplates as $tpl)
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-2">
                    <h4 class="text-sm font-bold text-slate-900">{{ $tpl['name'] }}</h4>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($tpl['items'] as $item)
                            <span class="px-2 py-0.5 rounded bg-white border border-slate-200 text-[11px] text-slate-700 font-medium">
                                {{ $item }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
