<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission 'report.view' — membuka menu Report.
 *
 * Report menampilkan SELURUH data Idea & Project lintas organisasi, berbeda dari
 * My Ideas / My Project yang terbatas pada milik sendiri atau yang user terlibat.
 * Karena itu aksesnya dikunci permission tersendiri yang bisa dicentang per role
 * lewat Role Management (muncul di grup "Monitoring").
 *
 * Role yang sudah biasa memegang akses lintas organisasi diberi permission ini
 * agar menu langsung tersedia tanpa penyetelan manual.
 */
return new class extends Migration
{
    private const NAMA = 'report.view';
    private const ROLE_AWAL = ['Admin', 'Super Admin'];

    public function up(): void
    {
        $id = DB::table('permissions')->where('name', self::NAMA)->where('guard_name', 'web')->value('id');

        if (! $id) {
            $id = DB::table('permissions')->insertGetId([
                'name' => self::NAMA, 'guard_name' => 'web',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (DB::table('roles')->whereIn('name', self::ROLE_AWAL)->where('guard_name', 'web')->pluck('id') as $roleId) {
            $sudah = DB::table('role_has_permissions')
                ->where('permission_id', $id)->where('role_id', $roleId)->exists();

            if (! $sudah) {
                DB::table('role_has_permissions')->insert(['permission_id' => $id, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', self::NAMA)->where('guard_name', 'web')->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
