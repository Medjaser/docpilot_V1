<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Sous-traitants</h1>
            <p class="text-sm text-slate-500 mt-0.5">Gérez l'ensemble des entreprises sous-traitantes et leur conformité</p>
        </div>

        <div class="flex items-center gap-3">
            <x-ui.button variant="secondary" wire:click="exportCsv" icon="download">
                Exporter CSV
            </x-ui.button>

            <x-ui.button variant="primary" href="{{ route('subcontractors.create') }}" icon="plus">
                Ajouter un sous-traitant
            </x-ui.button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-6">
            <button 
                type="button" 
                wire:click="$set('tab', 'active')" 
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $tab === 'active' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
            >
                <span>Actifs</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $tab === 'active' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $activeCount }}
                </span>
            </button>

            <button 
                type="button" 
                wire:click="$set('tab', 'archived')" 
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $tab === 'archived' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
            >
                <span>Archivés</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $tab === 'archived' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $archivedCount }}
                </span>
            </button>
        </nav>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <!-- Search field with 300ms debounce -->
            <div class="lg:col-span-2 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <x-ui.icon name="search" class="w-4 h-4" />
                </div>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Rechercher nom, contact, ville..." 
                    class="w-full h-9 pl-9 pr-3 text-xs rounded-lg border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <!-- Select Statut -->
            <select wire:model.live="status" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white text-slate-700 focus:ring-2 focus:ring-blue-500">
                <option value="">Tous les statuts</option>
                <option value="complete">Dossier complet</option>
                <option value="incomplete">Dossier incomplet</option>
                <option value="critical">Dossier critique</option>
            </select>

            <!-- Select Activité -->
            <select wire:model.live="activity" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white text-slate-700 focus:ring-2 focus:ring-blue-500">
                <option value="">Toutes les activités</option>
                @foreach ($activities as $value)
                    <option value="{{ $value }}">{{ $value }}</option>
                @endforeach
            </select>

            <!-- Select Ville -->
            <select wire:model.live="city" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white text-slate-700 focus:ring-2 focus:ring-blue-500">
                <option value="">Toutes les villes</option>
                @foreach ($cities as $value)
                    <option value="{{ $value }}">{{ $value }}</option>
                @endforeach
            </select>

            <!-- Select Responsable -->
            <select wire:model.live="manager" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white text-slate-700 focus:ring-2 focus:ring-blue-500">
                <option value="">Tous les responsables</option>
                @foreach ($managers as $userId => $userName)
                    <option value="{{ $userId }}">{{ $userName }}</option>
                @endforeach
            </select>
        </div>

        @if ($search || $status || $activity || $city || $manager || $tag)
            <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-100">
                <span class="text-slate-500">Filtres actifs</span>
                <button type="button" wire:click="resetFilters" class="text-blue-600 hover:underline font-medium">
                    Réinitialiser les filtres
                </button>
            </div>
        @endif
    </div>

    <!-- Subcontractors Table -->
    @if ($subcontractors->isEmpty())
        <x-ui.empty-state 
            icon="user-group" 
            title="Aucun sous-traitant pour le moment" 
            description="Aucune entreprise sous-traitante ne correspond à vos critères de recherche."
        >
            <x-ui.button variant="primary" href="{{ route('subcontractors.create') }}" icon="plus">
                Ajouter un sous-traitant
            </x-ui.button>
        </x-ui.empty-state>
    @else
        <!-- Desktop Table View -->
        <div class="hidden md:block bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 uppercase tracking-wider">
                    <tr>
                        <th class="p-4 w-10">
                            <input type="checkbox" class="rounded border-slate-300 text-blue-600" />
                        </th>
                        <th class="p-4">
                            <button type="button" wire:click="sortBy('name')" class="font-semibold text-slate-700 hover:text-blue-600 flex items-center gap-1">
                                <span>Raison sociale</span>
                                @if($sortColumn === 'name') <x-ui.icon :name="$sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" class="w-3 h-3 text-blue-600" /> @endif
                            </button>
                        </th>
                        <th class="p-4">Contact</th>
                        <th class="p-4">Ville</th>
                        <th class="p-4">Responsable</th>
                        <th class="p-4">
                            <button type="button" wire:click="sortBy('completeness')" class="font-semibold text-slate-700 hover:text-blue-600 flex items-center gap-1">
                                <span>Complétude</span>
                                @if($sortColumn === 'completeness') <x-ui.icon :name="$sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" class="w-3 h-3 text-blue-600" /> @endif
                            </button>
                        </th>
                        <th class="p-4">Statut</th>
                        <th class="p-4">
                            <button type="button" wire:click="sortBy('next_deadline')" class="font-semibold text-slate-700 hover:text-blue-600 flex items-center gap-1">
                                <span>Prochaine échéance</span>
                                @if($sortColumn === 'next_deadline') <x-ui.icon :name="$sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" class="w-3 h-3 text-blue-600" /> @endif
                            </button>
                        </th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($subcontractors as $sub)
                        <tr wire:key="subcontractor-{{ $sub['id'] }}" class="hover:bg-slate-50/80 transition-colors">
                            <td class="p-4">
                                <input type="checkbox" wire:model="selected" value="{{ $sub['id'] }}" class="rounded border-slate-300 text-blue-600" />
                            </td>
                            <td class="p-4 font-medium">
                                <a href="{{ route('subcontractors.show', $sub['id']) }}" class="flex items-center gap-3 hover:text-blue-600">
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($sub['name'], 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 text-sm leading-tight">{{ $sub['name'] }}</p>
                                        <p class="text-[11px] text-slate-500 mt-0.5 leading-tight">{{ $sub['activity'] }}</p>
                                    </div>
                                </a>
                            </td>
                            <td class="p-4">
                                <p class="font-medium text-slate-800 leading-tight">{{ $sub['contact_name'] }}</p>
                                <p class="text-[11px] text-slate-500 leading-tight">{{ $sub['contact_email'] }}</p>
                            </td>
                            <td class="p-4 text-slate-600">{{ $sub['city'] }}</td>
                            <td class="p-4 text-slate-600">{{ $sub['manager'] }}</td>
                            <td class="p-4 w-36">
                                <x-ui.progress :percentage="$sub['completeness']" size="sm" />
                            </td>
                            <td class="p-4">
                                <x-ui.badge :status="$sub['status']" size="sm" />
                            </td>
                            <td class="p-4 text-slate-600 font-medium">{{ $sub['next_deadline'] }}</td>
                            <td class="p-4 text-right">
                                <div x-data="{ menu: false }" class="relative inline-block text-left">
                                    <button @click="menu = !menu" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100">
                                        <x-ui.icon name="ellipsis-vertical" class="w-4 h-4" />
                                    </button>
                                    <div 
                                        x-show="menu" 
                                        @click.away="menu = false" 
                                        x-transition 
                                        class="absolute right-0 mt-1 w-36 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-20 text-xs text-left"
                                        style="display: none;"
                                    >
                                        <a href="{{ route('subcontractors.show', $sub['id']) }}" class="flex items-center gap-2 px-3 py-1.5 text-slate-700 hover:bg-slate-50">
                                            <x-ui.icon name="eye" class="w-3.5 h-3.5 text-slate-400" />
                                            <span>Voir la fiche</span>
                                        </a>
                                        <a href="{{ route('subcontractors.edit', $sub['id']) }}" class="flex items-center gap-2 px-3 py-1.5 text-slate-700 hover:bg-slate-50">
                                            <x-ui.icon name="pencil" class="w-3.5 h-3.5 text-slate-400" />
                                            <span>Modifier</span>
                                        </a>
                                        <button type="button" @click="menu = false" wire:click="toggleArchive({{ $sub['id'] }})" class="flex w-full items-center gap-2 px-3 py-1.5 {{ $tab === 'archived' ? 'text-emerald-600 hover:bg-emerald-50' : 'text-rose-600 hover:bg-rose-50' }}">
                                            <x-ui.icon name="trash" class="w-3.5 h-3.5 {{ $tab === 'archived' ? 'text-emerald-500' : 'text-rose-500' }}" />
                                            <span>{{ $tab === 'archived' ? 'Restaurer' : 'Archiver' }}</span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Pagination Bar -->
            <div class="px-5 py-3.5 border-t border-slate-200 bg-slate-50/50 flex items-center justify-between text-xs text-slate-500">
                <span>Affichage de {{ $subcontractors->firstItem() }} à {{ $subcontractors->lastItem() }} sur {{ $subcontractors->total() }} résultats</span>
                <div class="flex items-center gap-1">
                    @for ($page = 1; $page <= $subcontractors->lastPage(); $page++)
                        <button
                            type="button"
                            wire:click="gotoPage({{ $page }})"
                            @if ($page === $subcontractors->currentPage()) aria-current="page" @endif
                            class="px-2.5 py-1 rounded border font-medium {{ $page === $subcontractors->currentPage() ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}"
                        >{{ $page }}</button>
                    @endfor
                </div>
            </div>
        </div>

        <!-- Mobile Responsive Cards Stack -->
        <div class="md:hidden space-y-4">
            @foreach ($subcontractors as $sub)
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-2xs space-y-3">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 font-bold text-sm flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($sub['name'], 0, 2)) }}
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 text-sm">{{ $sub['name'] }}</h3>
                                <p class="text-xs text-slate-500">{{ $sub['activity'] }} • {{ $sub['city'] }}</p>
                            </div>
                        </div>
                        <x-ui.badge :status="$sub['status']" size="sm" />
                    </div>

                    <div class="space-y-1.5 text-xs text-slate-600">
                        <p><strong>Contact :</strong> {{ $sub['contact_name'] }} ({{ $sub['contact_email'] }})</p>
                        <p><strong>Responsable :</strong> {{ $sub['manager'] }}</p>
                        <p><strong>Échéance :</strong> {{ $sub['next_deadline'] }}</p>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                        <div class="w-32">
                            <x-ui.progress :percentage="$sub['completeness']" size="sm" />
                        </div>
                        <x-ui.button variant="secondary" href="{{ route('subcontractors.show', $sub['id']) }}" size="sm">
                            Voir la fiche
                        </x-ui.button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
