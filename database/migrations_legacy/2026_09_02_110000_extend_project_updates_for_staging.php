<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Change request menjadi STAGING: perubahan data proposal disimpan dulu di sini
 * (payload) dan BELUM berlaku sampai disetujui. Saat approval selesai, payload
 * diterapkan ke baris aslinya; bila ditolak, payload dibuang.
 *
 * Perubahan:
 *  - status  + 'draft'  : hasil edit yang belum ditekan "Update Project".
 *  - payload            : nilai baru yang menunggu diterapkan.
 *  - current_layer       : approval committee bisa berlapis (seperti alur Idea).
 *  - change_type + 3 jenis baru yang selaras dgn committee_assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `project_updates`
            MODIFY `status` ENUM('draft','pending','approved','rejected','applied','cancelled') NOT NULL DEFAULT 'pending'");

        // Jenis lama dipertahankan agar data historis tetap terbaca.
        DB::statement("ALTER TABLE `project_updates`
            MODIFY `change_type` ENUM('budget','planning','team','general','team_change','plan_indicator_change','budget_change') NOT NULL");

        Schema::table('project_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('project_updates', 'payload')) {
                $table->longText('payload')->nullable()->after('snapshot_after');
            }
            if (! Schema::hasColumn('project_updates', 'current_layer')) {
                $table->unsignedTinyInteger('current_layer')->default(1)->after('approver_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_updates', function (Blueprint $table) {
            foreach (['payload', 'current_layer'] as $c) {
                if (Schema::hasColumn('project_updates', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        DB::statement("UPDATE `project_updates` SET `status` = 'cancelled' WHERE `status` = 'draft'");
        DB::statement("UPDATE `project_updates` SET `change_type` = 'general'
            WHERE `change_type` IN ('team_change','plan_indicator_change','budget_change')");

        DB::statement("ALTER TABLE `project_updates`
            MODIFY `status` ENUM('pending','approved','rejected','applied','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE `project_updates`
            MODIFY `change_type` ENUM('budget','planning','team','general') NOT NULL");
    }
};
