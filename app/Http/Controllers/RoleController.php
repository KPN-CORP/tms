<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Manage Role — daftar semua role.
     */
    public function manageRole()
    {
        // employees_count & users_count dihitung dari tabel PIVOT (role_employees,
        // model_has_roles) di koneksi default — TIDAK join tabel users (yang ada di
        // database hcis), agar tak terjadi query lintas-DB yang ditolak izinnya di staging.
        $roles = Role::query()
            ->with('permissions')
            ->withCount(['businessUnits', 'companies', 'locations'])
            ->selectRaw('(select count(*) from role_employees where role_employees.role_id = roles.id) as employees_count')
            ->selectRaw(
                '(select count(*) from model_has_roles where model_has_roles.role_id = roles.id and model_has_roles.model_type = ?) as users_count',
                [\App\Models\User::class]
            )
            ->orderBy('name')
            ->get();

        return view('admin.role.index', compact('roles'));
    }

    /**
     * Form Create Role.
     */
    public function createRole()
    {
        return view('admin.role.create', $this->formData());
    }

    /**
     * Simpan Role baru.
     */
    public function saveRole(Request $request)
    {
        $validated = $this->validateRole($request);

        $role = Role::create([
            'name'       => $validated['name'],
            'guard_name' => 'web',
        ]);

        $this->syncRole($role, $validated);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role \"{$role->name}\" created successfully.");
    }

    /**
     * Form Edit Role.
     */
    public function editRole(Role $role)
    {
        return view('admin.role.edit', $this->formData() + ['role' => $role]);
    }

    /**
     * Update Role.
     */
    public function updateRole(Request $request, Role $role)
    {
        $validated = $this->validateRole($request, $role);

        $role->update(['name' => $validated['name']]);

        $this->syncRole($role, $validated);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role \"{$role->name}\" updated successfully.");
    }

    /**
     * Hapus Role.
     */
    public function deleteRole(Role $role)
    {
        $name = $role->name;
        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role \"{$name}\" deleted.");
    }

    /**
     * Form Assign Users ke sebuah role.
     */
    public function assignUser(Role $role)
    {
        return view('admin.role.assign-user', [
            'role'        => $role,
            'users'       => User::orderBy('name')->get(),
            'assignedIds' => $role->users()->pluck('users.id')->all(),
        ]);
    }

    /**
     * Simpan Assign Users.
     */
    public function saveAssignUser(Request $request, Role $role)
    {
        $validated = $request->validate([
            'users'   => ['array'],
            'users.*' => ['integer', 'exists:users,id'],
        ]);

        $role->users()->sync($validated['users'] ?? []);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Users for role \"{$role->name}\" updated.");
    }

    /* ----------------------------------------------------------------- */

    private function formData(): array
    {
        return [
            'permissions'   => Permission::orderBy('name')->get(),
            'businessUnits' => BusinessUnit::orderBy('name')->get(),
            'companies'     => Company::orderBy('name')->get(),
            'locations'     => Location::orderBy('name')->get(),
            'employees'     => User::orderBy('name')->get(),
        ];
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
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
    }

    private function syncRole(Role $role, array $validated): void
    {
        // Checkbox mengirim ID permission; Spatie syncPermissions menafsirkan
        // string sebagai NAMA, jadi resolve dulu ke objek Permission by-id.
        $permissions = Permission::whereIn('id', $validated['permissions'] ?? [])->get();

        $role->syncPermissions($permissions);
        $role->businessUnits()->sync($validated['business_units'] ?? []);
        $role->companies()->sync($validated['companies'] ?? []);
        $role->locations()->sync($validated['locations'] ?? []);
        $role->employees()->sync($validated['employees'] ?? []);
    }
}
