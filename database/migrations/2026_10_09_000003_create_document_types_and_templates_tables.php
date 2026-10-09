<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Catégories de documents (§9.6) et modèles de dossiers (§9.7). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            // NULL = modèle global géré par AD CLOUD IT, copiable par les entreprises
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('has_expiration_date')->default(true);
            $table->unsignedSmallInteger('default_validity_months')->nullable();
            $table->jsonb('reminder_days')->nullable();      // ex. [60, 30, 15, 7, 0]
            $table->jsonb('allowed_extensions')->nullable(); // ex. ["pdf","jpg","png"]
            $table->unsignedInteger('max_file_size_kb')->default(10240);
            $table->text('message_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');                 // ex. « Sous-traitant électricité »
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['document_template_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_items');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_types');
    }
};
