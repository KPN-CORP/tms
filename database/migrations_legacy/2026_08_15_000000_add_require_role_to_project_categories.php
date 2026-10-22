<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah field "Require role" + "Total People in the Role" ke project_categories.
 * Keduanya OPTIONAL (free text). Dipakai untuk memprapopulasi Role in Project pada
 * Team Members project yang kategorinya cocok (mis. Satpam × 2 baris).
 * Idempotent (guard hasColumn) agar aman dijalankan di staging.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('project_categories', 'require_role')) {
                $table->string('require_role')->nullable()->after('max_team_members');
            }
            if (! Schema::hasColumn('project_categories', 'total_in_role')) {
                $table->string('total_in_role')->nullable()->after('require_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            foreach (['require_role', 'total_in_role'] as $col) {
                if (Schema::hasColumn('project_categories', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
