@props([
    'headers' => [], // [['key' => 'name', 'label' => 'Raison sociale', 'sortable' => true], ...]
    'sortColumn' => null,
    'sortDirection' => 'asc',
])

<div {{ $attributes->merge(['class' => 'w-full overflow-hidden bg-white rounded-xl border border-slate-200 shadow-xs']) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50/80 text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                <tr>
                    {{ $headerSlot ?? '' }}
                    @if (count($headers) > 0)
                        @foreach ($headers as $header)
                            @php
                                $key = is_array($header) ? ($header['key'] ?? null) : null;
                                $label = is_array($header) ? ($header['label'] ?? $header) : $header;
                                $sortable = is_array($header) ? ($header['sortable'] ?? false) : false;
                                $align = is_array($header) ? ($header['align'] ?? 'left') : 'left';
                            @endphp
                            <th scope="col" class="px-5 py-3.5 text-{{ $align }}">
                                @if ($sortable && $key)
                                    <button 
                                        type="button" 
                                        wire:click="sortBy('{{ $key }}')" 
                                        class="group inline-flex items-center gap-1.5 font-semibold text-slate-600 hover:text-slate-900 focus:outline-none cursor-pointer"
                                    >
                                        <span>{{ $label }}</span>
                                        <span class="text-slate-400 group-hover:text-slate-600">
                                            @if ($sortColumn === $key)
                                                @if ($sortDirection === 'asc')
                                                    <x-ui.icon name="arrow-up" class="w-3.5 h-3.5 text-blue-600" />
                                                @else
                                                    <x-ui.icon name="arrow-down" class="w-3.5 h-3.5 text-blue-600" />
                                                @endif
                                            @else
                                                <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity" />
                                            @endif
                                        </span>
                                    </button>
                                @else
                                    <span>{{ $label }}</span>
                                @endif
                            </th>
                        @endforeach
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if (isset($footer))
        <div class="px-5 py-3.5 border-t border-slate-200 bg-slate-50/50">
            {{ $footer }}
        </div>
    @endif
</div>
