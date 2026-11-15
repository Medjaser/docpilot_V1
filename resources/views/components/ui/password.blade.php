@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'help' => null,
    'placeholder' => '••••••••',
])

@php
$wireModel = $attributes->wire('model')->value();
$fieldName = $name ?? $wireModel;
@endphp

<div class="w-full" x-data="{ show: false }">
    @if ($label)
        <label @if($fieldName) for="{{ $fieldName }}" @endif class="block text-sm font-medium text-slate-700 mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-rose-500 font-bold ml-0.5">*</span>
            @endif
        </label>
    @endif

    <div class="relative rounded-lg">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <x-ui.icon name="lock" class="w-5 h-5" />
        </div>

        <input 
            :type="show ? 'text' : 'password'"
            @if($fieldName) id="{{ $fieldName }}" name="{{ $fieldName }}" @endif
            placeholder="{{ $placeholder }}"
            {{ $attributes->merge(['class' => 'w-full h-10 pl-10 pr-10 rounded-lg border text-sm text-slate-900 bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ' . ($fieldName && $errors->has($fieldName) ? ' border-rose-300 bg-rose-50/20 text-rose-900 focus:ring-rose-500 focus:border-rose-500' : ' border-slate-300')]) }}
        />

        <button 
            type="button" 
            @click="show = !show"
            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
            aria-label="Afficher ou masquer le mot de passe"
        >
            <x-ui.icon name="eye" class="w-5 h-5" x-show="!show" />
            <x-ui.icon name="eye-slash" class="w-5 h-5" x-show="show" x-cloak />
        </button>
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
