@props([
    'type' => 'info', // success, error, warning, info
    'title' => null,
    'dismissible' => false,
])

@php
$config = [
    'success' => [
        'bg' => 'bg-emerald-50 border-emerald-200 text-emerald-900',
        'icon' => 'check-circle',
        'icon-color' => 'text-emerald-500',
    ],
    'error' => [
        'bg' => 'bg-rose-50 border-rose-200 text-rose-900',
        'icon' => 'x-circle',
        'icon-color' => 'text-rose-500',
    ],
    'warning' => [
        'bg' => 'bg-amber-50 border-amber-200 text-amber-900',
        'icon' => 'exclamation-triangle',
        'icon-color' => 'text-amber-500',
    ],
    'info' => [
        'bg' => 'bg-blue-50 border-blue-200 text-blue-900',
        'icon' => 'information-circle',
        'icon-color' => 'text-blue-500',
    ],
];

$c = $config[$type] ?? $config['info'];
@endphp

<div 
    x-data="{ show: true }" 
    x-show="show" 
    x-transition
    {{ $attributes->merge(['class' => "p-4 rounded-xl border {$c['bg']} flex items-start gap-3 shadow-2xs"]) }}
>
    <x-ui.icon :name="$c['icon']" class="w-5 h-5 {{ $c['icon-color'] }} shrink-0 mt-0.5" />

    <div class="flex-1 text-sm">
        @if ($title)
            <h4 class="font-semibold mb-0.5">{{ $title }}</h4>
        @endif
        <div class="text-xs leading-relaxed opacity-90">
            {{ $slot }}
        </div>
    </div>

    @if ($dismissible)
        <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600 focus:outline-none">
            <x-ui.icon name="x-mark" class="w-4 h-4" />
        </button>
    @endif
</div>
