<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nominal ambang budget yang KONFIGURABEL untuk committee project_proposal.
 * budget_tier kini berperan sebagai OPERATOR (0 = ≤, 1 = >) terhadap budget_amount.
 * Contoh: (tier=0, amount=10.000.000) → committee untuk project budget ≤ 10 juta.
 *
 * Backfill: baris project_proposal lama (ambang tetap 100 juta) diisi 100.000.000
 * agar perilaku lama tetap sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('committee_assignments', 'budget_amount')) {
                $table->decimal('budget_amount', 20, 2)->nullable()->after('budget_tier');
            }
        });

        DB::table('committee_assignments')
            ->where('approval_type', 'project_proposal')
            ->whereNull('budget_amount')
            ->update(['budget_amount' => 100000000]);
    }

    public function down(): void
    {
        Schema::table('committee_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('committee_assignments', 'budget_amount')) {
                $table->dropColumn('budget_amount');
            }
        });
    }
};
