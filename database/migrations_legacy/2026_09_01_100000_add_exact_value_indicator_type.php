<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah pilihan Type ketiga pada Success Indicator: "Exact Value".
 * Kolomnya ENUM, jadi daftar nilainya harus diperluas — tanpa ini MySQL menolak
 * dengan "Data truncated for column 'type'".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `implementation_indicators`
            MODIFY `type` ENUM('Higher Better','Lower Better','Exact Value') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        // Baris yang terlanjur memakai nilai baru dikosongkan dulu agar ALTER tidak gagal.
        DB::statement("UPDATE `implementation_indicators` SET `type` = NULL WHERE `type` = 'Exact Value'");
        DB::statement("ALTER TABLE `implementation_indicators`
            MODIFY `type` ENUM('Higher Better','Lower Better') NULL DEFAULT NULL");
    }
};
