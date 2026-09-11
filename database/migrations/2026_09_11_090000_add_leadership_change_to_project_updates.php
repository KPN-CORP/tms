<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jenis perubahan baru: 'leadership_change' — penggantian Project Sponsor dan/atau
 * Project Leader. Penyetujunya BUKAN committee_assignments, melainkan committee
 * layer TERAKHIR dari ide asal project tersebut.
 *
 * approver_role 'idea_committee' dipakai untuk menandai jalur itu, dan 'unassigned'
 * untuk permintaan yang menggantung karena ide-nya belum punya committee layer
 * terakhir — permintaan tetap tersimpan sampai penyetujunya ditentukan.
 */
return new class extends Migration
{
    private const TYPE_LAMA = "'budget','planning','team','general','team_change','plan_indicator_change','budget_change'";
    private const TYPE_BARU = "'budget','planning','team','general','team_change','plan_indicator_change','budget_change','leadership_change'";

    private const ROLE_LAMA = "'sponsor','committee','auto'";
    private const ROLE_BARU = "'sponsor','committee','auto','idea_committee','unassigned'";

    public function up(): void
    {
        DB::statement('ALTER TABLE project_updates MODIFY change_type ENUM(' . self::TYPE_BARU . ') NOT NULL');
        DB::statement('ALTER TABLE project_updates MODIFY approver_role ENUM(' . self::ROLE_BARU . ") NOT NULL DEFAULT 'sponsor'");
    }

    public function down(): void
    {
        // Baris yang memakai nilai baru dibuang lebih dulu agar ALTER tidak gagal.
        DB::table('project_updates')->where('change_type', 'leadership_change')->delete();
        DB::table('project_updates')->whereIn('approver_role', ['idea_committee', 'unassigned'])
            ->update(['approver_role' => 'sponsor']);

        DB::statement('ALTER TABLE project_updates MODIFY change_type ENUM(' . self::TYPE_LAMA . ') NOT NULL');
        DB::statement('ALTER TABLE project_updates MODIFY approver_role ENUM(' . self::ROLE_LAMA . ") NOT NULL DEFAULT 'sponsor'");
    }
};
