@props([
    'passwordModel' => 'password',
])

<div x-data="{
    score: 0,
    get label() {
        if (this.score <= 1) return 'Mot de passe faible';
        if (this.score === 2) return 'Mot de passe moyen';
        return 'Mot de passe solide';
    },
    evaluate(val) {
        if (!val) { this.score = 0; return; }
        let s = 0;
        if (val.length >= 8) s++;
        if (/[A-Z]/.test(val) && /[0-9]/.test(val)) s++;
        if (/[^A-Za-z0-9]/.test(val) || val.length >= 12) s++;
        this.score = s;
    }
}" 
x-init="$watch('$wire.{{ $passwordModel }}', val => evaluate(val))"
class="mt-2 space-y-1.5">
    <div class="flex items-center justify-between text-xs">
        <span class="font-medium" :class="{
            'text-rose-600': score <= 1,
            'text-amber-600': score === 2,
            'text-emerald-600': score === 3
        }" x-text="label"></span>
    </div>
    <div class="flex gap-1.5 h-1.5 w-full">
        <div class="h-full flex-1 rounded-full transition-all duration-300" :class="score >= 1 ? (score === 1 ? 'bg-rose-500' : (score === 2 ? 'bg-amber-500' : 'bg-emerald-500')) : 'bg-slate-200'"></div>
        <div class="h-full flex-1 rounded-full transition-all duration-300" :class="score >= 2 ? (score === 2 ? 'bg-amber-500' : 'bg-emerald-500') : 'bg-slate-200'"></div>
        <div class="h-full flex-1 rounded-full transition-all duration-300" :class="score >= 3 ? 'bg-emerald-500' : 'bg-slate-200'"></div>
    </div>
</div>
