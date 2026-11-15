<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtre toute requête sur l'entreprise courante : WHERE company_id = CurrentCompany::id().
 *
 * Sans entreprise courante, seules les lignes sans entreprise (company_id NULL : catégories
 * globales, audit plateforme) sont visibles. Une route oubliée ne fuit donc jamais de données.
 * Les traitements multi-entreprises (tâches planifiées, admin plateforme) doivent le dire
 * explicitement avec Model::acrossAllCompanies().
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(CurrentCompany::class)->id();
        $column = $model->qualifyColumn('company_id');

        $companyId === null
            ? $builder->whereNull($column)
            : $builder->where($column, $companyId);
    }
}
