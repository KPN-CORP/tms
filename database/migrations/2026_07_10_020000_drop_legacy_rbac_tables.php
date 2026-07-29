<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 refactor RBAC -> Spatie.
 *
 * Membuang seluruh tabel RBAC lama (custom) sebelum migrasi Spatie
 * membuat tabel roles/permissions yang baru. Data org (business_units,
 * companies, locations, users) TIDAK disentuh.
 *
 * Tidak reversible (down sengaja kosong): data lama sudah dibackup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Pivot & tabel scope (anak) lebih dulu
        Schema::dropIfExists('permission_menus');
        Schema::dropIfExists('role_menus');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('role_widgets');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_business_units');
        Schema::dropIfExists('role_companies');
        Schema::dropIfExists('role_locations');
        Schema::dropIfExists('role_employees');

        // Induk
        Schema::dropIfExists('menus');
        Schema::dropIfExists('widgets');
        Schema::dropIfExists('roles');        // Spatie akan buat ulang
        Schema::dropIfExists('permissions');  // Spatie akan buat ulang

        Schema::enableForeignKeyConstraints();

        // Kolom role string redundan di users
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        // Tidak reversible. Restore dari backup_pre_spatie_2026-07-10.sql bila perlu.
    }
};
