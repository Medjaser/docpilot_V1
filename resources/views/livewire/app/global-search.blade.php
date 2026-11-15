<div class="relative w-full max-w-md hidden sm:block" x-data="{ open: false }" @click.away="open = false" @keydown.escape="open = false">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
        <x-ui.icon name="search" class="w-4 h-4" />
    </div>
    <input 
        type="text" 
        wire:model.live.debounce.300ms="query"
        @focus="open = true"
        @input="open = true"
        placeholder="Rechercher un sous-traitant, un document..." 
        aria-label="Recherche globale"
        class="w-full h-9 pl-9 pr-12 text-xs rounded-lg border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-blue-500 transition-all"
    />
    <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none">
        <kbd class="px-1.5 py-0.5 text-[10px] font-semibold text-slate-400 bg-white border border-slate-200 rounded-md shadow-2xs">⌘K</kbd>
    </div>

    @if (mb_strlen($term) >= 2)
        <div x-show="open" x-transition class="absolute left-0 right-0 mt-2 bg-white rounded-xl shadow-xl border border-slate-200 py-2 z-50 text-xs max-h-96 overflow-y-auto">
            @if ($subcontractors->isEmpty() && $documents->isEmpty())
                <p class="px-4 py-3 text-slate-400">Aucun résultat pour « {{ $term }} ».</p>
            @endif

            @if ($subcontractors->isNotEmpty())
                <p class="px-4 pt-1 pb-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Sous-traitants</p>
                @foreach ($subcontractors as $subcontractor)
                    <a wire:key="search-sub-{{ $subcontractor->id }}" href="{{ route('subcontractors.show', $subcontractor->id) }}" class="flex items-center justify-between px-4 py-2 hover:bg-blue-50/50">
                        <span class="font-semibold text-slate-900">{{ $subcontractor->legal_name }}</span>
                        <span class="text-slate-400">{{ $subcontractor->city }}</span>
                    </a>
                @endforeach
            @endif

            @if ($documents->isNotEmpty())
                <p class="px-4 pt-2 pb-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Documents</p>
                @foreach ($documents as $document)
                    <a wire:key="search-doc-{{ $document->id }}" href="{{ route('documents.review', $document->id) }}" class="flex items-center justify-between px-4 py-2 hover:bg-blue-50/50">
                        <span class="font-semibold text-slate-900">{{ $document->documentType->name }}</span>
                        <span class="text-slate-400">{{ $document->subcontractor->legal_name }}</span>
                    </a>
                @endforeach
            @endif
        </div>
    @endif
</div>
