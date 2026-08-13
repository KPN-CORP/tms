<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return $this->name ?? ('Role #' . $this->getKey());
    }

    // Paksa koneksi tm_system (default). Tanpa ini, saat User (koneksi hcis)
    // memanggil roles(), Laravel morphToMany mewariskan koneksi hcis ke Role →
    // pivot model_has_roles salah database. Role/pivot HARUS di tm_system.
    protected $connection = 'mysql';

    // RBAC (roles, permissions, assignment) ditangani sepenuhnya oleh Spatie.
    //commit
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
