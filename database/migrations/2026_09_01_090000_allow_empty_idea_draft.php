<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Draft ide boleh benar-benar KOSONG — user menekan "Save as Draft" tanpa
 * mengisi apa pun. Tiga kolom ini sebelumnya NOT NULL sehingga penyimpanan
 * gagal di level database walau validasi sudah dilonggarkan.
 *
 * Kewajiban isi tetap ditegakkan saat SUBMIT (lihat IdeaController::validateIdea),
 * jadi ide yang masuk alur review tetap lengkap.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pakai SQL mentah: doctrine/dbal tidak terpasang, sehingga ->change() tidak tersedia.
        DB::statement('ALTER TABLE `ideas` MODIFY `idea_name` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `ideas` MODIFY `business_unit_id` INT(11) NULL');
        DB::statement('ALTER TABLE `ideas` MODIFY `department_id` INT(11) NULL');
    }

    public function down(): void
    {
        // Kembalikan NOT NULL. Baris yang terlanjur kosong diberi nilai pengganti
        // lebih dulu agar ALTER tidak gagal.
        DB::statement("UPDATE `ideas` SET `idea_name` = '(untitled draft)' WHERE `idea_name` IS NULL");
        DB::statement('DELETE FROM `ideas` WHERE `business_unit_id` IS NULL OR `department_id` IS NULL');

        DB::statement('ALTER TABLE `ideas` MODIFY `idea_name` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `ideas` MODIFY `business_unit_id` INT(11) NOT NULL');
        DB::statement('ALTER TABLE `ideas` MODIFY `department_id` INT(11) NOT NULL');
    }
};
