@props([
    'variant' => 'dark', // 'dark' (for light backgrounds) or 'light' (for dark backgrounds)
    'size' => 'md',
])

@php
$iconSizes = [
    'sm' => 'w-7 h-7',
    'md' => 'w-9 h-9',
    'lg' => 'w-11 h-11',
];

$textSizes = [
    'sm' => 'text-lg',
    'md' => 'text-xl',
    'lg' => 'text-2xl',
];

$textColor = $variant === 'light' ? 'text-white' : 'text-slate-900';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2.5 font-bold tracking-tight select-none']) }}>
    <div class="bg-blue-600 text-white p-2 rounded-xl shadow-sm flex items-center justify-center">
        <x-ui.icon name="document" class="{{ $iconSizes[$size] ?? 'w-9 h-9' }}" />
    </div>
    <span class="{{ $textSizes[$size] ?? 'text-xl' }} {{ $textColor }}">
        Doc<span class="text-blue-600">Pilot</span>
    </span>
</div>
