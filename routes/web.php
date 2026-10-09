<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Espace entreprise (mois 2) — à protéger par ['auth', 'verified']
|--------------------------------------------------------------------------
| Route::middleware(['auth', 'verified'])->group(function () {
|     Route::get('/tableau-de-bord', ...)->name('dashboard');
|     Route::get('/sous-traitants', ...)->name('subcontractors.index');
| });
|
|--------------------------------------------------------------------------
| Portail externe sous-traitant (mois 5) — sans compte, via jeton
|--------------------------------------------------------------------------
| Route::middleware('throttle:portal')->prefix('depot')->group(function () {
|     Route::get('/{token}', ...)->name('portal.show');
| });
*/
