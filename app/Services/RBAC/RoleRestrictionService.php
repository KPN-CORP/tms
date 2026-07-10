<?php

namespace App\Services\RBAC;

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menghitung "scope" data efektif untuk sebuah Role berdasarkan
 * pembatasan (restriction) yang dipilih saat pembuatan role.
 *
 * Aturan:
 *  - Pivot kosong pada suatu dimensi = tanpa pembatasan (akses semua) pada dimensi itu.
 *  - Hierarki dijaga: Business Unit -> Company -> Location.
 *    Membatasi Business Unit otomatis mempersempit Company & Location di bawahnya.
 *
 * Setiap method mengembalikan Collection berisi ID yang BOLEH diakses,
 * sehingga langsung bisa dipakai pada query: ->whereIn('id', $ids).
 */
class RoleRestrictionService
{
    public function businessUnitIds(Role $role): Collection
    {
        $restricted = $role->businessUnits->pluck('id');

        return $restricted->isNotEmpty()
            ? $restricted->values()
            : BusinessUnit::pluck('id');
    }

    public function companyIds(Role $role): Collection
    {
        // Company yang berada di bawah Business Unit yang diizinkan.
        $base = Company::whereIn('business_unit_id', $this->businessUnitIds($role))
            ->pluck('id');

        $restricted = $role->companies->pluck('id');

        return $restricted->isNotEmpty()
            ? $restricted->intersect($base)->values()
            : $base->values();
    }

    public function locationIds(Role $role): Collection
    {
        // Location yang berada di bawah Company yang diizinkan.
        $base = Location::whereIn('company_id', $this->companyIds($role))
            ->pluck('id');

        $restricted = $role->locations->pluck('id');

        return $restricted->isNotEmpty()
            ? $restricted->intersect($base)->values()
            : $base->values();
    }

    public function employeeIds(Role $role): Collection
    {
        // Employee tidak terhubung ke org, jadi hanya dibatasi daftar langsung.
        $restricted = $role->employees->pluck('id');

        return $restricted->isNotEmpty()
            ? $restricted->values()
            : User::pluck('id');
    }
}
