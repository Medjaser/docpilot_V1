<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Journal d'audit (§9.22). Écrit une fois, jamais modifié : pas de updated_at. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_type')->default('user');   // user, subcontractor, system
            $table->unsignedBigInteger('user_id')->nullable(); // pas de FK : peut désigner un sous-traitant
            $table->string('action');                        // ex. document.validated
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('metadata')->nullable();           // informations NON sensibles uniquement
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
