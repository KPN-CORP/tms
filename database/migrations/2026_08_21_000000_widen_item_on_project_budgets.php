<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perlebar kolom `item` project_budgets ke 500 char (spec Budget: Item Name max 500).
 * Butuh doctrine/dbal untuk change(); fallback raw SQL bila tidak tersedia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('project_budgets', 'item')) {
            return;
        }

        try {
            Schema::table('project_budgets', function (Blueprint $table) {
                $table->string('item', 500)->change();
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE `project_budgets` MODIFY `item` VARCHAR(500) NULL'
            );
        }
    }

    public function down(): void
    {
        // Tidak menyempitkan kembali (bisa memotong data). No-op.
    }
};
