<?php

namespace App\Http\Controllers;

use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    private function validated(Request $request, ?ProjectCategory $category = null): array
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'code'              => ['required', 'string', 'max:20', Rule::unique('project_categories', 'code')->ignore($category?->id)],
            'leader_grade_min'  => ['required', 'integer', 'min:0'],
            'leader_grade_max'  => ['required', 'integer', 'min:0'],
            'sponsor_grade_min' => ['required', 'integer', 'min:0'],
            'sponsor_grade_max' => ['required', 'integer', 'min:0'],
            'max_team_members'  => ['required', 'integer', 'min:0'],
        ]);

        $data['code'] = strtoupper($data['code']);

        return $data;
    }
 
}
