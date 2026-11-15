<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Essai / active : accès complet. Suspendue : lecture seule (les actions Livewire sont
 * bloquées par User::hasPermission()). Résiliée / archivée : accès refusé.
 */
class EnsureCompanyIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->company;

        if ($company === null) {
            return $next($request);
        }

        if (! $company->status->canAccessApp()) {
            abort(403, 'Votre espace entreprise est inactif.');
        }

        if ($company->isReadOnly() && ! $request->isMethodSafe() && ! $request->hasHeader('X-Livewire')) {
            abort(403, 'Compte suspendu : votre espace est en lecture seule.');
        }

        return $next($request);
    }
}
