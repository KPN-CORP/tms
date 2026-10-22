<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier budget untuk committee approval PROJECT PROPOSAL:
 *   0 = ≤ IDR 100 Mio, 1 = > IDR 100 Mio.
 * NULL untuk approval type lain (idea/completion/tracking/team_change).
 * Idempoten (guard hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('committee_assignments', 'budget_tier')) {
                $table->unsignedTinyInteger('budget_tier')->nullable()->after('department_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('committee_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('committee_assignments', 'budget_tier')) {
                $table->dropColumn('budget_tier');
            }
        });
    }
};
