<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents attendus/déposés (§9.10), versions (§9.11),
 * demandes de documents et liens de dépôt (§9.8).
 *
 * Un "document" = un emplacement attendu (sous-traitant × catégorie).
 * Chaque fichier déposé = une "document_version". Une seule version est courante.
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
            $table->unsignedBigInteger('current_version_id')->nullable(); // FK ajoutée plus bas
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('missing'); // App\Enums\DocumentStatus
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();    // App\Enums\RejectionReason
            $table->text('rejection_comment')->nullable();     // visible par le sous-traitant
            $table->text('internal_comment')->nullable();      // interne uniquement
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['subcontractor_id', 'document_type_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'expiration_date']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('original_file_name');
            $table->string('file_path');            // chemin dans le bucket, jamais une URL publique
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size'); // octets
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('status');               // statut de CETTE version
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('uploaded_by_type');     // 'user' ou 'subcontractor'
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->text('comment')->nullable();
            $table->text('replacement_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'version_number']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('document_versions')->nullOnDelete();
        });

        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // SHA-256 du jeton du lien : le jeton clair n'est jamais stocké
            $table->string('status')->default('draft'); // App\Enums\DocumentRequestStatus
            $table->text('message')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->unsignedSmallInteger('reminder_count')->default(0);
            $table->boolean('auto_reminders')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('requested_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_request_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requested_documents');
        Schema::dropIfExists('document_requests');
        Schema::table('documents', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
