@props([
    'percentage' => 0,
    'showText' => true,
    'size' => 'md',
])

@php
$pct = max(0, min(100, (int)$percentage));

if ($pct >= 75) {
    $barColor = 'bg-emerald-500';
    $textColor = 'text-emerald-700';
} elseif ($pct >= 50) {
    $barColor = 'bg-amber-500';
    $textColor = 'text-amber-700';
} else {
    $barColor = 'bg-rose-500';
    $textColor = 'text-rose-700';
}

$heights = [
    'sm' => 'h-1.5',
    'md' => 'h-2.5',
    'lg' => 'h-4',
];
@endphp

<div class="w-full flex items-center gap-3">
    <div class="flex-1 bg-slate-100 rounded-full overflow-hidden {{ $heights[$size] ?? 'h-2.5' }}">
        <div class="h-full {{ $barColor }} rounded-full transition-all duration-500 ease-out" style="width: {{ $pct }}%"></div>
    </div>
    @if ($showText)
        <span class="text-xs font-semibold {{ $textColor }} w-9 text-right shrink-0">{{ $pct }}%</span>
    @endif
</div>
