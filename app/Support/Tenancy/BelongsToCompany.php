<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * À utiliser sur TOUT modèle métier qui possède une colonne company_id.
 *
 * - Filtre automatiquement chaque requête sur l'entreprise courante (CompanyScope).
 * - Remplit automatiquement company_id à la création.
 *
 * C'est la première barrière d'isolation multi-tenant (§9.3).
 * Les Policies restent obligatoires en seconde barrière.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $current = app(CurrentCompany::class);

            if (empty($model->company_id) && $current->has()) {
                $model->company_id = $current->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Pour les tâches planifiées et l'admin plateforme : désactive explicitement le filtre. */
    public static function acrossAllCompanies(): Builder
    {
        return static::withoutGlobalScope(CompanyScope::class);
    }
}
