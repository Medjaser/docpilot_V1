@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'rows' => 3,
    'placeholder' => '',
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

    <textarea 
        rows="{{ $rows }}"
        @if($fieldName) id="{{ $fieldName }}" name="{{ $fieldName }}" @endif
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'w-full px-3.5 py-2.5 rounded-lg border text-sm text-slate-900 bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed ' . ($fieldName && $errors->has($fieldName) ? ' border-rose-300 bg-rose-50/20 text-rose-900 focus:ring-rose-500 focus:border-rose-500' : ' border-slate-300')]) }}
    >{{ $slot }}</textarea>

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
