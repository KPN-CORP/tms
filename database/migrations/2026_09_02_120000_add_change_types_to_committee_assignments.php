<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kolom approval_type belum memuat plan_indicator_change & budget_change, padahal
 * form Committee Assignment sudah menawarkannya — akibatnya keduanya TIDAK PERNAH
 * bisa tersimpan (MySQL menolak dgn "Data truncated for column 'approval_type'").
 *
 * Nilai lama 'project_tracking' dipertahankan agar data historis tidak hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `committee_assignments` MODIFY `approval_type`
            ENUM('idea','project_proposal','project_completion','project_tracking',
                 'team_change','plan_indicator_change','budget_change') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM `committee_assignments`
            WHERE `approval_type` IN ('plan_indicator_change','budget_change')");
        DB::statement("ALTER TABLE `committee_assignments` MODIFY `approval_type`
            ENUM('idea','project_proposal','project_completion','project_tracking','team_change') NOT NULL");
    }
};
