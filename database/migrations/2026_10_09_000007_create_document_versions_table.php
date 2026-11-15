<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fichiers déposés (§9.11). Une version n'est jamais modifiée ni supprimée immédiatement. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('file_name');                 // nom de stockage généré
            $table->string('original_name');             // nom du fichier chez le déposant
            $table->string('file_path');                 // chemin dans le bucket, jamais une URL publique
            $table->string('disk')->default('documents');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');     // octets
            $table->char('sha256', 64)->nullable();
            $table->string('status');                    // App\Enums\DocumentStatus, statut de CETTE version
            $table->string('uploaded_by_type');          // 'user' ou 'subcontractor' (morph map)
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->foreignId('replaced_by')->nullable()->constrained('document_versions')->nullOnDelete(); // version suivante
            $table->text('replacement_reason')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['uploaded_by_type', 'uploaded_by_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('document_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('document_versions');
    }
};
