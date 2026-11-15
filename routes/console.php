<?php

use Illuminate\Support\Facades\Schedule;

// Vérification quotidienne des dates d'expiration (§9.14)
Schedule::command('docpilot:check-expirations')
    ->dailyAt('06:00')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->onOneServer();

// Cycle de vie des entreprises : relances J-3 / J0, suspension après l'essai, archivage à J+90
Schedule::command('companies:check-trials')
    ->dailyAt('07:00')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->onOneServer();
