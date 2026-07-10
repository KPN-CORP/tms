<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Halaman Create Role
     */
    public function createRole()
    {
        return view('admin.role.create', [

            'businessUnits' => BusinessUnit::orderBy('name')->get(),

            'companies' => Company::orderBy('name')->get(),

            'locations' => Location::orderBy('name')->get(),

            'employees' => User::orderBy('name')->get(),

            'permissions' => Permission::orderBy('name')->get(),

        ]);
    }

    /**
     * Simpan Role
     */
    public function saveRole(Request $request)
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions'      => ['array'],
            'permissions.*'    => ['integer', 'exists:permissions,id'],
            'business_units'   => ['array'],
            'business_units.*' => ['integer', 'exists:business_units,id'],
            'companies'        => ['array'],
            'companies.*'      => ['integer', 'exists:companies,id'],
            'locations'        => ['array'],
            'locations.*'      => ['integer', 'exists:locations,id'],
            'employees'        => ['array'],
            'employees.*'      => ['integer', 'exists:users,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        // Restriction (kosong = tanpa pembatasan / akses semua)
        $role->businessUnits()->sync($validated['business_units'] ?? []);
        $role->companies()->sync($validated['companies'] ?? []);
        $role->locations()->sync($validated['locations'] ?? []);
        $role->employees()->sync($validated['employees'] ?? []);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * Manage Role
     *
     * Menu "Role Management" (roles.index) menampilkan halaman Create Role.
     */
    public function manageRole()
    {
        return $this->createRole();
    }

    /**
     * Update Role
     */
    public function updateRole()
    {

    }


    /**
     * Assign User
     */
    public function assignUser()
    {

    }

    /**
     * Save Assign User
     */
    public function saveAssignUser(Request $request)
    {

    }

}