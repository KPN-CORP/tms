<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ubah "Require role" jadi MULTI-role: satu kolom JSON `required_roles`
 * berisi daftar [{role, total}]. Backfill dari kolom lama require_role/total_in_role,
 * lalu hapus kolom lama. Idempotent — aman dijalankan di staging.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('project_categories', 'required_roles')) {
                $table->json('required_roles')->nullable()->after('max_team_members');
            }
        });

        // Backfill dari kolom tunggal lama (bila ada) → array berisi 1 item.
        if (Schema::hasColumn('project_categories', 'require_role')) {
            DB::table('project_categories')
                ->whereNotNull('require_role')
                ->where('require_role', '!=', '')
                ->get()
                ->each(function ($c) {
                    DB::table('project_categories')->where('id', $c->id)->update([
                        'required_roles' => json_encode([
                            ['role' => $c->require_role, 'total' => $c->total_in_role],
                        ]),
                    ]);
                });
        }

        Schema::table('project_categories', function (Blueprint $table) {
            foreach (['require_role', 'total_in_role'] as $col) {
                if (Schema::hasColumn('project_categories', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('project_categories', 'require_role')) {
                $table->string('require_role')->nullable();
            }
            if (! Schema::hasColumn('project_categories', 'total_in_role')) {
                $table->string('total_in_role')->nullable();
            }
        });

        Schema::table('project_categories', function (Blueprint $table) {
            if (Schema::hasColumn('project_categories', 'required_roles')) {
                $table->dropColumn('required_roles');
            }
        });
    }
};
