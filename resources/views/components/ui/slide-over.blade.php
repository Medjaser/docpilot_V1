@props([
    'name' => 'slide-over',
    'title' => null,
    'subtitle' => null,
])

<div
    x-data="{ show: false }"
    x-on:open-slide-over.window="if ($event.detail.name === '{{ $name }}') show = true"
    x-on:close-slide-over.window="if ($event.detail.name === '{{ $name }}') show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    class="fixed inset-0 z-50 overflow-hidden"
    style="display: none;"
>
    <!-- Backdrop -->
    <div 
        x-show="show" 
        x-transition:enter="ease-in-out duration-300" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="ease-in-out duration-300" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0" 
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
        @click="show = false"
    ></div>

    <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex">
        <!-- Panel -->
        <div 
            x-show="show" 
            x-transition:enter="transform transition ease-in-out duration-300 sm:duration-400" 
            x-transition:enter-start="translate-x-full" 
            x-transition:enter-end="translate-x-0" 
            x-transition:leave="transform transition ease-in-out duration-300 sm:duration-400" 
            x-transition:leave-start="translate-x-0" 
            x-transition:leave-end="translate-x-full" 
            class="w-screen max-w-md bg-white shadow-2xl flex flex-col justify-between"
        >
            <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <div>
                    @if ($title)
                        <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
                    @endif
                    @if ($subtitle)
                        <p class="text-xs text-slate-500 mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100">
                    <x-ui.icon name="x-mark" class="w-5 h-5" />
                </button>
            </div>

            <div class="p-6 flex-1 overflow-y-auto space-y-4">
                {{ $slot }}
            </div>

            @if (isset($footer))
                <div class="p-6 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
