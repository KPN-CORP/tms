<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sync skema staging dengan lokal — AMAN (tidak menghapus data):
 *  1. DROP semua FOREIGN KEY di database default (agar tak terganjal constraint FK,
 *     mis. user_id -> users yang kini pindah ke hcis, atau FK yang bikin migrate gagal).
 *  2. TAMBAH kolom yang mungkin belum ada di staging (idempotent via hasColumn),
 *     terutama kolom-kolom nama org di `ideas` dan `notes` di `projects`.
 *
 * Aman dijalankan berulang. Kolom enum inti (projects.status, committee_assignments.
 * approval_type, dsb.) sengaja TIDAK disentuh karena hampir pasti sudah ada di staging.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropAllForeignKeys();
        $this->addMissingColumns();
    }

    public function down(): void
    {
        // Snapshot sinkron; tidak direverse (FK & kolom dibiarkan apa adanya).
    }

    /** Drop SEMUA foreign key di database default. */
    private function dropAllForeignKeys(): void
    {
        $db = DB::connection()->getDatabaseName();

        $fks = DB::select(
            'SELECT DISTINCT TABLE_NAME, CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$db]
        );

        foreach ($fks as $fk) {
            try {
                DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Throwable $e) {
                // abaikan bila sudah tidak ada
            }
        }
    }

    /** Tambah kolom yang mungkin belum ada di staging (guarded hasColumn → idempotent). */
    private function addMissingColumns(): void
    {
        // ideas — kolom org id & NAMA (penyebab error business_unit_name), + user_id/current_layer.
        $this->ensure('ideas', [
            'user_id'            => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
            'current_layer'      => fn (Blueprint $t) => $t->unsignedTinyInteger('current_layer')->default(1),
            'company_id'         => fn (Blueprint $t) => $t->unsignedBigInteger('company_id')->nullable(),
            'location_id'        => fn (Blueprint $t) => $t->unsignedBigInteger('location_id')->nullable(),
            'business_unit_name' => fn (Blueprint $t) => $t->string('business_unit_name')->nullable(),
            'department_name'    => fn (Blueprint $t) => $t->string('department_name')->nullable(),
            'company_name'       => fn (Blueprint $t) => $t->string('company_name')->nullable(),
            'location_name'      => fn (Blueprint $t) => $t->string('location_name')->nullable(),
        ]);

        // projects — notes (Create Project), + summary/category/current_layer.
        $this->ensure('projects', [
            'current_layer'       => fn (Blueprint $t) => $t->unsignedTinyInteger('current_layer')->default(1),
            'project_summary'     => fn (Blueprint $t) => $t->text('project_summary')->nullable(),
            'project_category_id' => fn (Blueprint $t) => $t->unsignedBigInteger('project_category_id')->nullable(),
            'notes'               => fn (Blueprint $t) => $t->text('notes')->nullable(),
        ]);

        // committee_assignments — department_id (routing per Unit).
        $this->ensure('committee_assignments', [
            'department_id' => fn (Blueprint $t) => $t->unsignedBigInteger('department_id')->nullable(),
        ]);

        // implementation_plans — tanggal planning/actual + pic.
        $this->ensure('implementation_plans', [
            'planning_start' => fn (Blueprint $t) => $t->date('planning_start')->nullable(),
            'planning_end'   => fn (Blueprint $t) => $t->date('planning_end')->nullable(),
            'actual_start'   => fn (Blueprint $t) => $t->date('actual_start')->nullable(),
            'actual_end'     => fn (Blueprint $t) => $t->date('actual_end')->nullable(),
            'pic_user_ids'   => fn (Blueprint $t) => $t->json('pic_user_ids')->nullable(),
        ]);

        // implementation_indicators — baseline.
        $this->ensure('implementation_indicators', [
            'baseline' => fn (Blueprint $t) => $t->decimal('baseline', 15, 2)->nullable(),
        ]);

        // project_budgets — realisasi.
        $this->ensure('project_budgets', [
            'actual_qty'   => fn (Blueprint $t) => $t->decimal('actual_qty', 15, 2)->nullable(),
            'actual_price' => fn (Blueprint $t) => $t->decimal('actual_price', 15, 2)->nullable(),
        ]);
    }

    /**
     * Tambah kolom hanya bila tabel ada DAN kolom belum ada.
     *
     * @param  array<string, \Closure>  $columns  [nama_kolom => fn(Blueprint $t) => $t->...]
     */
    private function ensure(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $missing = array_filter(
            $columns,
            fn ($name) => ! Schema::hasColumn($table, $name),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($missing)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($missing) {
            foreach ($missing as $add) {
                $add($t);
            }
        });
    }
};
