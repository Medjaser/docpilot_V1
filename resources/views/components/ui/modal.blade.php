@props([
    'name' => 'modal',
    'title' => null,
    'maxWidth' => '2xl',
])

@php
$maxWidths = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '4xl' => 'sm:max-w-4xl',
    'full' => 'sm:max-w-6xl',
];
$widthClass = $maxWidths[$maxWidth] ?? 'sm:max-w-2xl';
@endphp

<div
    x-data="{ show: false }"
    x-on:open-modal.window="if ($event.detail.name === '{{ $name }}') show = true"
    x-on:close-modal.window="if ($event.detail.name === '{{ $name }}') show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
    style="display: none;"
>
    <!-- Overlay -->
    <div 
        x-show="show" 
        x-transition:enter="ease-out duration-200" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="ease-in duration-150" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0" 
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
        @click="show = false"
    ></div>

    <!-- Panel -->
    <div 
        x-show="show" 
        x-transition:enter="ease-out duration-200" 
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
        x-transition:leave="ease-in duration-150" 
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
        class="bg-white rounded-2xl overflow-hidden shadow-2xl transform transition-all w-full {{ $widthClass }} z-50 border border-slate-100 my-8"
    >
        @if ($title)
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100">
                    <x-ui.icon name="x-mark" class="w-5 h-5" />
                </button>
            </div>
        @endif

        <div class="px-6 py-5 max-h-[75vh] overflow-y-auto">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
