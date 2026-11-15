<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents attendus pour un sous-traitant (§9.10) : un emplacement par catégorie.
 * Chaque fichier déposé est une document_version ; une seule est courante.
 * Le caractère obligatoire vient de document_types.is_mandatory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable(); // FK ajoutée avec document_versions
            $table->string('status')->default('missing'); // App\Enums\DocumentStatus
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason_code')->nullable(); // App\Enums\RejectionReason
            $table->text('rejection_comment')->nullable();       // visible par le sous-traitant
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['subcontractor_id', 'document_type_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'expiration_date']);
            $table->index('document_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
