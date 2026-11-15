<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Demandes de documents envoyées à un sous-traitant, avec leur lien de dépôt (§9.8). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // SHA-256 du jeton du lien : le jeton clair n'est jamais stocké
            $table->text('message')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('draft'); // App\Enums\RequestStatus
            $table->boolean('auto_reminders')->default(true);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('confirmed_at')->nullable(); // le sous-traitant a confirmé l'envoi de ses dépôts
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('subcontractor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
