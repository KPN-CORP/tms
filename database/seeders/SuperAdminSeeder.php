<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role Super Admin — pemegang SELURUH permission.
 *
 * Bedanya dengan role lain: daftarnya tidak ditulis satu per satu. Seeder ini
 * mengambil semua baris tabel `permissions` lalu sync-kan ke role, sehingga
 * permission baru otomatis ikut begitu seeder dijalankan ulang — tidak ada
 * daftar kedua yang bisa ketinggalan.
 *
 * KARENA ITU URUTANNYA PENTING: jalankan setelah RolesAndPermissionsSeeder
 * membuat semua permission-nya. RolesAndPermissionsSeeder sudah memanggil
 * seeder ini di akhir, jadi menjalankan yang itu saja pun sudah benar.
 *
 * CATATAN — ini BUKAN yang membuat Super Admin lolos semua izin saat runtime.
 * Itu tugas Gate::before di AppServiceProvider, yang meloloskan Super Admin
 * tanpa melihat tabel sama sekali. Sync di sini membuat yang TERSIMPAN cocok
 * dengan yang BERLAKU: halaman Role Management menampilkan semua kotak
 * tercentang (bukan 14 dari 36), dan akses tetap utuh seandainya Gate::before
 * suatu saat dicabut.
 *
 * Penetapan user opsional lewat env SUPER_ADMIN_EMAILS (dipisah koma). Role
 * ditambahkan (assignRole), bukan syncRoles — role lain milik orang itu tidak
 * ikut terhapus. User hcis TIDAK pernah ditulis: assignRole hanya menyentuh
 * pivot model_has_roles di tm_system.
 */
class SuperAdminSeeder extends Seeder
{
    public const ROLE = 'Super Admin';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => self::ROLE, 'guard_name' => 'web']);

        $permissions = Permission::where('guard_name', 'web')->pluck('name');

        if ($permissions->isEmpty()) {
            $this->command?->warn(
                'Tabel permissions kosong — jalankan RolesAndPermissionsSeeder dulu.'
            );

            return;
        }

        $role->syncPermissions($permissions->all());

        $this->command?->info(
            self::ROLE.': '.$permissions->count().' permission ter-sync.'
        );

        $this->assignUsers($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Beri role ke user yang emailnya didaftarkan di SUPER_ADMIN_EMAILS. */
    private function assignUsers(Role $role): void
    {
        $emails = collect(explode(',', (string) env('SUPER_ADMIN_EMAILS')))
            ->map(fn ($email) => trim($email))
            ->filter()
            ->unique();

        if ($emails->isEmpty()) {
            return;
        }

        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->command?->warn("User {$email} tidak ada di hcis — dilewati.");

                continue;
            }

            if (! $user->hasRole($role->name)) {
                $user->assignRole($role->name);
            }

            $this->command?->info("{$email} → ".self::ROLE.'.');
        }
    }
}
