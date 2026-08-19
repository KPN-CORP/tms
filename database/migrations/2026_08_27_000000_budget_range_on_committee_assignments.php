<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ganti dimensi budget committee project_proposal dari (operator + nominal)
 * menjadi RANGE: budget_min – budget_max. Project dengan total budget dalam
 * [min, max] (inklusif) masuk ke committee tersebut.
 *
 * Backfill dari kolom lama:
 *   tier 0 (≤ amount) → [0, amount]
 *   tier 1 (> amount) → [amount, ~tak hingga]
 *   tier 2 (= amount) → [amount, amount]
 */
return new class extends Migration
{
    private string $oldIdx = 'ca_type_bu_dept_budget_layer_unq';
    private string $newIdx = 'ca_type_bu_dept_range_layer_unq';
    private string $bigMax = '999999999999999.99';

    public function up(): void
    {
        Schema::table('committee_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('committee_assignments', 'budget_min')) {
                $table->decimal('budget_min', 20, 2)->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('committee_assignments', 'budget_max')) {
                $table->decimal('budget_max', 20, 2)->nullable()->after('budget_min');
            }
        });

        if (Schema::hasColumn('committee_assignments', 'budget_tier')) {
            DB::table('committee_assignments')->where('approval_type', 'project_proposal')->where('budget_tier', 0)
                ->update(['budget_min' => 0, 'budget_max' => DB::raw('budget_amount')]);
            DB::table('committee_assignments')->where('approval_type', 'project_proposal')->where('budget_tier', 1)
                ->update(['budget_min' => DB::raw('budget_amount'), 'budget_max' => DB::raw($this->bigMax)]);
            DB::table('committee_assignments')->where('approval_type', 'project_proposal')->where('budget_tier', 2)
                ->update(['budget_min' => DB::raw('budget_amount'), 'budget_max' => DB::raw('budget_amount')]);
        }

        if ($this->indexExists($this->oldIdx)) {
            DB::statement("ALTER TABLE `committee_assignments` DROP INDEX `{$this->oldIdx}`");
        }
        if (! $this->indexExists($this->newIdx)) {
            DB::statement(
                "ALTER TABLE `committee_assignments` ADD UNIQUE `{$this->newIdx}` "
                . '(`approval_type`, `business_unit_id`, `department_id`, `budget_min`, `budget_max`, `layer`)'
            );
        }

        Schema::table('committee_assignments', function (Blueprint $table) {
            foreach (['budget_tier', 'budget_amount'] as $col) {
                if (Schema::hasColumn('committee_assignments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('committee_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('committee_assignments', 'budget_tier')) {
                $table->unsignedTinyInteger('budget_tier')->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('committee_assignments', 'budget_amount')) {
                $table->decimal('budget_amount', 20, 2)->nullable()->after('budget_tier');
            }
        });

        // Perkiraan balik: range [0, X] → tier 0 (≤ X); lainnya → tier 2 (= min).
        DB::table('committee_assignments')->where('approval_type', 'project_proposal')->where('budget_min', 0)
            ->update(['budget_tier' => 0, 'budget_amount' => DB::raw('budget_max')]);
        DB::table('committee_assignments')->where('approval_type', 'project_proposal')->where('budget_min', '>', 0)
            ->update(['budget_tier' => 2, 'budget_amount' => DB::raw('budget_min')]);

        if ($this->indexExists($this->newIdx)) {
            DB::statement("ALTER TABLE `committee_assignments` DROP INDEX `{$this->newIdx}`");
        }
        if (! $this->indexExists($this->oldIdx)) {
            DB::statement(
                "ALTER TABLE `committee_assignments` ADD UNIQUE `{$this->oldIdx}` "
                . '(`approval_type`, `business_unit_id`, `department_id`, `budget_tier`, `budget_amount`, `layer`)'
            );
        }

        Schema::table('committee_assignments', function (Blueprint $table) {
            foreach (['budget_min', 'budget_max'] as $col) {
                if (Schema::hasColumn('committee_assignments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function indexExists(string $name): bool
    {
        return count(DB::select('SHOW INDEX FROM committee_assignments WHERE Key_name = ?', [$name])) > 0;
    }
};
