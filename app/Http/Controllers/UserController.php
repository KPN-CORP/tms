<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * User Management (izin: user.manage) — §9.5.
 * CRUD user + set job_level, employee_id, unit/department, dan assign role (Spatie).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('q'));
        $roleFilter = $request->integer('role');

        $users = User::query()
            ->with(['roles', 'businessUnit', 'department'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter, function ($q) use ($roleFilter) {
                // model_has_roles ada di DB default (tms), sedangkan User di hcis — hindari
                // JOIN lintas-DB (whereHas): ambil id user dari pivot via koneksi mysql,
                // lalu filter User by id.
                $userIds = DB::connection('mysql')->table('model_has_roles')
                    ->where('role_id', $roleFilter)
                    ->where('model_type', User::class)
                    ->pluck('model_id');
                $q->whereIn('id', $userIds);
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.user.index', [
            'users'  => $users,
            'roles'  => Role::orderBy('name')->get(),
            'search' => $search,
            'roleFilter' => $roleFilter,
        ]);
    }

    public function create()
    {
        return view('admin.user.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateUser($request);

        $user = User::create([
            'name'             => $data['name'],
            'email'            => $data['email'],
            'password'         => Hash::make($data['password']),
            'employee_id'      => $data['employee_id'] ?? null,
            'business_unit_id' => $data['business_unit_id'] ?? null,
            'department_id'    => $data['department_id'] ?? null,
            'job_level'        => $data['job_level'] ?? null,
            'summary'          => $data['summary'] ?? null,
        ]);

        $user->syncRoles(Role::whereIn('id', $data['roles'] ?? [])->pluck('name')->all());

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User \"{$user->name}\" created.");
    }

    public function edit(User $user)
    {
        return view('admin.user.edit', $this->formData() + [
            'user'        => $user,
            'assignedIds' => $user->roles()->pluck('roles.id')->all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateUser($request, $user);

        $user->update([
            'name'             => $data['name'],
            'email'            => $data['email'],
            'employee_id'      => $data['employee_id'] ?? null,
            'business_unit_id' => $data['business_unit_id'] ?? null,
            'department_id'    => $data['department_id'] ?? null,
            'job_level'        => $data['job_level'] ?? null,
            'summary'          => $data['summary'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        $user->syncRoles(Role::whereIn('id', $data['roles'] ?? [])->pluck('name')->all());

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User \"{$user->name}\" updated.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User \"{$name}\" deleted.");
    }

    /* ----------------------------------------------------------------- */

    private function formData(): array
    {
        return [
            'roles'         => Role::orderBy('name')->get(),
            'businessUnits' => BusinessUnit::orderBy('name')->get(),
            'departments'   => Department::orderBy('name')->get(),
            'jobLevels'     => User::JOB_LEVELS,
        ];
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            // users ada di hcis (kpncorp) — cek unik di koneksi itu, bukan default (tms).
            'email'            => ['required', 'email', 'max:255', Rule::unique('kpncorp.users', 'email')->ignore($user?->id)],
            // Password wajib saat create, opsional saat edit.
            'password'         => [$user ? 'nullable' : 'required', 'nullable', 'string', 'min:8', 'confirmed'],
            'employee_id'      => ['nullable', 'string', 'max:50', Rule::unique('kpncorp.users', 'employee_id')->ignore($user?->id)],
            'business_unit_id' => ['nullable', 'integer', 'exists:business_units,id'],
            'department_id'    => ['nullable', 'integer', 'exists:departments,id'],
            'job_level'        => ['nullable', 'integer', Rule::in(array_keys(User::JOB_LEVELS))],
            'summary'          => ['nullable', 'string', 'max:2000'],
            'roles'            => ['array'],
            'roles.*'          => ['integer', 'exists:roles,id'],
        ]);
    }
}
