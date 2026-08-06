<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Autentikasi fallback ke database hcis (kpncorp) tabel users.
 * Bila email+password cocok di hcis, buat/sinkron user lokal (tm_system) sebagai
 * "mirror" lalu kembalikan user lokal itu — agar role, relasi, & FK aplikasi jalan.
 */
class HcisAuthService
{
    /** Role default untuk user hasil sinkron dari hcis (null = tanpa role). */
    private const DEFAULT_ROLE = 'Employee';

    public function attempt(string $email, string $password): ?User
    {
        $email = trim($email);
        if ($email === '' || $password === '') {
            return null;
        }

        $hcis = DB::connection('kpncorp')->table('users')->where('email', $email)->first();

        if (! $hcis || empty($hcis->password) || ! Hash::check($password, $hcis->password)) {
            return null;
        }

        return $this->syncLocalUser($hcis);
    }

    /**
     * Buat/perbarui user lokal (tm_system) berdasarkan data hcis.
     * CATATAN: hcis HANYA dibaca (SELECT) — tidak ada insert/update ke hcis.
     */
    private function syncLocalUser(object $hcis): User
    {
        return $this->mirror($hcis->email, $hcis->name ?? null, $hcis->employee_id ?? null);
    }

    /**
     * Find-or-create user lokal (mirror) dari data hcis + pastikan role Employee.
     * Dipakai login (setelah verifikasi password) & Committee Assignment (pilih approver).
     * TIDAK menulis ke hcis.
     */
    public function mirror(string $email, ?string $name = null, $employeeId = null): User
    {
        $email = trim($email);

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update([
                'name'        => $name ?: $user->name,
                'employee_id' => $user->employee_id ?: $employeeId,
            ]);
        } else {
            // Password lokal acak (user hcis diverifikasi via hcis saat login).
            $user = User::create([
                'email'       => $email,
                'name'        => $name ?: $email,
                'employee_id' => $employeeId,
                'password'    => Str::random(40),
            ]);
        }

        // SEMUA user hcis otomatis punya role Employee (self-healing, additive).
        $this->ensureDefaultRole($user);

        return $user;
    }

    /**
     * Pastikan SETIAP user hcis punya role Employee (tanpa terkecuali).
     * Additive: menambah Employee bila belum ada, TIDAK menghapus role lain.
     */
    private function ensureDefaultRole(User $user): void
    {
        if (! self::DEFAULT_ROLE || $user->hasRole(self::DEFAULT_ROLE)) {
            return;
        }

        if (Role::where('name', self::DEFAULT_ROLE)->exists()) {
            $user->assignRole(self::DEFAULT_ROLE);
        }
    }
}
