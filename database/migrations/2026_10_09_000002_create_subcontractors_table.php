<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fiches sous-traitants (§9.5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('provider_type')->nullable();   // type de prestataire
            $table->string('activity')->nullable();        // domaine d'activité
            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('FR');
            $table->string('registration_number')->nullable();
            $table->string('status')->default('draft');    // App\Enums\SubcontractorStatus
            $table->unsignedTinyInteger('completeness_score')->default(0); // dénormalisé pour le tableau de bord
            $table->foreignId('internal_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();             // commentaires internes, jamais visibles du sous-traitant
            $table->jsonb('tags')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'legal_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontractors');
    }
};
