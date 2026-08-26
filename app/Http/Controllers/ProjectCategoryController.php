<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectCategoryController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.project-category.index', [
            'categories' => ProjectCategory::orderBy('name')->get(),
            'editing'    => ProjectCategory::find($request->integer('edit')),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        ProjectCategory::create($data + ['is_active' => true]);

        return redirect()->route('admin.project-categories.index')
            ->with('success', "Category {$data['code']} created.");
    }

    public function update(Request $request, ProjectCategory $category)
    {
        $data = $this->validated($request, $category);
        $category->update($data);

        return redirect()->route('admin.project-categories.index')
            ->with('success', "Category {$data['code']} updated.");
    }

    public function toggle(ProjectCategory $category)
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', $category->is_active ? 'Category restored.' : 'Category archived.');
    }

    public function destroy(ProjectCategory $category)
    {
        // Cegah hapus bila masih dipakai project (hindari data project menggantung) — arsipkan saja.
        if (Project::where('project_category_id', $category->id)->exists()) {
            return back()->with('error', "Category {$category->code} is still used by projects — it cannot be deleted. Archive it instead.");
        }

        $code = $category->code;
        $category->delete();

        return redirect()->route('admin.project-categories.index')->with('success', "Category {$code} deleted.");
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
                'max_team_members' => "Max Person in Team ({$data['max_team_members']}) cannot be less than the total Require Role ({$roleTotal}).",
            ]);
        }

        unset($data['roles'], $data['totals']);

        return $data;
    }
 
}
