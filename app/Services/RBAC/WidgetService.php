<?php

namespace App\Services\RBAC;

use Illuminate\Support\Collection;

class WidgetService
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    public function all(): Collection
    {
        return $this->roleService
            ->role()?->widgets
            ->sortBy('sort_order')
            ->values()
            ?? collect();
    }
}