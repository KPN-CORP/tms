<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi item parsial:
 * - T-92/93/94: approval type bisa untuk SEMUA jenis (tambah project_tracking & team_change).
 * - T-61: Budget Actual tracking (actual_qty, actual_price -> actual_cost auto).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE committee_assignments MODIFY approval_type
            ENUM('idea','project_proposal','project_completion','project_tracking','team_change')
            NOT NULL DEFAULT 'idea'");

        Schema::table('project_budgets', function (Blueprint $table) {
            $table->decimal('actual_qty', 18, 2)->nullable()->after('unit_price');
            $table->decimal('actual_price', 18, 2)->nullable()->after('actual_qty');
        });
    }

    public function down(): void
    {
        Schema::table('project_budgets', function (Blueprint $table) {
            $table->dropColumn(['actual_qty', 'actual_price']);
        });

        DB::statement("ALTER TABLE committee_assignments MODIFY approval_type
            ENUM('idea','project_proposal','project_completion') NOT NULL DEFAULT 'idea'");
    }
};
