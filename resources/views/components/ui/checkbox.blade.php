@props([
    'label' => null,
    'name' => null,
    'help' => null,
])

@php
$wireModel = $attributes->wire('model')->value();
$fieldName = $name ?? $wireModel;
@endphp

<div class="flex flex-col">
    <label class="inline-flex items-start gap-2.5 cursor-pointer">
        <input 
            type="checkbox"
            @if($fieldName) id="{{ $fieldName }}" name="{{ $fieldName }}" @endif
            {{ $attributes->merge(['class' => 'mt-0.5 w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500']) }}
        />
        @if ($label || $slot->isNotEmpty())
            <span class="text-sm text-slate-700 font-normal">
                {{ $label ?? $slot }}
            </span>
        @endif
    </label>

    @if ($help)
        <p class="mt-1 text-xs text-slate-500 pl-6">{{ $help }}</p>
    @endif

    @if ($fieldName)
        @error($fieldName)
            <p class="mt-1 text-xs font-medium text-rose-600 pl-6">{{ $message }}</p>
        @enderror
    @endif
</div>
