<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\CommitteeAssignment;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommitteeAssignmentController extends Controller
{
    private const MAX_LAYERS = 10;

    public function index(Request $request)
    {
        $businessUnits = BusinessUnit::orderBy('name')->get();
        $type          = array_key_exists($request->query('approval_type'), CommitteeAssignment::TYPES)
            ? $request->query('approval_type')
            : 'idea';
        $selectedBuId  = $request->integer('business_unit_id') ?: optional($businessUnits->first())->id;

        // Department routing (Unit) hanya berlaku untuk approval_type = idea (T-95).
        $departments = $type === 'idea'
            ? Department::where('business_unit_id', $selectedBuId)->orderBy('name')->get()
            : collect();

        $selectedDeptId = null;
        if ($type === 'idea' && $request->filled('department_id')) {
            $deptId = $request->integer('department_id');
            if ($departments->contains('id', $deptId)) {
                $selectedDeptId = $deptId;
            }
        }

        // [layer => user_id]
        $assignments = CommitteeAssignment::where('approval_type', $type)
            ->where('business_unit_id', $selectedBuId)
            ->when(
                $selectedDeptId === null,
                fn ($q) => $q->whereNull('department_id'),
                fn ($q) => $q->where('department_id', $selectedDeptId)
            )
            ->pluck('user_id', 'layer');

        return view('admin.committee.index', [
            'businessUnits'  => $businessUnits,
            'types'          => CommitteeAssignment::TYPES,
            'type'           => $type,
            'selectedBuId'   => $selectedBuId,
            'departments'    => $departments,
            'selectedDeptId' => $selectedDeptId,
            'users'          => User::with('businessUnit')->orderBy('name')->get(),
            'assignments'    => $assignments,
            'maxLayers'      => self::MAX_LAYERS,
            'configured'     => $this->configuredSets(),
        ]);
    }

    /**
     * Daftar seluruh committee yang sudah diatur, dikelompokkan per
     * (approval type + business unit + department).
     */
    private function configuredSets()
    {
        return CommitteeAssignment::with(['user', 'businessUnit', 'department'])
            ->orderBy('approval_type')
            ->orderBy('business_unit_id')
            ->orderBy('layer')
            ->get()
            ->groupBy(fn ($r) => $r->approval_type . '|' . $r->business_unit_id . '|' . ($r->department_id ?? ''))
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'approval_type'    => $first->approval_type,
                    'type_label'       => CommitteeAssignment::TYPES[$first->approval_type] ?? $first->approval_type,
                    'business_unit_id' => $first->business_unit_id,
                    'bu_name'          => optional($first->businessUnit)->name,
                    'department_id'    => $first->department_id,
                    'dept_name'        => $first->department_id ? optional($first->department)->name : 'Semua Unit',
                    'layers'           => $rows->sortBy('layer')
                        ->map(fn ($r) => ['layer' => $r->layer, 'name' => optional($r->user)->name])
                        ->values(),
                ];
            })
            ->values();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'approval_type'    => ['required', Rule::in(array_keys(CommitteeAssignment::TYPES))],
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'department_id'    => ['nullable', 'integer', 'exists:departments,id'],
            'layers'           => ['array'],
            'layers.*'         => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Department routing hanya untuk idea; tipe lain selalu BU-wide (null).
        $departmentId = $data['approval_type'] === 'idea' ? ($data['department_id'] ?? null) : null;

        DB::transaction(function () use ($data, $departmentId) {
            CommitteeAssignment::where('approval_type', $data['approval_type'])
                ->where('business_unit_id', $data['business_unit_id'])
                ->when(
                    $departmentId === null,
                    fn ($q) => $q->whereNull('department_id'),
                    fn ($q) => $q->where('department_id', $departmentId)
                )
                ->delete();

            foreach (($data['layers'] ?? []) as $layer => $userId) {
                if ($userId) {
                    CommitteeAssignment::create([
                        'approval_type'    => $data['approval_type'],
                        'business_unit_id' => $data['business_unit_id'],
                        'department_id'    => $departmentId,
                        'layer'            => (int) $layer,
                        'user_id'          => $userId,
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.committee.index', array_filter([
                'approval_type'    => $data['approval_type'],
                'business_unit_id' => $data['business_unit_id'],
                'department_id'    => $departmentId,
            ]))
            ->with('success', 'Committee assignment saved.');
    }

    /** Hapus seluruh layer untuk satu set (type + BU + department). */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'approval_type'    => ['required', Rule::in(array_keys(CommitteeAssignment::TYPES))],
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'department_id'    => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $departmentId = $data['approval_type'] === 'idea' ? ($data['department_id'] ?? null) : null;

        CommitteeAssignment::where('approval_type', $data['approval_type'])
            ->where('business_unit_id', $data['business_unit_id'])
            ->when(
                $departmentId === null,
                fn ($q) => $q->whereNull('department_id'),
                fn ($q) => $q->where('department_id', $departmentId)
            )
            ->delete();

        return redirect()
            ->route('admin.committee.index')
            ->with('success', 'Committee assignment dihapus.');
    }
}
