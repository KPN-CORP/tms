<?php

namespace App\Http\Controllers;

use App\Models\CommitteeAssignment;
use App\Models\KpnBusinessUnit;
use App\Models\KpnEmployee;
use App\Models\User;
use App\Services\HcisAuthService;
use App\Services\OrgResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommitteeAssignmentController extends Controller
{
    private const MAX_LAYERS = 10;

    public function index(Request $request, OrgResolver $org)
    {
        // Business Unit dari hcis (master_bisnisunits) — dipetakan ke BU lokal (find-or-create).
        $businessUnits = KpnBusinessUnit::names()
            ->map(fn ($name) => $org->businessUnit($name))
            ->sortBy('name')
            ->values();

        // Default kosong (tanpa pra-pilih) — user harus memilih Approval Type & BU dulu.
        $type         = array_key_exists($request->query('approval_type'), CommitteeAssignment::TYPES)
            ? $request->query('approval_type')
            : null;
        $selectedBuId = $request->integer('business_unit_id') ?: null;
        $selectedBu   = $selectedBuId ? $businessUnits->firstWhere('id', $selectedBuId) : null;

        // Unit/Department dari employees.unit (tanpa kurung), by nama BU — untuk SEMUA approval type.
        $unitNames = $selectedBu ? KpnEmployee::unitsFor($selectedBu->name) : collect();

        $selectedUnitName = null;
        $selectedDeptId   = null;
        if ($request->filled('department')) {
            $reqName = $request->get('department');
            if ($unitNames->contains($reqName)) {
                $selectedUnitName = $reqName;
                $selectedDeptId   = $org->department($reqName, $selectedBuId)->id; // find-or-create satu
            }
        }

        // --- (DI-COMMENT) Filter employee per BU+Unit. Dropdown approver kini pakai
        //     AJAX search (semua employee hcis, muncul saat user mengetik).
        //     Uncomment untuk kembali ke daftar terfilter.
        // $employees = KpnEmployee::forSelection($selectedBu->name, $selectedUnitName);

        // [layer => user_id] existing → dipetakan ke [layer => {email,label}] untuk pre-select.
        $assignments = CommitteeAssignment::where('approval_type', $type)
            ->where('business_unit_id', $selectedBuId)
            ->when(
                $selectedDeptId === null,
                fn ($q) => $q->whereNull('department_id'),
                fn ($q) => $q->where('department_id', $selectedDeptId)
            )
            ->pluck('user_id', 'layer');

        $userEmails = User::whereIn('id', $assignments->values()->filter())->pluck('email', 'id');
        $empByEmail = KpnEmployee::query()->whereIn('email', $userEmails->values()->filter()->all())->get()->keyBy('email');

        $assignedByLayer = $assignments->map(function ($uid) use ($userEmails, $empByEmail) {
            $email = $userEmails[$uid] ?? null;
            if (! $email) {
                return null;
            }

            return ['email' => $email, 'label' => optional($empByEmail[$email] ?? null)->label() ?: $email];
        });

        return view('admin.committee.index', [
            'businessUnits'     => $businessUnits,
            'types'             => CommitteeAssignment::TYPES,
            'type'              => $type,
            'selectedBuId'      => $selectedBuId,
            'unitNames'         => $unitNames,
            'selectedUnitName'  => $selectedUnitName,
            'assignedByLayer'   => $assignedByLayer,
            'employeeSearchUrl' => route('org.employees'),
            'maxLayers'         => self::MAX_LAYERS,
            'configured'        => $this->configuredSets(),
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
                    'dept_name_raw'    => optional($first->department)->name,
                    'layers'           => $rows->sortBy('layer')
                        ->map(fn ($r) => ['layer' => $r->layer, 'name' => trim(optional($r->user)->name . (optional($r->user)->employee_id ? ' - ' . optional($r->user)->employee_id : ''))])
                        ->values(),
                ];
            })
            ->values();
    }

    public function store(Request $request, OrgResolver $org, HcisAuthService $hcis)
    {
        $data = $request->validate([
            'approval_type'    => ['required', Rule::in(array_keys(CommitteeAssignment::TYPES))],
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'department'       => ['nullable', 'string', 'max:255'],
            'layers'           => ['array'],
            'layers.*'         => ['nullable', 'email'], // email employee (hcis)
        ]);

        // Unit/Department (NAMA) → find-or-create FK lokal — untuk SEMUA approval type.
        $departmentId = null;
        if (! empty($data['department'])) {
            $departmentId = $org->department($data['department'], (int) $data['business_unit_id'])->id;
        }

        DB::transaction(function () use ($data, $departmentId, $hcis) {
            CommitteeAssignment::where('approval_type', $data['approval_type'])
                ->where('business_unit_id', $data['business_unit_id'])
                ->when(
                    $departmentId === null,
                    fn ($q) => $q->whereNull('department_id'),
                    fn ($q) => $q->where('department_id', $departmentId)
                )
                ->delete();

            foreach (($data['layers'] ?? []) as $layer => $email) {
                if (! $email) {
                    continue;
                }

                // Cari user hcis by email + pastikan role Employee (tanpa insert ke hcis).
                $emp  = KpnEmployee::forEmail($email);
                $user = $hcis->mirror($email, $emp?->fullname, $emp?->employee_id);

                if (! $user) {
                    continue; // email tak punya akun user di hcis → tak bisa jadi approver
                }

                CommitteeAssignment::create([
                    'approval_type'    => $data['approval_type'],
                    'business_unit_id' => $data['business_unit_id'],
                    'department_id'    => $departmentId,
                    'layer'            => (int) $layer,
                    'user_id'          => $user->id,
                ]);
            }
        });

        return redirect()
            ->route('admin.committee.index', array_filter([
                'approval_type'    => $data['approval_type'],
                'business_unit_id' => $data['business_unit_id'],
                'department'       => $data['department'] ?? null,
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
