<?php

namespace App\Services\RBAC;

use Illuminate\Support\Collection;

class PermissionService
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    public function all(): Collection
    {
        return $this->roleService
            ->role()?->permissions
            ?? collect();
    }

    public function can(string $permission): bool
    {
        return $this->all()
            ->contains('slug', $permission);
    }
}