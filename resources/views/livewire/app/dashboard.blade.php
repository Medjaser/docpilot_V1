<div class="space-y-6">
    <!-- Toast notification if reminder triggered -->
    @if ($toastMessage)
        <div class="fixed bottom-5 right-5 z-50 animate-bounce">
            <x-ui.alert type="success" title="Relance effectuée" dismissible>
                {{ $toastMessage }}
            </x-ui.alert>
        </div>
    @endif

    <!-- Header & Period Selector -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Bonjour {{ $firstName }} 👋</h1>
            <p class="text-sm text-slate-500 mt-0.5">Voici l'état de conformité des sous-traitants de {{ $companyName }}</p>
        </div>

        <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-xl border border-slate-200 shadow-2xs">
            <x-ui.icon name="calendar" class="w-4 h-4 text-slate-400" />
            <select wire:model.live="period" class="text-xs font-semibold text-slate-700 bg-transparent focus:outline-none cursor-pointer">
                <option value="2026-01-01 - 2026-12-31">01 jan. 2026 – 31 déc. 2026</option>
                <option value="2026-01-01 - 2026-06-30">Semestre 1 - 2026</option>
                <option value="2026-07-01 - 2026-12-31">Semestre 2 - 2026</option>
            </select>
        </div>
    </div>

    <!-- Line 1: 4 Main Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.stat-card 
            icon="user-group" 
            color="blue" 
            label="Sous-traitants suivis" 
            :value="$total" 
            :subtext="'+'.$newThisMonth.' ce mois-ci'" 
        />
        <x-ui.stat-card 
            icon="check-circle" 
            color="green" 
            label="Dossiers complets" 
            :value="$byBadge['complete']" 
            :subtext="$percentOf($byBadge['complete']).' % du total'" 
        />
        <x-ui.stat-card 
            icon="exclamation-circle" 
            color="red" 
            label="Dossiers incomplets" 
            :value="$byBadge['incomplete']" 
            :subtext="$percentOf($byBadge['incomplete']).' % du total'" 
        />
        <x-ui.stat-card 
            icon="x-circle" 
            color="red" 
            label="Dossiers critiques" 
            :value="$byBadge['critical']" 
            :subtext="$percentOf($byBadge['critical']).' % du total'" 
        />
    </div>

    <!-- Line 2: 5 Small Clickable Stat Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <a href="{{ route('subcontractors.index') }}?status=complete" class="block">
            <x-ui.stat-card icon="check" color="green" label="Documents valides" :value="$donut['valid']['count']" clickable />
        </a>
        <a href="{{ route('documents.pending') }}" class="block">
            <x-ui.stat-card icon="clock" color="purple" label="À valider" :value="$donut['pending']['count']" clickable />
        </a>
        <a href="{{ route('deadlines.index') }}?tab=7_days" class="block">
            <x-ui.stat-card icon="exclamation-triangle" color="amber" label="Bientôt expirés" :value="$donut['expiring_soon']['count']" clickable />
        </a>
        <a href="{{ route('deadlines.index') }}?tab=expired" class="block">
            <x-ui.stat-card icon="x-circle" color="red" label="Expirés" :value="$donut['expired']['count']" clickable />
        </a>
        <a href="{{ route('subcontractors.index') }}?status=incomplete" class="block">
            <x-ui.stat-card icon="folder" color="slate" label="Manquants" :value="$donut['missing']['count']" clickable />
        </a>
    </div>

    <!-- Bottom Section: 2 Columns Grid (Desktop) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Bottom Left: Upcoming Deadlines Card (8 cols) -->
        <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 p-5 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <x-ui.icon name="clock" class="w-5 h-5 text-blue-600" />
                        <span>Prochaines échéances</span>
                    </h3>
                    <a href="{{ route('deadlines.index') }}" class="text-xs font-semibold text-blue-600 hover:underline flex items-center gap-1">
                        <span>Voir toutes les échéances</span>
                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 font-semibold border-b border-slate-100 pb-2">
                                <th class="pb-2.5 font-medium">Sous-traitant</th>
                                <th class="pb-2.5 font-medium">Document</th>
                                <th class="pb-2.5 font-medium">Échéance</th>
                                <th class="pb-2.5 font-medium">Statut</th>
                                <th class="pb-2.5 font-medium text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($deadlines as $item)
                                <tr wire:key="deadline-{{ $loop->index }}">
                                    <td class="py-3 font-semibold text-slate-900">{{ $item['subcontractor'] }}</td>
                                    <td class="py-3 text-slate-600 truncate max-w-[160px]">{{ $item['document'] }}</td>
                                    <td class="py-3 text-slate-500 font-medium">{{ $item['due_date'] }}</td>
                                    <td class="py-3">
                                        <x-ui.badge :status="$item['status']" size="sm" />
                                    </td>
                                    <td class="py-3 text-right">
                                        <x-ui.button 
                                            variant="secondary" 
                                            size="sm" 
                                            wire:click="triggerReminder({{ $item['subcontractor_id'] }})"
                                        >
                                            Relancer
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400">Aucune échéance à venir.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bottom Right: Recent Activities + Document Distribution Donut Chart (5 cols) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Document Status Distribution (Pure SVG Donut Chart) -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs">
                <h3 class="text-base font-bold text-slate-900 mb-4 pb-2 border-b border-slate-100">
                    Répartition des documents par statut
                </h3>

                <div class="flex items-center gap-6">
                    <!-- SVG Donut -->
                    <div class="relative w-36 h-36 shrink-0">
                        <svg viewBox="0 0 36 36" class="w-full h-full transform -rotate-90">
                            <!-- Background stroke circle -->
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#F1F5F9" stroke-width="3.8"></circle>
                            @foreach ($donut as $slice)
                                @if ($slice['percent'] > 0)
                                    <circle cx="18" cy="18" r="15.915" fill="none" stroke="{{ $slice['color'] }}" stroke-width="3.8" stroke-dasharray="{{ $slice['percent'] }}, 100" stroke-dashoffset="{{ $slice['offset'] }}"></circle>
                                @endif
                            @endforeach
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                            <span class="text-xl font-extrabold text-slate-900 leading-tight">{{ $documentsTotal }}</span>
                            <span class="text-[10px] text-slate-400 font-medium">documents</span>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="space-y-1.5 text-xs flex-1">
                        @foreach ($donut as $slice)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $slice['dot'] }}"></span>
                                    <span class="text-slate-600">{{ $slice['label'] }}</span>
                                </div>
                                <span class="font-bold text-slate-900">{{ $slice['count'] }} ({{ $slice['percent'] }}%)</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Activities Timeline -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs">
                <h3 class="text-base font-bold text-slate-900 mb-4 pb-2 border-b border-slate-100">
                    Dernières activités
                </h3>
                <div class="space-y-4">
                    @forelse ($activities as $act)
                        <div class="flex items-start gap-3 text-xs">
                            <div class="w-7 h-7 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                <x-ui.icon :name="$act['icon']" class="w-3.5 h-3.5" />
                            </div>
                            <div class="flex-1">
                                <p class="text-slate-800 font-medium leading-tight">{{ $act['text'] }}</p>
                                <span class="text-[10px] text-slate-400 block mt-0.5">{{ $act['time'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Aucune activité récente.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
