<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Catégories de documents (§9.6). */
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
            $table->jsonb('allowed_extensions')->nullable(); // ex. ["pdf", "jpg", "png"]
            $table->unsignedSmallInteger('max_file_size_mb')->default(10);
            $table->text('message_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // NULLS NOT DISTINCT : deux modèles globaux ne peuvent pas porter le même nom
            $table->unique(['company_id', 'name'])->nullsNotDistinct();
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
