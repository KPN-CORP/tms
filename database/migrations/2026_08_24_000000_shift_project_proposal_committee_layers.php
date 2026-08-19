<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Layer 1 project_proposal kini dicadangkan untuk PROJECT SPONSOR (implisit,
 * per-project). Committee yang di-set admin mulai dari Layer 2.
 *
 * Geser semua committee project_proposal yang sudah ada +1 layer (dari yang
 * tertinggi ke terendah agar tidak bentrok unique) supaya konfigurasi lama
 * tetap terjaga: committee pertama (dulu L1) menjadi L2, dst.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Urut dari layer tertinggi agar +1 tidak menabrak baris lain.
        $layers = DB::table('committee_assignments')
            ->where('approval_type', 'project_proposal')
            ->distinct()->orderByDesc('layer')->pluck('layer');

        foreach ($layers as $layer) {
            DB::table('committee_assignments')
                ->where('approval_type', 'project_proposal')
                ->where('layer', $layer)
                ->update(['layer' => $layer + 1]);
        }
    }

    public function down(): void
    {
        $layers = DB::table('committee_assignments')
            ->where('approval_type', 'project_proposal')
            ->where('layer', '>', 1)
            ->distinct()->orderBy('layer')->pluck('layer');

        foreach ($layers as $layer) {
            DB::table('committee_assignments')
                ->where('approval_type', 'project_proposal')
                ->where('layer', $layer)
                ->update(['layer' => $layer - 1]);
        }
    }
};
