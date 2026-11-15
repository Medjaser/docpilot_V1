@props([
    'title' => null,
    'subtitle' => null,
    'actions' => null,
    'padding' => 'p-6',
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden']) }}>
    @if ($title || $subtitle || isset($actions))
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
            <div>
                @if ($title)
                    <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-slate-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @if (isset($actions))
                <div class="flex items-center gap-2">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</div>
