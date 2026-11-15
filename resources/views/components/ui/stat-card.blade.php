@props([
    'icon' => 'document',
    'color' => 'blue', // blue, green, amber, red, purple
    'label' => '',
    'value' => '0',
    'subtext' => null,
    'clickable' => false,
])

@php
$colors = [
    'blue' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100'],
    'green' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100'],
    'amber' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100'],
    'red' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'border' => 'border-rose-100'],
    'purple' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'border' => 'border-purple-100'],
    'slate' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'border' => 'border-slate-200'],
];

$c = $colors[$color] ?? $colors['blue'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 p-5 shadow-xs transition-all ' . ($clickable ? 'hover:shadow-md hover:border-slate-300 cursor-pointer' : '')]) }}>
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl {{ $c['bg'] }} {{ $c['text'] }} flex items-center justify-center shrink-0">
            <x-ui.icon :name="$icon" class="w-6 h-6" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-medium text-slate-500 truncate">{{ $label }}</p>
            <p class="text-2xl font-bold text-slate-900 mt-0.5 tracking-tight">{{ $value }}</p>
            @if ($subtext)
                <p class="text-xs text-slate-500 mt-1 flex items-center gap-1 truncate">
                    <span>{{ $subtext }}</span>
                </p>
            @endif
        </div>
    </div>
</div>
