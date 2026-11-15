@props([
    'variant' => 'primary', // primary, secondary, danger, success, ghost
    'size' => 'md', // sm, md
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'left',
    'loading' => false,
    'write' => null, // action d'écriture ? par défaut : submit ou variante primary/danger/success
])

@php
$baseClasses = 'inline-flex items-center justify-center font-medium rounded-xl transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer';

$sizeClasses = [
    'sm' => 'px-3 py-1.5 text-xs gap-1.5',
    'md' => 'px-4 py-2.5 text-sm gap-2',
];

$variantClasses = [
    'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 shadow-sm active:bg-blue-800',
    'secondary' => 'bg-white text-blue-600 border border-blue-600 hover:bg-blue-50 focus:ring-blue-500 shadow-sm',
    'danger' => 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500 shadow-sm',
    'success' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 shadow-sm',
    'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:ring-slate-400',
];

$classes = $baseClasses . ' ' . ($sizeClasses[$size] ?? $sizeClasses['md']) . ' ' . ($variantClasses[$variant] ?? $variantClasses['primary']);

// Entreprise suspendue : les actions d'écriture sont désactivées (le serveur les refuse aussi)
$isWrite = $write ?? ($type === 'submit' || in_array($variant, ['primary', 'danger', 'success'], true));
$readOnly = ! $href && $isWrite && (auth()->user()?->company?->isReadOnly() ?? false);
if ($readOnly) {
    $attributes = $attributes->merge(['disabled' => true, 'title' => 'Compte suspendu : lecture seule']);
}
@endphp

@if ($href)
    <a href="{!! $href !!}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon && $iconPosition === 'left')
            <x-ui.icon :name="$icon" class="{{ $size === 'sm' ? 'w-4 h-4' : 'w-5 h-5' }}" />
        @endif
        <span>{{ $slot }}</span>
        @if ($icon && $iconPosition === 'right')
            <x-ui.icon :name="$icon" class="{{ $size === 'sm' ? 'w-4 h-4' : 'w-5 h-5' }}" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($loading)
            <svg wire:loading class="animate-spin -ml-1 mr-2 h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        @if ($icon && $iconPosition === 'left')
            <x-ui.icon :name="$icon" class="{{ $size === 'sm' ? 'w-4 h-4' : 'w-5 h-5' }}" wire:loading.remove />
        @endif
        <span>{{ $slot }}</span>
        @if ($icon && $iconPosition === 'right')
            <x-ui.icon :name="$icon" class="{{ $size === 'sm' ? 'w-4 h-4' : 'w-5 h-5' }}" />
        @endif
    </button>
@endif
