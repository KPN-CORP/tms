<?php

namespace App\Services\RBAC;

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung scope data efektif untuk seorang User berdasarkan
 * restriction (restrict) pada SEMUA role yang dimilikinya (union).
 *
 * Aturan:
 *  - Dimensi kosong pada suatu role = "semua" untuk role itu.
 *  - Scope user = GABUNGAN (union) scope tiap role. Maka satu role tanpa
 *    restrict membuat user bisa akses semua pada dimensi tsb.
 *  - Hierarki dijaga per role: Business Unit -> Company -> Location.
 *  - User tanpa role sama sekali = tidak boleh akses apa pun (collection kosong).
 *
 * Setiap method publik mengembalikan Collection ID yang boleh diakses,
 * siap dipakai: ->whereIn('id', $ids).
 */
class RoleScopeService
{
    public function businessUnitIds(User $user): Collection
    {
        return $this->union($user, fn (Role $role) => $this->roleBusinessUnitIds($role));
    }

    public function companyIds(User $user): Collection
    {
        return $this->union($user, fn (Role $role) => $this->roleCompanyIds($role));
    }

    public function locationIds(User $user): Collection
    {
        return $this->union($user, fn (Role $role) => $this->roleLocationIds($role));
    }

    public function employeeIds(User $user): Collection
    {
        return $this->union($user, fn (Role $role) => $this->roleEmployeeIds($role));
    }

    /* ---- Union lintas role ------------------------------------------- */

    private function union(User $user, callable $perRole): Collection
    {
        if ($user->roles->isEmpty()) {
            return collect();
        }

        return $user->roles
            ->flatMap($perRole)
            ->unique()
            ->sort()
            ->values();
    }

    /* ---- Scope per satu role (hierarki + blank=semua) ---------------- */

    private function roleBusinessUnitIds(Role $role): Collection
    {
        $restricted = $role->businessUnits->pluck('id');

        return $restricted->isNotEmpty()
            ? $restricted->values()
            : BusinessUnit::pluck('id');
    }

    private function roleCompanyIds(Role $role): Collection
    {
        $base = Company::whereIn('business_unit_id', $this->roleBusinessUnitIds($role))
            ->pluck('id');

        $restricted = $role->companies->pluck('id');

        return $restricted->isNotEmpty()
            ? $restricted->intersect($base)->values()
            : $base->values();
    }

    /**
     * private function roleCompanyIds(Role $role): collection
     * $base = Company::whereIn('business_unit_id', $this -> roleBusinessUnitIds)
     */

    private function roleLocationIds(Role $role): Collection
    {
        $base = Location::whereIn('company_id', $this->roleCompanyIds($role))
            ->pluck('id');

        $restricted = $role->locations->pluck('id');

        return $restricted->isNotEmpty()
            ? $restricted->intersect($base)->values()
            : $base->values();
    }


    private function roleEmployeeIds(Role $role): Collection
    {
        // role_employees ada di koneksi mysql; relasi employees() (User) jatuh ke kpncorp.
        // Baca langsung via mysql agar tak error di staging.
        $restricted = DB::connection('mysql')->table('role_employees')
            ->where('role_id', $role->id)
            ->pluck('user_id');

        return $restricted->isNotEmpty()
            ? $restricted->values()
            : User::pluck('id');
    }

}

