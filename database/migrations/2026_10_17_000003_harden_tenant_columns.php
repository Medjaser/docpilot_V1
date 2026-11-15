<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renforce l'isolation en base :
 * - document_types devient strictement tenant (plus de ligne globale company_id NULL) ;
 * - notifications reçoit son company_id explicite ;
 * - les tables dont l'index existant ne commence pas par company_id reçoivent un index tenant.
 *
 * users.company_id reste nullable pour l'administrateur plateforme et audit_logs.company_id
 * pour les événements pré-authentification / plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('document_types')->whereNull('company_id')->exists()) {
            throw new \RuntimeException(
                'Des catégories globales existent encore. Copiez-les dans une entreprise avant cette migration.',
            );
        }

        Schema::table('document_types', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('notifications')
            ->where('notifiable_type', 'user')
            ->orderBy('id')
            ->each(function (object $notification): void {
                $companyId = DB::table('users')->where('id', $notification->notifiable_id)->value('company_id');

                if ($companyId !== null) {
                    DB::table('notifications')->where('id', $notification->id)->update(['company_id' => $companyId]);
                }
            });

        if (DB::table('notifications')->whereNull('company_id')->exists()) {
            throw new \RuntimeException(
                'Certaines notifications ne peuvent pas être rattachées à une entreprise.',
            );
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->index(['company_id', 'created_at']);
        });

        foreach ([
            'requested_documents',
            'document_comments',
            'document_versions',
            'document_template_items',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index('company_id');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'requested_documents',
            'document_comments',
            'document_versions',
            'document_template_items',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['company_id']);
            });
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'created_at']);
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('document_types', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
        });
    }
};
