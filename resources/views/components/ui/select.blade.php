@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'icon' => null,
    'options' => [], // Key-value array ['val' => 'Label'] or simple array
    'placeholder' => 'Sélectionner...',
    'help' => null,
])

@php
$wireModel = $attributes->wire('model')->value();
$fieldName = $name ?? $wireModel;
@endphp

<div class="w-full">
    @if ($label)
        <label @if($fieldName) for="{{ $fieldName }}" @endif class="block text-sm font-medium text-slate-700 mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-rose-500 font-bold ml-0.5">*</span>
            @endif
        </label>
    @endif

    <div class="relative rounded-lg">
        @if ($icon)
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <x-ui.icon :name="$icon" class="w-5 h-5" />
            </div>
        @endif

        <select 
            @if($fieldName) id="{{ $fieldName }}" name="{{ $fieldName }}" @endif
            {{ $attributes->merge(['class' => 'w-full h-10 px-3.5 pr-8 rounded-lg border text-sm text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors appearance-none ' . ($icon ? 'pl-10' : '') . ($fieldName && $errors->has($fieldName) ? ' border-rose-300 bg-rose-50/20 text-rose-900 focus:ring-rose-500 focus:border-rose-500' : ' border-slate-300')]) }}
        >
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif

            @if (count($options) > 0)
                @foreach ($options as $value => $optionLabel)
                    <option value="{{ is_numeric($value) ? $optionLabel : $value }}">{{ $optionLabel }}</option>
                @endforeach
            @else
                {{ $slot }}
            @endif
        </select>

        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
            <x-ui.icon name="chevron-down" class="w-4 h-4" />
        </div>
    </div>

    @if ($help)
        <p class="mt-1.5 text-xs text-slate-500">{{ $help }}</p>
    @endif

    @if ($fieldName)
        @error($fieldName)
            <p class="mt-1.5 text-xs font-medium text-rose-600 flex items-center gap-1">
                <x-ui.icon name="exclamation-circle" class="w-3.5 h-3.5" />
                <span>{{ $message }}</span>
            </p>
        @enderror
    @endif
</div>
