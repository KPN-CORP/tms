<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectCategoryController extends Controller
{
    public function index(Request $request)
    {
        $editing = ProjectCategory::find($request->integer('edit'));

        return Inertia::render('Admin/ProjectCategory/Index', [
            'categories' => ProjectCategory::orderBy('name')->get()->map(fn (ProjectCategory $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'leader_grade_min' => $c->leader_grade_min,
                'leader_grade_max' => $c->leader_grade_max,
                'sponsor_grade_min' => $c->sponsor_grade_min,
                'sponsor_grade_max' => $c->sponsor_grade_max,
                'max_team_members' => $c->max_team_members,
                'required_roles' => $c->requiredRoles(),
                'is_active' => (bool) $c->is_active,
                'urls' => [
                    'edit' => route('admin.project-categories.index', ['edit' => $c->id]),
                    'update' => route('admin.project-categories.update', $c),
                    'toggle' => route('admin.project-categories.toggle', $c),
                    'destroy' => route('admin.project-categories.destroy', $c),
                ],
            ])->values(),

            // Form yang sama dipakai untuk tambah & ubah; `editing` menentukan
            // tujuan submit dan nilai awalnya.
            'editing' => $editing ? [
                'id' => $editing->id,
                'name' => $editing->name,
                'code' => $editing->code,
                'leader_grade_min' => $editing->leader_grade_min,
                'leader_grade_max' => $editing->leader_grade_max,
                'sponsor_grade_min' => $editing->sponsor_grade_min,
                'sponsor_grade_max' => $editing->sponsor_grade_max,
                'max_team_members' => $editing->max_team_members,
                'required_roles' => $editing->requiredRoles(),
            ] : null,

            'urls' => [
                'index' => route('admin.project-categories.index'),
                'store' => route('admin.project-categories.store'),
                'update' => $editing ? route('admin.project-categories.update', $editing) : null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        ProjectCategory::create($data + ['is_active' => true]);

        return redirect()->route('admin.project-categories.index')
            ->with('success', __('flash.categories.created', ['code' => $data['code']]));
    }

    public function update(Request $request, ProjectCategory $category)
    {
        $data = $this->validated($request, $category);
        $category->update($data);

        return redirect()->route('admin.project-categories.index')
            ->with('success', __('flash.categories.updated', ['code' => $data['code']]));
    }

    public function toggle(ProjectCategory $category)
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success',
            __($category->is_active ? 'flash.categories.activated' : 'flash.categories.deactivated'));
    }

    public function destroy(ProjectCategory $category)
    {
        // Cegah hapus bila masih dipakai project (hindari data project menggantung) — arsipkan saja.
        if (Project::where('project_category_id', $category->id)->exists()) {
            return back()->with('error', __('flash.categories.in_use', ['code' => $category->code]));
        }

        $code = $category->code;
        $category->delete();

        return redirect()->route('admin.project-categories.index')->with('success', __('flash.categories.deleted', ['code' => $code]));
    }

    private function validated(Request $request, ?ProjectCategory $category = null): array
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'code'              => ['required', 'string', 'max:20', Rule::unique('project_categories', 'code')->ignore($category?->id)],
            'leader_grade_min'  => ['nullable', 'integer', 'min:0'],
            'leader_grade_max'  => ['nullable', 'integer', 'min:0'],
            'sponsor_grade_min' => ['nullable', 'integer', 'min:0'],
            'sponsor_grade_max' => ['nullable', 'integer', 'min:0'],
            'max_team_members'  => ['required', 'integer', 'min:0'],
            // Multi require-role: array paralel roles[] + totals[] dari repeater.
            'roles'             => ['nullable', 'array'],
            'roles.*'           => ['nullable', 'string', 'max:255'],
            'totals'            => ['nullable', 'array'],
            'totals.*'          => ['nullable', 'string', 'max:50'],
        ]);

        $data['code'] = strtoupper($data['code']);

        // Rakit required_roles (JSON) dari input paralel; baris tanpa nama role dibuang.
        $totals = $request->input('totals', []);
        $required = [];
        foreach ($request->input('roles', []) as $i => $role) {
            $role = trim((string) $role);
            if ($role === '') {
                continue;
            }
            $required[] = ['role' => $role, 'total' => trim((string) ($totals[$i] ?? ''))];
        }
        $data['required_roles'] = $required ?: null;

        // Max Person in Team tidak boleh lebih kecil dari total jumlah Require Role.
        $roleTotal = collect($required)->sum(fn ($r) => max(0, (int) $r['total']));
        if ($roleTotal > (int) $data['max_team_members']) {
            throw ValidationException::withMessages([
                'max_team_members' => __('messages.validation.team_smaller_than_roles', [
                    'max' => $data['max_team_members'],
                    'total' => $roleTotal,
                ]),
            ]);
        }

        unset($data['roles'], $data['totals']);

        return $data;
    }
 
}
