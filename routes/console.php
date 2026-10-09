<?php

use Illuminate\Support\Facades\Schedule;

// Vérification quotidienne des dates d'expiration (§9.14)
Schedule::command('docpilot:check-expirations')
    ->dailyAt('06:00')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->onOneServer();
