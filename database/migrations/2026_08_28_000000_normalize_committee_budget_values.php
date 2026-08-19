<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalisasi budget_min/budget_max ke bilangan bulat (buang pecahan).
 * Sentinel lama 999999999999999.99 → 999999999999999 agar cocok saat
 * dibandingkan dari form (yang mengirim nilai integer) — mis. tombol Delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE committee_assignments SET budget_min = FLOOR(budget_min) WHERE budget_min IS NOT NULL AND budget_min <> FLOOR(budget_min)');
        DB::statement('UPDATE committee_assignments SET budget_max = FLOOR(budget_max) WHERE budget_max IS NOT NULL AND budget_max <> FLOOR(budget_max)');
    }

    public function down(): void
    {
        // Tidak dapat mengembalikan pecahan yang sudah dibuang. No-op.
    }
};
