@props([
    'status' => 'valid', // valid, expiring_soon, expired, rejected, pending, missing, requested, complete, incomplete, critical, active, disabled
    'size' => 'md',
    'label' => null,
])

@php
$config = [
    'valid' => ['text' => 'Valide', 'bg' => 'bg-emerald-50', 'text-color' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500'],
    'complete' => ['text' => 'Dossier complet', 'bg' => 'bg-emerald-50', 'text-color' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500'],
    'expiring_soon' => ['text' => 'Bientôt expiré', 'bg' => 'bg-amber-50', 'text-color' => 'text-amber-700', 'border' => 'border-amber-200', 'dot' => 'bg-amber-500'],
    'expired' => ['text' => 'Expiré', 'bg' => 'bg-rose-50', 'text-color' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500'],
    'rejected' => ['text' => 'Rejeté', 'bg' => 'bg-rose-50', 'text-color' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500'],
    'incomplete' => ['text' => 'Dossier incomplet', 'bg' => 'bg-rose-50', 'text-color' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500'],
    'critical' => ['text' => 'Dossier critique', 'bg' => 'bg-rose-100', 'text-color' => 'text-rose-800', 'border' => 'border-rose-300', 'dot' => 'bg-rose-600'],
    'pending' => ['text' => 'À valider', 'bg' => 'bg-purple-50', 'text-color' => 'text-purple-700', 'border' => 'border-purple-200', 'dot' => 'bg-purple-500'],
    'requested' => ['text' => 'En attente', 'bg' => 'bg-blue-50', 'text-color' => 'text-blue-700', 'border' => 'border-blue-200', 'dot' => 'bg-blue-500'],
    'missing' => ['text' => 'Manquant', 'bg' => 'bg-slate-100', 'text-color' => 'text-slate-700', 'border' => 'border-slate-300', 'dot' => 'bg-slate-400'],
    'active' => ['text' => 'Actif', 'bg' => 'bg-emerald-50', 'text-color' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500'],
    'disabled' => ['text' => 'Désactivé', 'bg' => 'bg-slate-100', 'text-color' => 'text-slate-600', 'border' => 'border-slate-200', 'dot' => 'bg-slate-400'],
];

$c = $config[$status] ?? [
    'text' => ucfirst($status),
    'bg' => 'bg-slate-100',
    'text-color' => 'text-slate-700',
    'border' => 'border-slate-200',
    'dot' => 'bg-slate-400'
];

$displayText = $label ?? $c['text'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border {$c['bg']} {$c['text-color']} {$c['border']} shadow-2xs"]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $c['dot'] }}"></span>
    <span>{{ $displayText }}</span>
</span>
