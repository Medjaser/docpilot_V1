<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Commentaires sur un document : internes (équipe) ou externes (visibles du sous-traitant). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('author_type');               // 'user' ou 'subcontractor' (morph map)
            $table->unsignedBigInteger('author_id')->nullable();
            $table->text('body');
            $table->string('visibility')->default('internal'); // internal | external
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
            $table->index(['author_type', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};
