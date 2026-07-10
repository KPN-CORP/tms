<?php

namespace App\Services\RBAC;

use App\Models\Role;

class RoleService
{
    protected ?Role $role = null;

    public function __construct()
    {
        $activeRole = session('active_role');

        if (!$activeRole) {
            return;
        }

        $this->role = Role::with([
            'permissions',
            'menus',
            'widgets'
        ])
        ->where('slug', $activeRole)
        ->first();
    }

    public function activeRole(): ?string
    {
        return session('active_role');
    }

    public function role(): ?Role
    {
        return $this->role;
    }
}