@props([
    'icon' => 'folder',
    'title' => 'Aucune donnée disponible',
    'description' => 'Aucun élément ne correspond à votre recherche ou pour le moment.',
    'action' => null,
])

<div {{ $attributes->merge(['class' => 'text-center py-12 px-4 bg-white rounded-xl border border-slate-200 shadow-2xs my-4']) }}>
    <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xs">
        <x-ui.icon :name="$icon" class="w-8 h-8" />
    </div>
    <h3 class="text-base font-semibold text-slate-900 mb-1">{{ $title }}</h3>
    <p class="text-sm text-slate-500 max-w-md mx-auto mb-6 leading-relaxed">{{ $description }}</p>
    
    @if (isset($action) || $slot->isNotEmpty())
        <div class="flex justify-center">
            {{ $action ?? $slot }}
        </div>
    @endif
</div>
