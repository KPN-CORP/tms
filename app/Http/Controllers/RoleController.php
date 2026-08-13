<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\KpnBusinessUnit;
use App\Models\KpnCompany;
use App\Models\KpnLocation;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrgResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

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
            // Sembunyikan role Employee dari daftar — wajib & otomatis untuk SEMUA user
            // (via SSO), jadi tak perlu dikelola di sini. Super Admin & role lain tetap tampil.
            ->where('name', '!=', 'Employee')
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
        return view('admin.role.create', $this->formData() + ['assignedEmployees' => collect()]);
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
        // Employee terpilih dibaca dari role_employees (mysql), lalu load User-nya (hcis).
        $empIds = DB::connection('mysql')->table('role_employees')->where('role_id', $role->id)->pluck('user_id');

        return view('admin.role.edit', $this->formData() + [
            'role'              => $role,
            'assignedEmployees' => User::whereIn('id', $empIds)->orderBy('name')->get(),
        ]);
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
     *
     * FK antar tabel sudah di-drop, sehingga $role->delete() TIDAK membersihkan
     * baris pivot terkait. Tanpa ini, menghapus role meninggalkan assignment
     * menggantung di model_has_roles (mis. user seolah masih punya role yang sudah
     * dihapus). Maka bersihkan SEMUA pivot role (koneksi mysql) lebih dulu.
     */
    public function deleteRole(Role $role)
    {
        $name = $role->name;

        $conn = DB::connection('mysql');
        foreach ([
            'model_has_roles',       // pivot Spatie: role ↔ user
            'role_has_permissions',  // pivot Spatie: role ↔ permission
            'role_business_units',   // restrict scope
            'role_companies',
            'role_locations',
            'role_employees',
        ] as $pivot) {
            $conn->table($pivot)->where('role_id', $role->id)->delete();
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role \"{$name}\" deleted.");
    }

    /**
     * Form Assign Users ke sebuah role.
     */
    public function assignUser(Role $role)
    {
        // User terpilih dibaca dari model_has_roles (mysql), lalu load User-nya (hcis).
        // TIDAK memuat semua user — pemilihan via search AJAX (org.users).
        $assignedIds = DB::connection('mysql')->table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->pluck('model_id');

        return view('admin.role.assign-user', [
            'role'          => $role,
            'assignedUsers' => User::whereIn('id', $assignedIds)->orderBy('name')->get(),
        ]);
    }

    /**
     * Simpan Assign Users.
     */
    public function saveAssignUser(Request $request, Role $role)
    {
        $validated = $request->validate([
            'users'   => ['array'],
            'users.*' => ['integer', 'exists:kpncorp.users,id'],
        ]);

        // model_has_roles di koneksi mysql (relasi users() jatuh ke kpncorp yang tak punya tabelnya).
        $this->syncPivotMysql('model_has_roles', 'model_id', $role->id, $validated['users'] ?? [], ['model_type' => User::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Users for role \"{$role->name}\" updated.");
    }

    /* ----------------------------------------------------------------- */

    private function formData(): array
    {
        // 'employees' TIDAK dimuat penuh (User ada ribuan di hcis) — pemilihan employee
        // memakai search AJAX (org.users). Yang di-pass hanya opsi terpilih via caller.
        return [
            'permissions'   => Permission::orderBy('name')->get(),
            // Dropdown "Restrict Group Company" bersumber dari hcis (master_bisnisunits.nama_bisnis),
            // sama seperti field Business Unit di form ide. Value = NAMA; saat simpan di-resolve
            // (find-or-create) ke business_units lokal untuk dapat id pivot.
            'businessUnits' => KpnBusinessUnit::names(),
            // 'companies' & 'locations' TIDAK dimuat: dropdown di-cascade via AJAX
            // (Restrict Company←BU: org.companies; Restrict Location←BU: org.locations).
        ];
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        // Restrict Company cascade dari Restrict Group Company: opsi valid = contribution_level
        // dari companies (hcis) yang company_name-nya memuat salah satu BU terpilih.
        $allowedCompanies = collect($request->input('business_units', []))
            ->flatMap(fn ($bu) => KpnCompany::contributionsFor((string) $bu))
            ->unique()
            ->values()
            ->all();

        // Restrict Location cascade dari Restrict Group Company: opsi valid = area (locations)
        // yang company_name-nya (= nama BU) termasuk salah satu BU terpilih.
        $allowedLocations = collect($request->input('business_units', []))
            ->flatMap(fn ($bu) => KpnLocation::areasFor((string) $bu))
            ->unique()
            ->values()
            ->all();

        return $request->validate([
            'name'             => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions'      => ['array'],
            'permissions.*'    => ['integer', 'exists:permissions,id'],
            'business_units'   => ['array'],
            // Nilai = NAMA business unit dari hcis (bukan id lokal). Divalidasi terhadap
            // daftar nama master_bisnisunits; di-resolve ke id lokal saat sync.
            'business_units.*' => ['string', Rule::in(KpnBusinessUnit::names()->all())],
            'companies'        => ['array'],
            // Nilai = contribution_level (nama) dari hcis, tergantung BU terpilih.
            'companies.*'      => ['string', Rule::in($allowedCompanies)],
            'locations'        => ['array'],
            // Nilai = area (nama) dari hcis, tergantung Group Company (BU) terpilih.
            'locations.*'      => ['string', Rule::in($allowedLocations)],
            'employees'        => ['array'],
            'employees.*'      => ['integer', 'exists:kpncorp.users,id'],
        ]);
    }

    private function syncRole(Role $role, array $validated): void
    {
        // Checkbox mengirim ID permission; Spatie syncPermissions menafsirkan
        // string sebagai NAMA, jadi resolve dulu ke objek Permission by-id.
        $permissions = Permission::whereIn('id', $validated['permissions'] ?? [])->get();

        $role->syncPermissions($permissions);

        // business_units berisi NAMA dari hcis → find-or-create ke business_units lokal
        // (via OrgResolver, konsisten dengan flow ide) untuk mendapat id pivot.
        $org     = app(OrgResolver::class);
        $buNames = collect($validated['business_units'] ?? [])->filter()->values();
        $buIds   = $buNames->map(fn ($name) => $org->businessUnit($name)->id)->unique()->values()->all();
        $role->businessUnits()->sync($buIds);

        // companies berisi contribution_level (nama) dari hcis, cascade dari BU terpilih.
        // Tiap nama di-find-or-create ke Company lokal (per nama + business_unit_id BU pemiliknya)
        // agar dapat id pivot & tetap konsisten dengan hierarki scope (BU → Company).
        // $companyByBu: buLocalId → companyLocalId (pertama), dipakai saat resolve Location.
        $companyIds  = [];
        $companyByBu = [];
        foreach (($validated['companies'] ?? []) as $cName) {
            $ownerBu = $buNames->first(fn ($bu) => KpnCompany::query()
                ->where('company_name', 'like', '%' . $bu . '%')
                ->where('contribution_level', $cName)
                ->exists());
            $buId    = $ownerBu ? $org->businessUnit($ownerBu)->id : null;
            $company = Company::firstOrCreate(
                ['name' => $cName, 'business_unit_id' => $buId],
                ['code' => $this->orgCode($cName)]
            );
            $companyIds[] = $company->id;
            if ($buId !== null && ! isset($companyByBu[$buId])) {
                $companyByBu[$buId] = $company->id;
            }
        }
        $role->companies()->sync(array_values(array_unique($companyIds)));

        // locations berisi area (nama) dari hcis, cascade dari Group Company (BU) terpilih.
        // area → BU pemilik (locations.company_name = nama BU) → Company lokal di BU itu
        // (dari $companyByBu; bila company tak direstriksi, pakai container Company se-BU)
        // → find-or-create Location agar dapat id pivot & tetap dalam hierarki scope.
        $locationIds = [];
        foreach (($validated['locations'] ?? []) as $area) {
            $buName = KpnLocation::query()
                ->where('area', $area)
                ->whereIn('company_name', $buNames->all())
                ->value('company_name');
            if (! $buName) {
                continue; // area di luar BU terpilih — lewati
            }
            $buId      = $org->businessUnit($buName)->id;
            $companyId = $companyByBu[$buId] ?? Company::firstOrCreate(
                ['name' => $buName, 'business_unit_id' => $buId],
                ['code' => $this->orgCode($buName)]
            )->id;
            $locationIds[] = Location::firstOrCreate(['name' => $area, 'company_id' => $companyId])->id;
        }
        $role->locations()->sync(array_values(array_unique($locationIds)));

        // role_employees: pivot Role↔User. User ada di koneksi hcis, jadi relasi
        // employees() akan menjalankan pivot di kpncorp (tak ada tabelnya). Kelola
        // langsung via koneksi mysql (tempat tabel role_employees berada).
        $this->syncPivotMysql('role_employees', 'user_id', $role->id, $validated['employees'] ?? []);
    }

    /** Kode ringkas dari sebuah nama org (untuk kolom code find-or-create). */
    private function orgCode(string $name): string
    {
        return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 10)) ?: 'GEN';
    }

    /**
     * Sinkron pivot Role↔User pada koneksi MYSQL (bukan kpncorp), via delete+insert.
     *
     * @param  string  $table   nama tabel pivot (role_employees / model_has_roles)
     * @param  string  $col     kolom user (user_id / model_id)
     * @param  array<string>  $extra  kolom tambahan tetap (mis. model_type)
     */
    private function syncPivotMysql(string $table, string $col, int $roleId, array $userIds, array $extra = []): void
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));

        DB::connection('mysql')->table($table)
            ->where('role_id', $roleId)
            ->when(! empty($extra), fn ($q) => $q->where($extra))
            ->delete();

        if ($ids) {
            DB::connection('mysql')->table($table)->insert(
                array_map(fn ($uid) => ['role_id' => $roleId, $col => $uid] + $extra, $ids)
            );
        }
    }
}
