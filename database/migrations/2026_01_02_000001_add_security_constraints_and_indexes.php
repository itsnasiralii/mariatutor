<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_events', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('summary');
            }
        });

        Schema::table('tuition_requests', function (Blueprint $table): void {
            $table->index('parent_user_id', 'idx_requests_parent_user');
            $table->index('selected_tutor_id', 'idx_requests_selected_tutor');
        });

        Schema::table('opportunity_exposures', function (Blueprint $table): void {
            $table->index('tutor_id', 'idx_exposures_tutor');
        });

        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->index('tutor_id', 'idx_verif_docs_tutor');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('super_admin', 'admin', 'tutor', 'parent'))");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users DROP CHECK chk_users_role');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS chk_users_role');
        }

        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->dropIndex('idx_verif_docs_tutor');
        });

        Schema::table('opportunity_exposures', function (Blueprint $table): void {
            $table->dropIndex('idx_exposures_tutor');
        });

        Schema::table('tuition_requests', function (Blueprint $table): void {
            $table->dropIndex('idx_requests_parent_user');
            $table->dropIndex('idx_requests_selected_tutor');
        });

        Schema::table('audit_events', function (Blueprint $table): void {
            if (Schema::hasColumn('audit_events', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
        });
    }
};
