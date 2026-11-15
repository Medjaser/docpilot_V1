<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identifiant aléatoire du logo, seul élément exposé dans l'URL publique /branding/logo/{uuid}
 * (l'URL par identifiant d'entreprise permettait d'énumérer les clients).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->uuid('logo_uuid')->nullable()->unique()->after('logo_path');
        });

        DB::table('companies')->whereNotNull('logo_path')->orderBy('id')->each(function ($company) {
            preg_match('/logo-([0-9a-f-]{36})\./', $company->logo_path, $matches);

            DB::table('companies')
                ->where('id', $company->id)
                ->update(['logo_uuid' => $matches[1] ?? (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['logo_uuid']);
            $table->dropColumn('logo_uuid');
        });
    }
};
