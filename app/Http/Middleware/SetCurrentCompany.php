<?php

namespace App\Http\Middleware;

use App\Enums\CompanyStatus;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Définit l'entreprise courante à partir de l'utilisateur connecté
 * et bloque l'accès si l'entreprise est suspendue, résiliée ou archivée.
 */
class SetCurrentCompany
{
    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->company_id) {
            $company = $user->company;

            if (! in_array($company->status, [CompanyStatus::Trial, CompanyStatus::Active], true)) {
                abort(403, 'Votre espace entreprise est inactif.');
            }

            $this->current->set($company->id);
        }

        return $next($request);
    }
}
