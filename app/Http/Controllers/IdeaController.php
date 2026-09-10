<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileAttachments;
use App\Http\Controllers\Concerns\HasListQuery;
use App\Models\BusinessUnit;
use App\Models\CommitteeAssignment;
use App\Models\Company;
use App\Models\Department;
use App\Models\Idea;
use App\Models\IdeaAttachment;
use App\Models\KpnBusinessUnit;
use App\Services\Dashboard\MetricScopeService;
use App\Models\Location;
use App\Services\Idea\IdeaWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IdeaController extends Controller
{
    use HandlesFileAttachments;
    use HasListQuery;

    /** Konfigurasi search/sort untuk daftar ide. Search = teks bebas saja. */
    private const IDEA_LIST_CONFIG = [
        'searchable'   => ['idea_id', 'idea_name', 'problem'],
        'sortable'     => [
            'idea_id'     => 'idea_id',
            'idea_name'   => 'idea_name',
            'status'      => 'status',
            'modified_at' => 'modified_at',
        ],
        'default_sort' => 'modified_at',
        'default_dir'  => 'desc',
    ];

    /**
     * My Ideas — semua ide milik user yang login (termasuk draft).
     * Search = teks bebas (ID/nama/problem); BU, Department, Status = dropdown.
     */
    public function index(Request $request)
    {
        // Basis query (tersaring kepemilikan + scope) — dipakai ulang untuk
        // menurunkan opsi dropdown agar hanya menampilkan nilai yang relevan.
        $base = Idea::where('user_id', $request->user()->id)
            ->visibleTo($request->user());

        // Dibuka dari kartu Dashboard: batasi ke status milik metrik itu supaya
        // jumlah barisnya sama persis dengan angka pada kartunya.
        $metric = $request->get('metric');
        if (MetricScopeService::exists($metric) && MetricScopeService::kindOf($metric) === 'idea') {
            $base->whereIn('status', MetricScopeService::METRICS[$metric]['statuses'] ?? []);
        } else {
            $metric = null;
        }

        // Filter BU/Unit by NAMA (dropdown dari hcis) + search/sort. Status pakai tab.
        $query = (clone $base)
            ->when($request->filled('bu'), fn ($q) => $q->where('business_unit_name', $request->get('bu')))
            ->when($request->filled('unit'), fn ($q) => $q->where('department_name', $request->get('unit')))
            ->with(['businessUnit', 'department']);

        $this->applyListSearchSort($query, $request, self::IDEA_LIST_CONFIG);

        // Jumlah per status (kartu ringkasan + angka tab) — sadar search & filter, tanpa sort.
        $counts = (clone $query)->reorder()->getQuery()
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        // Tab aktif → filter status (selain 'all').
        $tabs = ['all', 'draft', 'submitted', 'review', 'approved', 'rejected'];
        $tab  = in_array($request->get('tab'), $tabs, true) ? $request->get('tab') : 'all';
        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        // "Show N entries" — jumlah baris per halaman (whitelist).
        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        // Dropdown filter Business Unit dari hcis (master_bisnisunits.nama_bisnis).
        // Unit di-cascade via AJAX (org.unit-names → departments.department_name).
        $buNames = KpnBusinessUnit::names();

        return view('ideas.index', [
            'ideas'   => $query->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
            'buNames' => $buNames,
            'counts'  => $counts,
            'tab'     => $tab,
            // Konteks kartu Dashboard (bila halaman ini dibuka dari sana).
            'metric'      => $metric,
            'metricLabel' => $metric ? MetricScopeService::labelOf($metric) : null,
        ] + $this->listSortState($request, self::IDEA_LIST_CONFIG));
    }

    /**
     * Detail ide (read-only). Hanya pemilik ide.
     */
    public function show(Request $request, Idea $idea)
    {
        // Pemilik ide, atau pemegang izin Report (menu Report memuat seluruh ide
        // lintas organisasi, jadi tombol lihat detail di sana harus bisa dibuka).
        // Halaman ini memang read-only, tidak ada aksi yang bisa dijalankan di sini.
        abort_unless(
            $idea->user_id === $request->user()->id || $request->user()->can('report.view'),
            403
        );

        $idea->load(['businessUnit', 'department', 'user.businessUnit', 'user.department', 'attachments', 'approvals.user']);

        return view('ideas.show', compact('idea'));
    }

    public function create()
    {
        return view('ideas.create', $this->formData());
    }

    public function store(Request $request, IdeaWorkflowService $wf)
    {
        $isSubmit  = $request->input('action') === 'submit';
        $validated = $this->validateIdea($request, $isSubmit);
        $org       = $this->resolveOrg($validated);
        unset($validated['business_unit'], $validated['department'], $validated['company'], $validated['location']);

        $idea = Idea::create($validated + $org + [
            'user_id' => $request->user()->id,
            // ID dibuat sekali di sini (draft maupun submit). BU pada ID = BU PENGAJU
            // (group_company employee-nya dari hcis), bukan BU target ide.
            'idea_id' => $this->generateIdeaId($this->submitterBusinessUnit($request->user())),
            'status'  => $isSubmit ? 'submitted' : 'draft',
            // Di-set eksplisit (bukan mengandalkan DEFAULT database) supaya instance
            // di memori tidak NULL — routing committee di bawah mencocokkan layer.
            'current_layer' => 1,
        ]);

        $this->storeAttachments($request, $idea);

        // Pengaju yang juga committee di layer aktif → layer itu auto-approved,
        // ide langsung diteruskan ke Task Box layer berikutnya.
        if ($isSubmit) {
            $wf->autoApproveSubmitterLayers($idea);
        }

        return redirect()
            ->route('ideas.index')
            ->with('success', $isSubmit
                ? "Idea {$idea->idea_id} submitted."
                : "Draft {$idea->idea_id} saved.");
    }

    public function edit(Request $request, Idea $idea)
    {
        $this->authorizeOwnerDraft($request, $idea);

        return view('ideas.edit', $this->formData() + ['idea' => $idea]);
    }

    public function update(Request $request, Idea $idea, IdeaWorkflowService $wf)
    {
        $this->authorizeOwnerDraft($request, $idea);

        $isSubmit  = $request->input('action') === 'submit';
        $validated = $this->validateIdea($request, $isSubmit);
        $org       = $this->resolveOrg($validated);
        unset($validated['business_unit'], $validated['department'], $validated['company'], $validated['location']);

        $idea->update($validated + $org + [
            'status' => $isSubmit ? 'submitted' : 'draft',
        ]);

        $this->storeAttachments($request, $idea);

        if ($isSubmit) {
            $wf->autoApproveSubmitterLayers($idea);
        }

        return redirect()
            ->route('ideas.index')
            ->with('success', $isSubmit
                ? "Idea {$idea->idea_id} submitted."
                : "Draft {$idea->idea_id} updated.");
    }

    public function destroy(Request $request, Idea $idea)
    {
        $this->authorizeOwnerDraft($request, $idea);

        $ideaId = $idea->idea_id;
        $idea->delete();

        return redirect()
            ->route('ideas.index')
            ->with('success', "Draft {$ideaId} deleted.");
    }

    /**
     * Task Box — gabungan "Review Ideas" + "Approved Ideas" untuk committee & Super Admin.
     * Ditampilkan per-tab status (All / Submitted / Approved / On Review / Reject) beserta
     * jumlahnya. FLOW TIDAK BERUBAH — hanya tampilan & nama menu:
     *  - Submitted/On Review = antrean review user (di layer-nya, via reviewQueueFor).
     *  - Approved            = ide approved di mana user committee layer terakhir (buat shell).
     *  - Reject              = ide rejected di BU tempat user menjadi idea-committee.
     *  - Super Admin melihat semua ide (oversight, read-only kecuali yang memang haknya).
     * Aksi (review/approve/reject/buat project) tetap di-gate service seperti semula.
     */
    public function taskBox(Request $request, IdeaWorkflowService $wf)
    {
        $user    = $request->user();
        $isSuper = $user->hasRole('Super Admin');
        $isAdminView = $user->hasAnyRole(['Admin', 'Super Admin']); // tooltip: admin lihat semua layer

        // --- Universe (koleksi) sesuai flow ---
        if ($isSuper) {
            $universe = Idea::with(['businessUnit', 'department', 'user'])
                ->whereIn('status', ['submitted', 'review', 'approved', 'rejected'])
                ->get();
        } else {
            // Semua ide yang PERNAH mencapai layer committee user — apa pun statusnya.
            // Tetap tampil setelah di-approve/reject; ide yang tak pernah masuk ke
            // akun user (belum mencapai layernya) tidak ditampilkan.
            $universe = $wf->taskBoxQueueFor($user)
                ->with(['businessUnit', 'department', 'user'])->get();
        }

        // --- Filter search (q) + BU + Department (PHP, karena universe adalah koleksi) ---
        if (($q = trim((string) $request->get('q'))) !== '') {
            $needle = mb_strtolower($q);
            $universe = $universe->filter(fn (Idea $i) => str_contains(mb_strtolower((string) $i->idea_id), $needle)
                || str_contains(mb_strtolower((string) $i->idea_name), $needle)
                || str_contains(mb_strtolower((string) $i->problem), $needle));
        }
        // Filter BU/Unit memakai NAMA dari hcis — persis seperti halaman My Ideas,
        // supaya pilihan dropdown dan hasilnya konsisten di kedua halaman.
        if ($request->filled('bu')) {
            $universe = $universe->where('business_unit_name', $request->get('bu'));
        }
        if ($request->filled('unit')) {
            $universe = $universe->where('department_name', $request->get('unit'));
        }
        $universe = $universe->values();

        // --- Jumlah per status (setelah filter, sebelum tab) → angka di tab ---
        $counts = [
            'all'       => $universe->count(),
            'submitted' => $universe->where('status', 'submitted')->count(),
            'approved'  => $universe->where('status', 'approved')->count(),
            'review'    => $universe->where('status', 'review')->count(),
            'rejected'  => $universe->where('status', 'rejected')->count(),
        ];

        // --- Tab aktif → filter status ---
        $tab  = in_array($request->get('tab'), array_keys($counts), true) ? $request->get('tab') : 'all';
        $list = $tab === 'all' ? $universe : $universe->where('status', $tab)->values();

        // --- Sort (whitelist), default modified_at desc ---
        $sortable = ['idea_id', 'idea_name', 'status', 'current_layer', 'modified_at'];
        $sort = in_array($request->get('sort'), $sortable, true) ? $request->get('sort') : 'modified_at';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $list = $list->sortBy(fn (Idea $i) => $i->{$sort}, SORT_REGULAR, $dir === 'desc')->values();

        // --- Opsi dropdown BU/Dept dari universe penuh ---
        // Dropdown Business Unit diambil dari hcis (master_bisnisunits), Unit di-cascade
        // via AJAX — sama seperti My Ideas, bukan lagi dari BU/Department lokal.
        $buNames = KpnBusinessUnit::names();

        // --- Paginate + tandai aksi per baris (hanya halaman aktif → hemat query) ---
        $perPage = $this->listPerPage($request);
        $ideas   = $this->paginateListCollection($list, $request, $perPage);
        $ideas->setCollection($ideas->getCollection()->map(function (Idea $i) use ($wf, $user, $isAdminView) {
            $i->can_review       = $wf->isCurrentReviewer($i, $user);
            $i->can_create_shell = $i->status === 'approved' && $wf->isLastLayerCommittee($i, $user);

            // Tooltip Layer: Admin lihat SEMUA layer; non-admin hanya layer yang
            // sedang aktif (current_layer) — nama pemilik layer aktif saja.
            $chain = $wf->layerParticipants($i);
            $i->layer_chain = $isAdminView
                ? $chain->values()->all()
                : $chain->where('layer', $i->current_layer)->values()->all();

            return $i;
        }));

        return view('ideas.task-box', [
            'ideas'         => $ideas,
            'perPage'       => $perPage,
            'buNames'       => $buNames,
            'counts'        => $counts,
            'tab'           => $tab,
            'sort'          => $sort,
            'dir'           => $dir,
        ]);
    }

    /**
     * Detail review + aksi approve/reject.
     */
    public function reviewShow(Request $request, Idea $idea, IdeaWorkflowService $wf)
    {
        $user = $request->user();

        // Committee dari BU ide ini (di layer mana pun) boleh melihat; Super Admin
        // boleh melihat semua (read-only untuk oversight Task Box).
        $isCommitteeOfBu = CommitteeAssignment::where('business_unit_id', $idea->business_unit_id)
            ->where('user_id', $user->id)
            ->exists();
        abort_unless($isCommitteeOfBu || $user->hasRole('Super Admin'), 403);

        // FR-078: begitu dibuka reviewer layer aktif -> On Review.
        if ($wf->isCurrentReviewer($idea, $user)) {
            $wf->markOnReview($idea);
            $idea->refresh();
        }

        $idea->load(['businessUnit', 'department', 'user.businessUnit', 'user.department', 'approvals.user']);

        return view('ideas.review-show', [
            'idea'      => $idea,
            'canReview' => $wf->isCurrentReviewer($idea, $user),
        ]);
    }

    public function approve(Request $request, Idea $idea, IdeaWorkflowService $wf)
    {
        abort_unless($wf->isCurrentReviewer($idea, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $wf->approve($idea, $request->user(), $note);

        return redirect()->route('ideas.taskbox')
            ->with('success', "Idea {$idea->idea_id} approved for your layer.");
    }

    public function reject(Request $request, Idea $idea, IdeaWorkflowService $wf)
    {
        abort_unless($wf->isCurrentReviewer($idea, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $wf->reject($idea, $request->user(), $note);

        return redirect()->route('ideas.taskbox')
            ->with('success', "Idea {$idea->idea_id} rejected.");
    }

    /* ---- Attachments (T-17) ------------------------------------------ */

    public function downloadAttachment(Request $request, Idea $idea, IdeaAttachment $attachment)
    {
        abort_unless($attachment->idea_id === $idea->id, 404);

        // Pemilik ide, atau committee dari BU ide ini, boleh mengunduh.
        $user      = $request->user();
        $isOwner   = $idea->user_id === $user->id;
        $isCommittee = CommitteeAssignment::where('business_unit_id', $idea->business_unit_id)
            ->where('user_id', $user->id)->exists();
        abort_unless($isOwner || $isCommittee || $user->hasRole('Super Admin'), 403);

        return $this->downloadAttachmentFile($attachment->file_path, $attachment->file_name);
    }

    /** Tampilkan lampiran INLINE (untuk preview gambar/PDF di modal). Auth sama seperti download. */
    public function viewAttachment(Request $request, Idea $idea, IdeaAttachment $attachment)
    {
        abort_unless($attachment->idea_id === $idea->id, 404);

        $user        = $request->user();
        $isOwner     = $idea->user_id === $user->id;
        $isCommittee = CommitteeAssignment::where('business_unit_id', $idea->business_unit_id)
            ->where('user_id', $user->id)->exists();
        abort_unless($isOwner || $isCommittee || $user->hasRole('Super Admin'), 403);

        return $this->viewAttachmentFile($attachment->file_path, $attachment->file_name);
    }

    public function destroyAttachment(Request $request, Idea $idea, IdeaAttachment $attachment)
    {
        $this->authorizeOwnerDraft($request, $idea); // hanya pemilik & saat draft
        abort_unless($attachment->idea_id === $idea->id, 404);

        $this->deleteAttachmentFile($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Attachment deleted.');
    }

    /** Simpan file dari input attachments[] (opsional, multi-file). */
    private function storeAttachments(Request $request, Idea $idea): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $request->validate(['attachments.*' => $this->attachmentRules()]);

        foreach ($request->file('attachments') as $file) {
            $idea->attachments()->create(
                $this->storeAttachmentFile($file, "idea-attachments/{$idea->id}", $request->user()->id)
            );
        }
    }

    

    /* ----------------------------------------------------------------- */

    private function formData(): array
    {
        // Business Unit dari hcis (master_bisnisunits.nama_bisnis); Department via AJAX.
        // employeeInfo: data BU/Department user login dari hcis (Employee Information).
        return [
            'businessUnits' => KpnBusinessUnit::names(),
            'employeeInfo'  => \App\Models\KpnEmployee::forEmail(auth()->user()->email),
        ];
    }

    /**
     * Terjemahkan input nama (business_unit/department dari hcis) ke payload kolom ide:
     * simpan NAMA + jembatani ke FK lokal (find-or-create) agar RBAC scope & relasi lama jalan.
     */
    private function resolveOrg(array $validated): array
    {
        $buName   = $validated['business_unit'] ?? null;
        $deptName = $validated['department'] ?? null;

        $payload = [
            'business_unit_name' => $buName,
            'department_name'    => $deptName,
            'company_name'       => $validated['company'] ?? null,
            'location_name'      => $validated['location'] ?? null,
            'business_unit_id'   => null,
            'department_id'      => null,
            'company_id'         => null,
            'location_id'        => null,
        ];

        if ($buName) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $buName), 0, 10)) ?: 'GEN';
            $bu = BusinessUnit::firstOrCreate(['name' => $buName], ['code' => $code]);
            $payload['business_unit_id'] = $bu->id;

            if ($deptName) {
                $dept = Department::firstOrCreate(['name' => $deptName, 'business_unit_id' => $bu->id]);
                $payload['department_id'] = $dept->id;
            }
        }

        return $payload;
    }

    

    private function validateIdea(Request $request, bool $isSubmit): array
    {
        // Saat submit semua mandatory; saat DRAFT tidak ada yang wajib sama sekali —
        // user boleh menekan "Save as Draft" pada form yang masih kosong.
        $required = $isSubmit ? 'required' : 'nullable';

        return $request->validate([
            'idea_name'        => [$required, 'string', 'max:255'],
            'problem'          => [$required, 'string', 'max:5000'],
            'description'      => [$required, 'string', 'max:10000'],
            'expected_outcome' => [$required, 'string', 'max:5000'],
            // BU & Department kini NAMA (string) dari hcis; Company & Location opsional.
            'business_unit'    => [$required, 'string', 'max:255'],
            'department'       => [$required, 'string', 'max:255'],
            'company'          => ['nullable', 'string', 'max:255'],
            'location'         => ['nullable', 'string', 'max:255'],
        ]);
    }


    private function authorizeOwnerDraft(Request $request, Idea $idea): void
    {
        abort_unless($idea->user_id === $request->user()->id, 403);
        abort_unless($idea->status === 'draft', 403, 'Only draft ideas can be edited.');
    }

    /**
     * Business Unit PENGAJU ide = group_company employee-nya di hcis, dicocokkan
     * lewat employee_id (users.employee_id = employees.employee_id). Dipakai untuk
     * kode BU pada Idea ID. Null bila user tak punya employee terkait.
     */
    private function submitterBusinessUnit(\App\Models\User $user): ?string
    {
        return optional($user->employee)->group_company;
    }

    
    private function generateIdeaId(?string $businessUnitName): string
    {
        $buCode = $this->buCodeFromName($businessUnitName);
        $date   = now()->format('Ymd');

        $lastIdea = Idea::where('idea_id', 'like', "I-{$buCode}-{$date}-%")
            ->orderByDesc('idea_id')
            ->first();

        if ($lastIdea) {
            $lastSeq = (int) substr($lastIdea->idea_id, -5);
            $seq = $lastSeq + 1;
        } else {
            $seq = 1;
        }

        return sprintf('I-%s-%s-%05d', $buCode, $date, $seq);
    }

    /** Override kode BU untuk nama tertentu (diprioritaskan di atas singkatan otomatis). */
    private const BU_CODE_MAP = [
        'KPN Corporation' => 'CORP',
        'Cement' => 'CEME',
        'Downstream' => 'DOWN',
        'KPN Sugar' => 'SUGA',
        'Plantations' => 'PLANT',
        'Property' => 'PROP'

    ];

    /**
     * Singkatan kode BU dari namanya:
     *  - override eksplisit bila terdaftar di BU_CODE_MAP (mis. "KPN Corporation" → CORP),
     *  - inisial tiap kata bila ≥ 3 huruf (mis. "Semen Merah Putih" → SMP),
     *  - selain itu 3 huruf pertama nama (mis. "Cement" → CEM).
     */
    private function buCodeFromName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'GEN';
        }

        // Cocokkan override (abaikan beda huruf besar/kecil).
        foreach (self::BU_CODE_MAP as $key => $code) {
            if (strcasecmp($key, $name) === 0) {
                return $code;
            }
        }

        $words = preg_split('/\s+/', preg_replace('/[^A-Za-z\s]/', '', $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = strtoupper(implode('', array_map(fn ($w) => $w[0], $words)));

        if (strlen($initials) >= 3) {
            return substr($initials, 0, 6);
        }

        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $name));

        return substr($letters, 0, 3) ?: 'GEN';
    }
    
}
