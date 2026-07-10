<?php

namespace App\Services\RBAC;

class RbacService
{
    public function __construct(
        protected RoleService $roleService,
        protected PermissionService $permissionService,
        protected MenuService $menuService,
        protected WidgetService $widgetService,
        protected RoleRestrictionService $restrictionService
    ) {}

    public function activeRole(): ?string
    {
        return $this->roleService->activeRole();
    }

    public function role()
    {
        return $this->roleService->role();
    }

    public function can(string $permission): bool
    {
        return $this->permissionService->can($permission);
    }

    public function permissions()
    {
        return $this->permissionService->all();
    }

    public function menus()
    {
        return $this->menuService->all();
    }

    public function widgets()
    {
        return $this->widgetService->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Restriction (data scope) untuk role yang sedang aktif
    |--------------------------------------------------------------------------
    | Mengembalikan Collection ID yang boleh diakses. Jika tidak ada role
    | aktif, mengembalikan collection kosong.
    */

    public function allowedBusinessUnitIds()
    {
        $role = $this->role();

        return $role
            ? $this->restrictionService->businessUnitIds($role)
            : collect();
    }

    public function allowedCompanyIds()
    {
        $role = $this->role();

        return $role
            ? $this->restrictionService->companyIds($role)
            : collect();
    }

    public function allowedLocationIds()
    {
        $role = $this->role();

        return $role
            ? $this->restrictionService->locationIds($role)
            : collect();
    }

    public function allowedEmployeeIds()
    {
        $role = $this->role();

        return $role
            ? $this->restrictionService->employeeIds($role)
            : collect();
    }
}