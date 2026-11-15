<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Catégories de documents composant un modèle de dossier. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['template_id', 'document_type_id']);
            $table->index('document_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_items');
    }
};
