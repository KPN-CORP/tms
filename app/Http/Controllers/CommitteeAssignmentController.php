<?php

namespace App\Http\Controllers;

use App\Models\CommitteeAssignment;
use App\Services\Committee\ApprovalCoverageReport;
use App\Services\Committee\CommitteeLayerImport;
use App\Support\SimpleXlsx;
use App\Models\KpnBusinessUnit;
use App\Models\KpnEmployee;
use App\Models\User;
use App\Services\HcisAuthService;
use App\Services\OrgResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CommitteeAssignmentController extends Controller
{
    private const MAX_LAYERS = 10;

    /** Halaman daftar committee (list saja). Form Add/Edit ada di halaman terpisah. */
    public function index(Request $request, ApprovalCoverageReport $coverage)
    {
        $type = array_key_exists($request->query('approval_type'), CommitteeAssignment::TYPES)
            ? $request->query('approval_type')
            : null;

        $report = $coverage->build();

        $assignUrl = fn (array $row) => route('admin.committee.form', array_filter([
            'approval_type' => $row['type'] ?? null,
            'business_unit_id' => $row['bu_id'] ?? null,
        ]));

        return Inertia::render('Admin/Committee/Index', [
            'types' => collect(CommitteeAssignment::TYPES)
                ->map(fn (string $label, string $key) => [
                    'value' => $key,
                    'label' => $label,
                    'usesBudgetRange' => CommitteeAssignment::usesBudgetRange($key),
                ])->values(),

            // Tab yang terbuka saat halaman dibuka dari tautan "Assign".
            'activeType' => $type ?: array_key_first(CommitteeAssignment::TYPES),

            'sets' => $this->configuredSets()->map(function (array $set) {
                // budget_min bisa 0 (falsy), jadi min/max tidak boleh ikut array_filter.
                $editParams = array_filter([
                    'approval_type' => $set['approval_type'],
                    'business_unit_id' => $set['business_unit_id'],
                    'department' => $set['dept_name_raw'],
                ]);

                if ($set['budget_min'] !== null) {
                    $editParams['budget_min'] = $set['budget_min'];
                }

                if ($set['budget_max'] !== null) {
                    $editParams['budget_max'] = $set['budget_max'];
                }

                return [
                    'approval_type' => $set['approval_type'],
                    'type_label' => $set['type_label'],
                    'business_unit_id' => $set['business_unit_id'],
                    'bu_name' => $set['bu_name'],
                    'department_id' => $set['department_id'],
                    'dept_name' => $set['dept_name'],
                    'budget_min' => $set['budget_min'],
                    'budget_max' => $set['budget_max'],
                    'budget_label' => $set['budget_label'],
                    'layers' => $set['layers']->values(),
                    'edit_url' => route('admin.committee.form', $editParams),
                ];
            })->values(),

            // Peringatan layer approval yang belum di-assign — dihitung dari data
            // setiap halaman dibuka, jadi hilang sendiri begitu assignment-nya ada.
            'coverage' => [
                'total' => $report['total'],
                'blocked' => $report['blocked']->map(fn (array $r) => $r + [
                    'oldest_human' => $r['oldest'] ? \Carbon\Carbon::parse($r['oldest'])->diffForHumans() : null,
                    'assign_url' => $assignUrl($r),
                ])->values(),
                'gaps' => $report['gaps']->map(fn (array $r) => $r + ['assign_url' => $assignUrl($r)])->values(),
                'risks' => $report['risks']->map(fn (array $r) => $r + ['assign_url' => $assignUrl($r)])->values(),
            ],

            'urls' => [
                'create' => route('admin.committee.form'),
                'destroy' => route('admin.committee.destroy'),
            ],
        ]);
    }

    /** Halaman form Add/Edit committee (approval type + BU + Unit + budget + layer). */
    public function form(Request $request, OrgResolver $org)
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

        // Budget range (min–max) — khusus approval type budget-scoped (project_proposal).
        $usesRange = $type && CommitteeAssignment::usesBudgetRange($type);
        $selectedMin = ($p = preg_replace('/[^0-9]/', '', (string) $request->query('budget_min'))) !== '' ? (int) $p : null;
        $selectedMax = ($p = preg_replace('/[^0-9]/', '', (string) $request->query('budget_max'))) !== '' ? (int) $p : null;

        // Set siap di-edit hanya bila min & max lengkap (dan min ≤ max).
        $rangeReady = ! $usesRange || ($selectedMin !== null && $selectedMax !== null && $selectedMin <= $selectedMax);

        // [layer => user_id] existing → dipetakan ke [layer => {email,label}] untuk pre-select.
        $assignments = ($type && $selectedBuId && $rangeReady)
            ? CommitteeAssignment::where('approval_type', $type)
                ->where('business_unit_id', $selectedBuId)
                ->when(
                    $selectedDeptId === null,
                    fn ($q) => $q->whereNull('department_id'),
                    fn ($q) => $q->where('department_id', $selectedDeptId)
                )
                ->when($usesRange, fn ($q) => $q->where('budget_min', $selectedMin)->where('budget_max', $selectedMax))
                ->pluck('user_id', 'layer')
            : collect();

        $userEmails = User::whereIn('id', $assignments->values()->filter())->pluck('email', 'id');
        $empByEmail = KpnEmployee::query()->whereIn('email', $userEmails->values()->filter()->all())->get()->keyBy('email');

        $assignedByLayer = $assignments->map(function ($uid) use ($userEmails, $empByEmail) {
            $email = $userEmails[$uid] ?? null;
            if (! $email) {
                return null;
            }

            return ['email' => $email, 'label' => optional($empByEmail[$email] ?? null)->label() ?: $email];
        });

        // Layer di atas 3 disembunyikan sampai "Add Layer" ditekan, tapi layer
        // yang sudah terisi harus tetap terlihat.
        $maxAssigned = collect($assignedByLayer)->filter()->keys()->max() ?? 0;

        return Inertia::render('Admin/Committee/Form', [
            'types' => collect(CommitteeAssignment::TYPES)
                ->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])
                ->values(),

            'businessUnits' => $businessUnits
                ->map(fn ($bu) => ['value' => $bu->id, 'label' => $bu->name])
                ->values(),

            'unitNames' => $unitNames->map(fn ($name) => ['value' => $name, 'label' => $name])->values(),

            'selection' => [
                'approval_type' => $type,
                'business_unit_id' => $selectedBuId,
                'department' => $selectedUnitName,
                'budget_min' => $selectedMin,
                'budget_max' => $selectedMax,
            ],

            'context' => [
                'type_label' => $type ? CommitteeAssignment::TYPES[$type] : null,
                'bu_name' => $selectedBu?->name,
                'unit_label' => $selectedUnitName ?: __('messages.labels.all_units_bu_wide'),
                'budget_label' => $usesRange
                    ? CommitteeAssignment::budgetRangeLabel($selectedMin, $selectedMax)
                    : null,
            ],

            // Layer 1 selalu Project Sponsor pada tipe ber-sponsor; committee
            // dikonfigurasi mulai Layer 2.
            'sponsorLayer' => $type ? CommitteeAssignment::usesSponsorLayer($type) : false,

            'assignedByLayer' => $assignedByLayer->filter()->all(),
            'initialVisible' => max(3, (int) $maxAssigned),

            // Kombinasi yang sudah punya baris committee = sedang diubah,
            // bukan dibuat. Judul halaman ikut ini supaya tidak lagi
            // menampilkan "Add / Edit" sekaligus.
            'isEdit' => $assignments->isNotEmpty(),

            'usesRange' => $usesRange,
            'rangeReady' => $rangeReady,
            'maxLayers' => self::MAX_LAYERS,

            'urls' => [
                'form' => route('admin.committee.form'),
                'store' => route('admin.committee.store'),
                'import' => route('admin.committee.import'),
                'template' => route('admin.committee.template'),
                'index' => route('admin.committee.index'),
                'employeeSearch' => route('org.employees'),
            ],
        ]);
    }

    /**
     * Baca file template (employee_id, approver_id, layer) dan kembalikan
     * pasangan layer→approver sebagai JSON untuk MENGISI form — belum disimpan.
     * Penyimpanan tetap lewat tombol Save supaya isi file bisa diperiksa dulu.
     */
    public function import(Request $request, CommitteeLayerImport $importer)
    {
        $data = $request->validate([
            'approval_type' => ['required', Rule::in(array_keys(CommitteeAssignment::TYPES))],
            'file'          => ['required', 'file', 'max:2048', 'mimes:xlsx,csv,txt'],
        ], [
            'file.mimes' => __('messages.validation.import_mimes'),
            'file.max'   => __('messages.validation.import_max'),
        ]);

        try {
            $hasil = $importer->parse(
                $data['file']->getRealPath(),
                strtolower($data['file']->getClientOriginalExtension()),
                $data['approval_type'],
                self::MAX_LAYERS,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($hasil);
    }

    /** Template kosong berisi contoh satu chain, agar format kolomnya jelas. */
    public function template()
    {
        $isi = SimpleXlsx::write([
            ['employee_id', 'approver_id', 'layer'],
            ['', '', '1'],
            ['', '', '2'],
        ]);

        return response($isi, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="committee-template.xlsx"',
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
            ->orderBy('budget_min')
            ->orderBy('budget_max')
            ->orderBy('layer')
            ->get()
            ->groupBy(fn ($r) => $r->approval_type . '|' . $r->business_unit_id . '|' . ($r->department_id ?? '') . '|' . ($r->budget_min ?? '') . '|' . ($r->budget_max ?? ''))
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'approval_type'    => $first->approval_type,
                    'type_label'       => CommitteeAssignment::TYPES[$first->approval_type] ?? $first->approval_type,
                    'business_unit_id' => $first->business_unit_id,
                    'bu_name'          => optional($first->businessUnit)->name,
                    'department_id'    => $first->department_id,
                    'dept_name'        => $first->department_id
                        ? optional($first->department)->name
                        : __('messages.labels.all_units'),
                    'dept_name_raw'    => optional($first->department)->name,
                    'budget_min'       => $first->budget_min !== null ? (int) $first->budget_min : null,
                    'budget_max'       => $first->budget_max !== null ? (int) $first->budget_max : null,
                    'budget_label'     => CommitteeAssignment::budgetRangeLabel($first->budget_min, $first->budget_max),
                    'layers'           => $this->layersWithReserved($rows),
                ];
            })
            ->values();
    }

    /**
     * Daftar layer sebuah set committee, urut dari L1.
     *
     * Khusus Project Proposal, Layer 1 dicadangkan untuk Project Sponsor dan tidak
     * pernah tersimpan di committee_assignments — chain committee mulai dari L2.
     * Tanpa penanda ini penomoran di tabel terlihat "melompat" dan seolah L1 hilang,
     * jadi L1 tetap ditampilkan sebagai baris semu (reserved, bukan hasil konfigurasi).
     */
    private function layersWithReserved($rows)
    {
        $layers = $rows->sortBy('layer')
            ->map(fn ($r) => [
                'layer'    => (int) $r->layer,
                'name'     => trim(optional($r->user)->name . (optional($r->user)->employee_id ? ' - ' . optional($r->user)->employee_id : '')),
                'reserved' => false,
            ])
            ->values();

        $type = $rows->first()->approval_type;

        if (CommitteeAssignment::usesSponsorLayer($type) && ! $layers->contains(fn ($l) => $l['layer'] === 1)) {
            $layers = $layers->prepend([
                'layer'    => 1,
                // Orangnya berbeda per project (diambil dari field Sponsor project),
                // jadi yang ditampilkan peranannya, bukan satu nama tertentu.
                'name'     => 'Project Sponsor',
                'reserved' => true,
            ])->values();
        }

        return $layers;
    }

    public function store(Request $request, OrgResolver $org, HcisAuthService $hcis)
    {
        $usesRange = CommitteeAssignment::usesBudgetRange((string) $request->input('approval_type'));

        // Range: terima "20.000.000" / "20000000" → digit saja sebelum validasi.
        $request->merge([
            'budget_min' => preg_replace('/[^0-9]/', '', (string) $request->input('budget_min')),
            'budget_max' => preg_replace('/[^0-9]/', '', (string) $request->input('budget_max')),
        ]);

        $data = $request->validate([
            'approval_type'    => ['required', Rule::in(array_keys(CommitteeAssignment::TYPES))],
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'department'       => ['nullable', 'string', 'max:255'],
            // Range budget (min–max) wajib untuk approval type budget-scoped (project_proposal).
            'budget_min'       => [Rule::requiredIf(fn () => $usesRange), 'nullable', 'integer', 'min:0'],
            'budget_max'       => [Rule::requiredIf(fn () => $usesRange), 'nullable', 'integer', 'gte:budget_min'],
            'layers'           => ['array'],
            'layers.*'         => ['nullable', 'email'], // email employee (hcis)
        ], [
            'budget_max.gte' => 'Budget Max must be greater than or equal to Budget Min.',
        ]);

        // Unit/Department (NAMA) → find-or-create FK lokal — untuk SEMUA approval type.
        $departmentId = null;
        if (! empty($data['department'])) {
            $departmentId = $org->department($data['department'], (int) $data['business_unit_id'])->id;
        }

        // Range hanya untuk approval type budget-scoped; selain itu NULL.
        $min = $usesRange ? (int) $data['budget_min'] : null;
        $max = $usesRange ? (int) $data['budget_max'] : null;

        DB::transaction(function () use ($data, $departmentId, $min, $max, $usesRange, $hcis) {
            CommitteeAssignment::where('approval_type', $data['approval_type'])
                ->where('business_unit_id', $data['business_unit_id'])
                ->when(
                    $departmentId === null,
                    fn ($q) => $q->whereNull('department_id'),
                    fn ($q) => $q->where('department_id', $departmentId)
                )
                ->when(
                    $usesRange,
                    fn ($q) => $q->where('budget_min', $min)->where('budget_max', $max)
                )
                ->delete();

            foreach (($data['layers'] ?? []) as $layer => $email) {
                // Layer 1 milik Project Sponsor untuk type tsb — abaikan bila
                // sempat terkirim (mis. form lama / request manual).
                if ((int) $layer === CommitteeAssignment::SPONSOR_LAYER
                    && CommitteeAssignment::usesSponsorLayer($data['approval_type'])) {
                    continue;
                }

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
                    'budget_min'       => $min,
                    'budget_max'       => $max,
                    'layer'            => (int) $layer,
                    'user_id'          => $user->id,
                ]);
            }
        });

        // Kembali ke halaman daftar pada tab approval type yang baru disimpan.
        return redirect()
            ->route('admin.committee.index', ['approval_type' => $data['approval_type']])
            ->with('success', __('flash.committee.saved'));
    }

    /** Hapus seluruh layer untuk satu set (type + BU + department). */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'approval_type'    => ['required', Rule::in(array_keys(CommitteeAssignment::TYPES))],
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'department_id'    => ['nullable', 'integer', 'exists:departments,id'],
            'budget_min'       => ['nullable', 'integer', 'min:0'],
            'budget_max'       => ['nullable', 'integer', 'min:0'],
        ]);

        $usesRange    = CommitteeAssignment::usesBudgetRange($data['approval_type']);
        $departmentId = $data['department_id'] ?? null;
        $min          = $usesRange ? ($data['budget_min'] ?? null) : null;
        $max          = $usesRange ? ($data['budget_max'] ?? null) : null;

        CommitteeAssignment::where('approval_type', $data['approval_type'])
            ->where('business_unit_id', $data['business_unit_id'])
            ->when(
                $departmentId === null,
                fn ($q) => $q->whereNull('department_id'),
                fn ($q) => $q->where('department_id', $departmentId)
            )
            ->when(
                $usesRange,
                fn ($q) => $q->where('budget_min', $min)->where('budget_max', $max)
            )
            ->delete();

        // Tetap di tab approval type yang sama pada halaman daftar.
        return redirect()
            ->route('admin.committee.index', ['approval_type' => $data['approval_type']])
            ->with('success', __('flash.committee.deleted'));
    }
}
