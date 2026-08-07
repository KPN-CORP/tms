<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * User kini memakai tabel users di database hcis (koneksi kpncorp), sehingga
 * kolom *_id/*_by yang menunjuk user berisi id hcis. FK ke tabel `users` lokal
 * (tm_system) tidak lagi valid → di-drop. Kolom tetap ada; relasi resolve via
 * model User (hcis). Nama constraint dicari dinamis agar aman lintas environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection()->getDatabaseName();

        $fks = DB::select(
            "SELECT TABLE_NAME, CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = 'users'",
            [$db]
        );

        foreach ($fks as $fk) {
            try {
                DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Throwable $e) {
                // Constraint sudah tidak ada — abaikan.
            }
        }
    }

    public function down(): void
    {
        // Tidak dibalik otomatis: penambahan ulang FK butuh id user lokal yang
        // konsisten (arsitektur sudah beralih ke user hcis).
    }
};
