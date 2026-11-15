@props([
    'tabs' => [], // [['id' => 'active', 'label' => 'Actifs', 'count' => 25], ...]
    'active' => '',
])

<div {{ $attributes->merge(['class' => 'border-b border-slate-200']) }}>
    <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
        @foreach ($tabs as $tab)
            @php
                $tabId = is_array($tab) ? ($tab['id'] ?? $tab['label']) : $tab;
                $tabLabel = is_array($tab) ? ($tab['label'] ?? $tabId) : $tab;
                $tabCount = is_array($tab) ? ($tab['count'] ?? null) : null;
                $isActive = $active == $tabId;
            @endphp
            <button
                type="button"
                @click="$dispatch('tab-changed', { tab: '{{ $tabId }}' })"
                class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 cursor-pointer {{ $isActive ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
            >
                <span>{{ $tabLabel }}</span>
                @if ($tabCount !== null)
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $isActive ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $tabCount }}
                    </span>
                @endif
            </button>
        @endforeach
    </nav>
</div>
