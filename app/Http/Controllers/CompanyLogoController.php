<?php

namespace App\Http\Controllers;

use App\Domain\Company\CompanyLogoStorage;
use App\Models\Company;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logo d'entreprise, public car affiché sur le portail sous-traitant et dans les e-mails.
 * Adressé par un UUID aléatoire propre au fichier : impossible d'énumérer les entreprises.
 */
class CompanyLogoController extends Controller
{
    public function __invoke(string $uuid): StreamedResponse
    {
        $company = Company::query()->where('logo_uuid', $uuid)->first();
        $disk = CompanyLogoStorage::disk();

        abort_unless($company?->logo_path && $disk->exists($company->logo_path), 404);

        return $disk->response($company->logo_path, basename($company->logo_path), [
            'Content-Type' => CompanyLogoStorage::mimeTypeFor($company->logo_path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
