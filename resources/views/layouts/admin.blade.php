<!DOCTYPE html>
<html lang="fr" class="h-full bg-[#F5F7FB]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'DocPilot - Administration Plateforme' }}</title>

    <!-- Google Font Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-800 bg-[#F5F7FB] flex overflow-hidden">
    <!-- Anthracite Sidebar Left (#1F2937) -->
    <aside class="w-60 bg-[#1F2937] text-white flex flex-col justify-between shrink-0 border-r border-slate-700">
        <div class="p-4 border-b border-slate-700 flex items-center justify-between">
            <x-ui.logo variant="light" size="md" />
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-slate-950 uppercase tracking-wider">Admin</span>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 text-xs">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-semibold transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                <x-ui.icon name="chart-bar" class="w-4 h-4" />
                <span>Tableau de bord SuperAdmin</span>
            </a>

            <a href="{{ route('admin.companies') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-semibold transition-colors {{ request()->routeIs('admin.companies') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                <x-ui.icon name="building-office" class="w-4 h-4" />
                <span>Entreprises clientes</span>
            </a>
        </nav>

        <div class="p-4 border-t border-slate-700 text-xs text-slate-400">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 hover:text-white">
                <x-ui.icon name="arrow-left" class="w-4 h-4" />
                <span>Quitter l'administration</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <header class="h-16 bg-white border-b border-slate-200 px-6 flex items-center justify-between shrink-0">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">SuperAdmin DocPilot</h2>
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Système opérationnel (PostgreSQL / PHP 8.4)</span>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
