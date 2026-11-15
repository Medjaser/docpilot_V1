<!DOCTYPE html>
<html lang="fr" class="h-full bg-[#F5F7FB]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'DocPilot - Tableau de bord' }}</title>

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
<body 
    x-data="{ 
        collapsed: localStorage.getItem('sidebar_collapsed') === 'true',
        mobileOpen: false,
        toggleCollapse() {
            this.collapsed = !this.collapsed;
            localStorage.setItem('sidebar_collapsed', this.collapsed);
        }
    }"
    class="h-full font-sans antialiased text-slate-800 bg-[#F5F7FB] flex overflow-hidden"
>

    <!-- Mobile Overlay Backdrop -->
    <div 
        x-show="mobileOpen" 
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="mobileOpen = false" 
        class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-xs lg:hidden"
        style="display: none;"
    ></div>

    <!-- Sidebar Left Navy Blue (#0B1B3F) -->
    <aside 
        :class="{
            'w-60': !collapsed,
            'w-20': collapsed,
            'translate-x-0': mobileOpen,
            '-translate-x-full lg:translate-x-0': !mobileOpen
        }"
        class="fixed lg:static inset-y-0 left-0 z-50 bg-[#0B1B3F] text-white flex flex-col justify-between transition-all duration-300 ease-in-out shrink-0 border-r border-slate-800"
    >
        <!-- Sidebar Header: Logo + Collapse Button -->
        <div class="p-4 border-b border-white/10 flex items-center justify-between">
            <div x-show="!collapsed" class="flex items-center">
                <x-ui.logo variant="light" size="md" />
            </div>
            <div x-show="collapsed" class="mx-auto" style="display: none;">
                <div class="bg-blue-600 text-white p-2 rounded-xl">
                    <x-ui.icon name="document" class="w-6 h-6" />
                </div>
            </div>
            <button 
                type="button" 
                @click="toggleCollapse()"
                class="hidden lg:flex p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 focus:outline-none"
                :title="collapsed ? 'Déplier le menu' : 'Replier le menu'"
            >
                <x-ui.icon name="chevron-left" class="w-5 h-5 transition-transform duration-300" ::class="{ 'rotate-180': collapsed }" />
            </button>
            <button 
                type="button" 
                @click="mobileOpen = false"
                class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10"
            >
                <x-ui.icon name="x-mark" class="w-5 h-5" />
            </button>
        </div>

        @php $currentCompany = auth()->user()?->company; @endphp

        <!-- Company Block -->
        <div class="p-4 border-b border-white/10 bg-white/5">
            <div class="flex items-center gap-3">
                @if ($currentLogoUrl = $currentCompany?->logoUrl())
                    <div class="w-9 h-9 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-sm overflow-hidden">
                        <img src="{{ $currentLogoUrl }}" alt="Logo {{ $currentCompany->name }}" class="w-full h-full object-contain" />
                    </div>
                @else
                    <div class="w-9 h-9 rounded-xl bg-blue-600 font-bold text-sm text-white flex items-center justify-center shrink-0 shadow-sm">
                        {{ $currentCompany?->initials() ?? 'DP' }}
                    </div>
                @endif
                <div x-show="!collapsed" class="min-w-0 flex-1">
                    <h4 class="text-xs font-bold text-white truncate">{{ $currentCompany?->name ?? 'DocPilot' }}</h4>
                    <p class="text-[11px] text-slate-400 truncate">SIRET {{ $currentCompany?->registration_number ?? '-' }}</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            @php
                $canAdmin = auth()->user()?->role?->canAdministerCompany() ?? false;
                // Modèles BelongsToCompany : comptes limités à l'entreprise courante
                $pendingDocumentsCount = $currentCompany
                    ? \App\Models\Document::query()->whereIn('status', [\App\Enums\DocumentStatus::Pending, \App\Enums\DocumentStatus::Uploaded])->count()
                    : 0;
                $activeSubcontractorsCount = $currentCompany ? \App\Models\Subcontractor::query()->whereNull('archived_at')->count() : 0;
                $currentPlan = $currentCompany ? config('docpilot.plans.'.$currentCompany->subscription_plan) : null;
                // Modèles BelongsToCompany : comptes limités à l'entreprise courante
                $pendingDocumentsCount = $currentCompany
                    ? \App\Models\Document::query()->whereIn('status', [\App\Enums\DocumentStatus::Pending, \App\Enums\DocumentStatus::Uploaded])->count()
                    : 0;
                $activeSubcontractorsCount = $currentCompany ? \App\Models\Subcontractor::query()->whereNull('archived_at')->count() : 0;
                $currentPlan = $currentCompany ? config('docpilot.plans.'.$currentCompany->subscription_plan) : null;
                $navItems = [
                    ['route' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'chart-bar', 'pattern' => 'app/dashboard*', 'show' => true],
                    ['route' => 'subcontractors.index', 'label' => 'Sous-traitants', 'icon' => 'user-group', 'pattern' => 'app/subcontractors*', 'show' => true],
                    ['route' => 'documents.pending', 'label' => 'Documents à valider', 'icon' => 'document', 'pattern' => 'app/documents*', 'badge' => $pendingDocumentsCount ?: null, 'show' => true],
                    ['route' => 'deadlines.index', 'label' => 'Échéances', 'icon' => 'clock', 'pattern' => 'app/deadlines*', 'show' => true],
                    ['route' => 'templates.index', 'label' => 'Modèles de dossiers', 'icon' => 'folder', 'pattern' => 'app/templates*', 'show' => $canAdmin],
                    ['route' => 'document-types.index', 'label' => 'Catégories de documents', 'icon' => 'filter', 'pattern' => 'app/document-types*', 'show' => $canAdmin],
                    ['route' => 'users.index', 'label' => 'Utilisateurs', 'icon' => 'user', 'pattern' => 'app/users*', 'show' => $canAdmin],
                    ['route' => 'audit.index', 'label' => 'Journal d\'audit', 'icon' => 'shield-check', 'pattern' => 'app/audit*', 'show' => $canAdmin],
                    ['route' => 'settings.index', 'label' => 'Paramètres', 'icon' => 'settings', 'pattern' => 'app/settings*', 'show' => (bool) $currentCompany],
                ];
            @endphp

            @foreach ($navItems as $item)
                @if ($item['show'])
                    @php
                        $isActive = request()->routeIs($item['route']) || request()->is($item['pattern']);
                    @endphp
                    <a 
                        href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                        class="group flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {{ $isActive ? 'bg-blue-600 text-white shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
                        :title="collapsed ? '{{ $item['label'] }}' : ''"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <x-ui.icon :name="$item['icon']" class="w-5 h-5 shrink-0 {{ $isActive ? 'text-white' : 'text-slate-400 group-hover:text-white' }}" />
                            <span x-show="!collapsed" class="truncate">{{ $item['label'] }}</span>
                        </div>

                        @if (isset($item['badge']))
                            <span x-show="!collapsed" class="px-2 py-0.5 text-xs font-bold bg-rose-500 text-white rounded-full">
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>

        <!-- Sidebar Bottom Footer Box -->
        <div class="p-3 border-t border-white/10 space-y-3">
            <!-- Subscription Box (administrateurs uniquement) -->
            @if ($canAdmin)
            <div x-show="!collapsed" class="bg-white/10 backdrop-blur-xs rounded-xl p-3 border border-white/10 text-xs">
                <div class="flex items-center gap-2 text-amber-400 font-semibold mb-1">
                    <x-ui.icon name="crown" class="w-4 h-4" />
                    <span>Offre {{ $currentPlan['label'] ?? 'Essentiel' }}</span>
                </div>
                <p class="text-slate-300 text-[11px] mb-2">Jusqu'à {{ $currentPlan['max_subcontractors'] ?? '—' }} sous-traitants ({{ $activeSubcontractorsCount }} utilisés)</p>
                <a href="{{ route('subscription') }}" class="block text-center py-1.5 px-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors text-xs">
                    Voir mon abonnement
                </a>
            </div>
            @endif

            <!-- Help link -->
            <a href="#" class="flex items-center gap-2 text-xs text-slate-400 hover:text-white px-2 py-1 transition-colors">
                <x-ui.icon name="help" class="w-4 h-4 shrink-0" />
                <span x-show="!collapsed">Aide et support</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <!-- Top Bar White -->
        <header class="h-16 bg-white border-b border-slate-200 px-4 sm:px-6 flex items-center justify-between gap-4 shrink-0 z-10">
            <!-- Left: Mobile Toggle & Global Search -->
            <div class="flex items-center gap-3 flex-1 max-w-xl">
                <button 
                    type="button" 
                    @click="mobileOpen = true"
                    class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100"
                >
                    <x-ui.icon name="menu" class="w-6 h-6" />
                </button>

                <!-- Search Input with ⌘K -->
                @if ($currentCompany)
                    <livewire:app.global-search />
                @endif
            </div>

            <!-- Right: Notifications & User Profile -->
            <div class="flex items-center gap-3 sm:gap-4">
                <!-- Notifications Bell with Alpine Dropdown -->
                @php
                    // DatabaseNotification utilise BelongsToCompany : uniquement l'entreprise courante
                    $latestNotifications = $currentCompany ? auth()->user()->notifications()->limit(5)->get() : collect();
                    $unreadNotificationsCount = $currentCompany ? auth()->user()->notifications()->whereNull('read_at')->count() : 0;
                @endphp
                <div x-data="{ open: false }" class="relative">
                    <button 
                        @click="open = !open" 
                        type="button"
                        class="p-2 text-slate-500 hover:text-slate-700 rounded-xl hover:bg-slate-100 relative focus:outline-none"
                        aria-label="Notifications"
                    >
                        <x-ui.icon name="bell" class="w-5 h-5" />
                        @if ($unreadNotificationsCount > 0)
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-500 ring-2 ring-white"></span>
                        @endif
                    </button>

                    <!-- Notifications Dropdown Panel -->
                    <div 
                        x-show="open" 
                        @click.away="open = false" 
                        x-transition 
                        class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-xl border border-slate-200 py-2 z-50 overflow-hidden"
                        style="display: none;"
                    >
                        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Notifications ({{ $unreadNotificationsCount }})</h4>
                            <a href="{{ route('notifications.index') }}" class="text-xs font-medium text-blue-600 hover:underline">Tout voir</a>
                        </div>
                        <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                            @forelse ($latestNotifications as $notification)
                                <a href="{{ route('notifications.index') }}" class="p-3.5 flex items-start gap-3 hover:bg-blue-50/50 transition-colors {{ $notification->read_at ? '' : 'bg-blue-50/20' }}">
                                    <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center shrink-0 mt-0.5">
                                        <x-ui.icon :name="$notification->data['icon'] ?? 'bell'" class="w-4 h-4" />
                                    </div>
                                    <div class="text-xs min-w-0 flex-1">
                                        <p class="font-medium text-slate-900">{{ $notification->data['title'] ?? 'Notification' }}</p>
                                        <p class="text-slate-500 text-[11px] truncate mt-0.5">{{ $notification->data['text'] ?? '' }}</p>
                                        <span class="text-[10px] text-slate-400 mt-1 block">{{ $notification->created_at?->diffForHumans() }}</span>
                                    </div>
                                    @unless ($notification->read_at)
                                        <span class="w-2 h-2 rounded-full bg-blue-600 shrink-0 mt-1"></span>
                                    @endunless
                                </a>
                            @empty
                                <p class="p-4 text-xs text-slate-400">Aucune notification.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div x-data="{ userMenu: false }" class="relative">
                    <button 
                        @click="userMenu = !userMenu" 
                        type="button" 
                        class="flex items-center gap-3 p-1 rounded-xl hover:bg-slate-100 transition-colors focus:outline-none"
                    >
                        <div class="w-9 h-9 rounded-full bg-blue-600 text-white font-bold text-sm flex items-center justify-center shadow-xs">
                            {{ auth()->user() ? strtoupper(substr(auth()->user()->first_name, 0, 1) . substr(auth()->user()->last_name, 0, 1)) : 'U' }}
                        </div>
                        <div class="text-left hidden sm:block">
                            <h4 class="text-xs font-semibold text-slate-900 leading-tight">{{ auth()->user()?->full_name ?? auth()->user()?->first_name }}</h4>
                            <p class="text-[11px] text-slate-500 leading-tight">{{ auth()->user()?->role?->label() ?? (auth()->user()?->isPlatformAdmin() ? 'Admin Plateforme' : '') }}</p>
                        </div>
                        <x-ui.icon name="chevron-down" class="w-4 h-4 text-slate-400 hidden sm:block" />
                    </button>

                    <!-- Dropdown Menu -->
                    <div 
                        x-show="userMenu" 
                        @click.away="userMenu = false" 
                        x-transition 
                        class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-50 text-xs"
                        style="display: none;"
                    >
                        <a href="{{ route('profile') }}" class="flex items-center gap-2 px-4 py-2 text-slate-700 hover:bg-slate-50">
                            <x-ui.icon name="user" class="w-4 h-4 text-slate-400" />
                            <span>Mon profil & Sécurité</span>
                        </a>
                        @if (auth()->user()?->role?->canAdministerCompany())
                            <a href="{{ route('settings.index') }}" class="flex items-center gap-2 px-4 py-2 text-slate-700 hover:bg-slate-50">
                                <x-ui.icon name="settings" class="w-4 h-4 text-slate-400" />
                                <span>Paramètres</span>
                            </a>
                        @endif
                        <div class="border-t border-slate-100 my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-rose-600 hover:bg-rose-50 text-left text-xs">
                                <x-ui.icon name="logout" class="w-4 h-4 text-rose-500" />
                                <span>Se déconnecter</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Bandeau de statut de l'entreprise -->
        @if ($currentCompany?->status === \App\Enums\CompanyStatus::Trial)
            @php $trialDaysLeft = $currentCompany->trialDaysLeft() ?? 0; @endphp
            <div role="status" class="bg-blue-600 text-white px-4 sm:px-6 py-2 text-xs font-medium flex items-center justify-between gap-3 shrink-0">
                <span class="flex items-center gap-2">
                    <x-ui.icon name="clock" class="w-4 h-4 shrink-0" />
                    <span>Essai gratuit : {{ $trialDaysLeft }} {{ $trialDaysLeft > 1 ? 'jours restants' : 'jour restant' }}</span>
                </span>
                @if ($canAdmin)
                    <a href="{{ route('subscription') }}" class="font-semibold underline underline-offset-2 hover:text-blue-100">Choisir une offre</a>
                @endif
            </div>
        @elseif ($currentCompany?->status === \App\Enums\CompanyStatus::Suspended)
            <div role="alert" class="bg-rose-600 text-white px-4 sm:px-6 py-2 text-xs font-medium flex items-center gap-2 shrink-0">
                <x-ui.icon name="exclamation-triangle" class="w-4 h-4 shrink-0" />
                <span><strong>Compte suspendu</strong> — votre espace est en lecture seule. Contactez le support DocPilot pour le réactiver.</span>
            </div>
        @endif

        <!-- Breadcrumbs bar if available -->
        @if (isset($breadcrumbs))
            <div class="bg-white border-b border-slate-200 px-6 py-2 text-xs text-slate-500">
                {{ $breadcrumbs }}
            </div>
        @endif

        <!-- Main Page Scrollable Content -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
