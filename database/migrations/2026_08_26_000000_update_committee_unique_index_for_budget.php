<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unique lama (approval_type, business_unit_id, department_id, layer) menghalangi
 * dua bracket budget berbeda (operator/nominal) memakai layer yang sama pada
 * BU+Unit yang sama. Ganti dengan unique yang menyertakan budget_tier + budget_amount.
 */
return new class extends Migration
{
    private string $old = 'ca_type_bu_dept_layer_unq';
    private string $new = 'ca_type_bu_dept_budget_layer_unq';

    public function up(): void
    {
        if ($this->indexExists($this->old)) {
            DB::statement("ALTER TABLE `committee_assignments` DROP INDEX `{$this->old}`");
        }
        if (! $this->indexExists($this->new)) {
            DB::statement(
                "ALTER TABLE `committee_assignments` ADD UNIQUE `{$this->new}` "
                . '(`approval_type`, `business_unit_id`, `department_id`, `budget_tier`, `budget_amount`, `layer`)'
            );
        }
    }

    public function down(): void
    {
        if ($this->indexExists($this->new)) {
            DB::statement("ALTER TABLE `committee_assignments` DROP INDEX `{$this->new}`");
        }
        if (! $this->indexExists($this->old)) {
            DB::statement(
                "ALTER TABLE `committee_assignments` ADD UNIQUE `{$this->old}` "
                . '(`approval_type`, `business_unit_id`, `department_id`, `layer`)'
            );
        }
    }

    private function indexExists(string $name): bool
    {
        return count(DB::select(
            'SHOW INDEX FROM committee_assignments WHERE Key_name = ?',
            [$name]
        )) > 0;
    }
};
