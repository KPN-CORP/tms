<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission 'dashboard.act-as' — pengendali tombol "Act as" di Dashboard.
 *
 * Sebelumnya tombol itu dikunci ke nama role (Admin / Super Admin) di dalam kode,
 * sehingga role baru tidak mungkin mendapatkannya tanpa mengubah kode. Sekarang
 * menjadi permission biasa yang bisa dicentang lewat Role Management, dan ikut
 * tampil otomatis di grup "Monitoring" pada form role (dikelompokkan dari prefix
 * 'dashboard').
 *
 * Role yang selama ini punya tombolnya langsung diberi permission ini supaya
 * perilaku yang berjalan tidak berubah.
 */
return new class extends Migration
{
    private const NAMA = 'dashboard.act-as';
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

        $roles = DB::table('roles')->whereIn('name', self::ROLE_AWAL)->where('guard_name', 'web')->pluck('id');
        foreach ($roles as $roleId) {
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
