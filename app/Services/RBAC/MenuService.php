<?php

namespace App\Services\RBAC;

use Illuminate\Support\Collection;

class MenuService
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    public function all(): Collection
    {
        return $this->roleService
            ->role()?->menus
            ->sortBy('sort_order')
            ->values()
            ?? collect();
    }
}