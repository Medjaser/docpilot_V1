@props([
    'percentage' => 0,
    'size' => 64, // px size
    'strokeWidth' => 6,
])

@php
$pct = max(0, min(100, (int)$percentage));
$radius = ($size - $strokeWidth) / 2;
$circumference = 2 * M_PI * $radius;
$dashoffset = $circumference - ($pct / 100) * $circumference;

if ($pct >= 75) {
    $strokeColor = 'stroke-emerald-500';
    $textColor = 'text-emerald-700';
} elseif ($pct >= 50) {
    $strokeColor = 'stroke-amber-500';
    $textColor = 'text-amber-700';
} else {
    $strokeColor = 'stroke-rose-500';
    $textColor = 'text-rose-700';
}
@endphp

<div class="relative inline-flex items-center justify-center shrink-0" style="width: {{ $size }}px; height: {{ $size }}px;">
    <svg width="{{ $size }}" height="{{ $size }}" class="transform -rotate-90">
        <!-- Background circle -->
        <circle 
            cx="{{ $size / 2 }}" 
            cy="{{ $size / 2 }}" 
            r="{{ $radius }}" 
            class="stroke-slate-100 fill-none" 
            stroke-width="{{ $strokeWidth }}" 
        />
        <!-- Progress circle -->
        <circle 
            cx="{{ $size / 2 }}" 
            cy="{{ $size / 2 }}" 
            r="{{ $radius }}" 
            class="{{ $strokeColor }} fill-none transition-all duration-700 ease-out" 
            stroke-width="{{ $strokeWidth }}"
            stroke-dasharray="{{ $circumference }}"
            stroke-dashoffset="{{ $dashoffset }}"
            stroke-linecap="round"
        />
    </svg>
    <span class="absolute text-xs font-bold {{ $textColor }}">{{ $pct }}%</span>
</div>
