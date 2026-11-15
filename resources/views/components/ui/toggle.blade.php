@props([
    'label' => null,
    'name' => null,
    'help' => null,
    'checked' => false,
])

@php
$wireModel = $attributes->wire('model')->value();
$fieldName = $name ?? $wireModel;
@endphp

<div class="flex flex-col">
    <label class="inline-flex items-center gap-3 cursor-pointer">
        <div class="relative inline-block w-11 h-6 select-none" x-data="{ checked: @json($checked) }">
            <input 
                type="checkbox"
                @if($fieldName) id="{{ $fieldName }}" name="{{ $fieldName }}" @endif
                x-model="checked"
                {{ $attributes->merge(['class' => 'sr-only peer']) }}
            />
            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
        </div>
        @if ($label || $slot->isNotEmpty())
            <span class="text-sm font-medium text-slate-700 select-none">
                {{ $label ?? $slot }}
            </span>
        @endif
    </label>

    @if ($help)
        <p class="mt-1 text-xs text-slate-500 pl-14">{{ $help }}</p>
    @endif
</div>
