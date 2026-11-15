<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lignes d'une demande : une catégorie demandée, reliée au document une fois connu. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requested_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('requested'); // App\Enums\DocumentStatus
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->unique(['document_request_id', 'document_type_id']);
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requested_documents');
    }
};
