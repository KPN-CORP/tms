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

    /** Buat/perbarui user lokal berdasarkan data hcis. */
    private function syncLocalUser(object $hcis): User
    {
        $user = User::where('email', $hcis->email)->first();

        if ($user) {
            // Perbarui data profil dasar (bukan password/role — dikelola lokal).
            $user->update([
                'name'        => $hcis->name ?: $user->name,
                'employee_id' => $user->employee_id ?: ($hcis->employee_id ?? null),
            ]);

            return $user;
        }

        // Buat mirror baru. Password lokal acak (login hcis diverifikasi via hcis).
        $user = User::create([
            'email'       => $hcis->email,
            'name'        => $hcis->name ?: $hcis->email,
            'employee_id' => $hcis->employee_id ?? null,
            'password'    => Str::random(40),
        ]);

        if (self::DEFAULT_ROLE && Role::where('name', self::DEFAULT_ROLE)->exists()) {
            $user->assignRole(self::DEFAULT_ROLE);
        }

        return $user;
    }
}
