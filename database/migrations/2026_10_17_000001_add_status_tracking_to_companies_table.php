<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Suivi des changements de statut d'une entreprise (suspension, résiliation, archivage à J+90). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('status_reason', 500)->nullable()->after('status');
            $table->timestamp('suspended_at')->nullable()->after('trial_ends_at');
            $table->timestamp('terminated_at')->nullable()->after('suspended_at');

            $table->index('registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['registration_number']);
            $table->dropColumn(['status_reason', 'suspended_at', 'terminated_at']);
        });
    }
};
