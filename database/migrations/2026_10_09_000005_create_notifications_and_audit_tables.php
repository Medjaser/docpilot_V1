<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - notifications : table standard Laravel (notifications dans l'application).
 * - notification_logs : historique des envois e-mail/relances + anti-doublons (§9.15, §9.16).
 * - audit_logs : journal d'audit (§9.22).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('channel');              // mail, database
            $table->string('type');                 // document_expiring, reminder_sent, ...
            $table->string('recipient');            // e-mail ou user:{id}
            $table->nullableMorphs('subject');      // document, document_request...
            // Clé unique anti-doublon, ex. "expiring:doc:42:30d" ou "reminder:req:7:2026-08-08"
            $table->string('dedup_key')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'type']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type')->default('user'); // user, subcontractor, system
            $table->string('action');               // ex. document.validated
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('metadata')->nullable();  // informations NON sensibles uniquement
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notifications');
    }
};
