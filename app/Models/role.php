<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    // RBAC (roles, permissions, assignment) ditangani sepenuhnya oleh Spatie.

    /*
    |--------------------------------------------------------------------------
    | Restrict / Scope (kosong = tanpa pembatasan / akses semua)
    |--------------------------------------------------------------------------
    */

    public function businessUnits()
    {
        return $this->belongsToMany(BusinessUnit::class, 'role_business_units');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'role_companies');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'role_locations');
    }

    public function employees()
    {
        return $this->belongsToMany(User::class, 'role_employees');
    }
}
