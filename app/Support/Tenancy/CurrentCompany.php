<?php

namespace App\Support\Tenancy;

/**
 * Entreprise (tenant) courante de la requête.
 *
 * Renseignée par le middleware SetCurrentCompany (utilisateur connecté)
 * ou par le portail externe (à partir du jeton du lien de dépôt).
 * Enregistrée comme singleton dans AppServiceProvider.
 */
class CurrentCompany
{
    private ?int $companyId = null;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function has(): bool
    {
        return $this->companyId !== null;
    }

    public function clear(): void
    {
        $this->companyId = null;
    }
}
