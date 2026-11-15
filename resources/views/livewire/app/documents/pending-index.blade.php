<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Documents à valider</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500 text-white shadow-2xs">
                    {{ $totalCount }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-0.5">Examinez et validez les pièces transmises par vos sous-traitants</p>
        </div>
        <div class="flex items-center gap-3">
            <x-ui.button variant="secondary" icon="download" wire:click="exportCsv">
                Exporter CSV
            </x-ui.button>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <x-ui.icon name="search" class="w-4 h-4" />
                </div>
                <input 
                    type="text" 
                    wire:model.live="search" 
                    placeholder="Rechercher document ou sous-traitant..." 
                    class="w-full h-9 pl-9 pr-3 text-xs rounded-lg border border-slate-300"
                />
            </div>

            <select wire:model.live="subcontractor" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white">
                <option value="">Tous les sous-traitants</option>
                @foreach ($subcontractorsList as $subName)
                    <option value="{{ $subName }}">{{ $subName }}</option>
                @endforeach
            </select>

            <select wire:model.live="category" class="h-9 px-3 text-xs rounded-lg border border-slate-300 bg-white">
                <option value="">Toutes les catégories</option>
                @foreach ($categoriesList as $catName)
                    <option value="{{ $catName }}">{{ $catName }}</option>
                @endforeach
            </select>

            @if ($search || $subcontractor || $category)
                <button type="button" wire:click="resetFilters" class="text-xs text-blue-600 hover:underline font-semibold self-center">
                    Réinitialiser les filtres
                </button>
            @endif
        </div>
    </div>

    <!-- Table -->
    @if ($documents->isEmpty())
        <x-ui.empty-state 
            icon="check-circle" 
            title="Tout est à jour !" 
            description="Aucun document n'est en attente d'examen pour le moment."
        />
    @else
        <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200 uppercase tracking-wider">
                    <tr>
                        <th class="p-4">Sous-traitant</th>
                        <th class="p-4">Type de document</th>
                        <th class="p-4">Date de dépôt</th>
                        <th class="p-4">Date d'expiration</th>
                        <th class="p-4">Déposé par</th>
                        <th class="p-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($documents as $doc)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="p-4 font-bold text-slate-900 text-sm">
                                <a href="{{ route('subcontractors.show', $doc['subcontractor_id']) }}" class="hover:text-blue-600 hover:underline">
                                    {{ $doc['subcontractor_name'] }}
                                </a>
                            </td>
                            <td class="p-4 font-medium text-slate-800 flex items-center gap-2">
                                <x-ui.icon name="document" class="w-4 h-4 text-purple-600 shrink-0" />
                                <span>{{ $doc['document_type'] }}</span>
                                <span class="text-[10px] font-mono bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">{{ $doc['version'] }}</span>
                            </td>
                            <td class="p-4 text-slate-500">{{ $doc['submitted_at'] }}</td>
                            <td class="p-4 text-slate-600 font-medium">{{ $doc['expires_at'] }}</td>
                            <td class="p-4 text-slate-500">{{ $doc['submitted_by'] }}</td>
                            <td class="p-4 text-right">
                                <x-ui.button variant="secondary" href="{{ route('documents.review', $doc['id']) }}" size="sm" icon="eye">
                                    Examiner
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($paginator->hasPages())
                <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                    {{ $paginator->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
