<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Draft Project Shell — committee layer terakhir menekan "Save as Draft" pada
 * form Create Project Shell tanpa harus mengisi semuanya. Baris projects tetap
 * dibuat, ditandai is_shell_draft = 1, dan DISEMBUNYIKAN dari seluruh aplikasi
 * lewat global scope pada App\Models\Project (Leader/Sponsor belum boleh
 * melihatnya). Draft hanya tampil di menu Project Shell dengan status "Draft",
 * bisa dilanjutkan atau dihapus.
 *
 * Kolom wajib dilonggarkan karena draft boleh belum lengkap; kewajiban isi
 * tetap ditegakkan saat "Create Project" (lihat ProjectController::store).
 * project_id baru dibuat ketika shell benar-benar dibuat, jadi ikut NULL-able
 * (unique index MySQL mengizinkan banyak NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'is_shell_draft')) {
            DB::statement('ALTER TABLE `projects` ADD `is_shell_draft` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`');
        }

        // Pakai SQL mentah: doctrine/dbal tidak terpasang, sehingga ->change() tidak tersedia.
        DB::statement('ALTER TABLE `projects` MODIFY `project_id` VARCHAR(50) NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_name` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_scope` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `expected_outcome` VARCHAR(500) NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_category` VARCHAR(20) NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_sponsor_id` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_leader_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Draft dibuang lebih dulu: barisnya justru yang punya kolom kosong,
        // sehingga ALTER ke NOT NULL akan gagal bila dibiarkan.
        DB::statement('DELETE FROM `projects` WHERE `is_shell_draft` = 1');

        DB::statement("UPDATE `projects` SET `project_name` = '(untitled)' WHERE `project_name` IS NULL");
        DB::statement("UPDATE `projects` SET `project_scope` = '' WHERE `project_scope` IS NULL");
        DB::statement("UPDATE `projects` SET `expected_outcome` = '' WHERE `expected_outcome` IS NULL");
        DB::statement("UPDATE `projects` SET `project_category` = '' WHERE `project_category` IS NULL");
        DB::statement('DELETE FROM `projects` WHERE `project_id` IS NULL OR `project_sponsor_id` IS NULL OR `project_leader_id` IS NULL');

        DB::statement('ALTER TABLE `projects` MODIFY `project_id` VARCHAR(50) NOT NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_name` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_scope` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `expected_outcome` VARCHAR(500) NOT NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_category` VARCHAR(20) NOT NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_sponsor_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `projects` MODIFY `project_leader_id` BIGINT UNSIGNED NOT NULL');

        if (Schema::hasColumn('projects', 'is_shell_draft')) {
            DB::statement('ALTER TABLE `projects` DROP COLUMN `is_shell_draft`');
        }
    }
};
