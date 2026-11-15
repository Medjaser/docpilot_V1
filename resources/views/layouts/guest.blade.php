<!DOCTYPE html>
<html lang="fr" class="h-full bg-[#F5F7FB]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'DocPilot - Gestion des documents sous-traitants BTP' }}</title>

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
<body class="h-full font-sans antialiased text-slate-800 bg-[#F5F7FB] flex items-center justify-center p-4 sm:p-6 md:p-8">
    <div class="w-full max-w-5xl my-auto">
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
