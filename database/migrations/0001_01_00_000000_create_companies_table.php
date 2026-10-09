<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entreprises clientes (tenants). Créée AVANT users car chaque utilisateur
 * (sauf l'administrateur plateforme) appartient à une entreprise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // raison sociale
            $table->string('trade_name')->nullable();     // nom commercial
            $table->string('registration_number')->nullable(); // SIREN / SIRET
            $table->string('sector')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('FR');
            $table->string('logo_path')->nullable();
            $table->string('status')->default('trial');   // App\Enums\CompanyStatus
            $table->string('subscription_plan')->default('mini');
            $table->timestamp('trial_ends_at')->nullable();
            $table->jsonb('settings')->nullable();        // délais d'alerte, relances, notifications
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
