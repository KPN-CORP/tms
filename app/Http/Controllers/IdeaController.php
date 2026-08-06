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
use App\Models\Location;
use App\Services\Idea\IdeaWorkflowService;
use Illuminate\Http\Request;
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
     * My Ideas — ide milik user yang login (selain draft).
     * Search = teks bebas (ID/nama/problem); BU, Department, Status = dropdown.
     */
    public function index(Request $request)
    {
        // Basis query (sudah tersaring kepemilikan + scope) — dipakai ulang untuk
        // menurunkan opsi dropdown agar hanya menampilkan nilai yang relevan.
        $base = Idea::where('user_id', $request->user()->id)
            ->where('status', '!=', 'draft')
            ->visibleTo($request->user());

        $query = (clone $base)
            ->when($request->filled('business_unit_id'), fn ($q) => $q->where('business_unit_id', $request->integer('business_unit_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->with(['businessUnit', 'department']);

        $this->applyListSearchSort($query, $request, self::IDEA_LIST_CONFIG);

        // "Show N entries" — jumlah baris per halaman (whitelist).
        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        return view('ideas.index', [
            'ideas'         => $query->paginate($perPage)->withQueryString(),
            'perPage'       => $perPage,
            'businessUnits' => BusinessUnit::whereIn('id', (clone $base)->select('business_unit_id'))->orderBy('name')->get(),
            'departments'   => Department::whereIn('id', (clone $base)->whereNotNull('department_id')->select('department_id'))->orderBy('name')->get(),
        ] + $this->listSortState($request, self::IDEA_LIST_CONFIG));
    }

    /**
     * Draft Ideas — ide milik user yang masih draft.
     */
    public function drafts(Request $request)
    {
        $config = [
            'searchable'   => ['idea_id', 'idea_name', 'problem'],
            'sortable'     => ['idea_name' => 'idea_name', 'modified_at' => 'modified_at'],
            'default_sort' => 'modified_at',
            'default_dir'  => 'desc',
        ];

        $base = Idea::where('user_id', $request->user()->id)->where('status', 'draft');

        $query = (clone $base)
            ->when($request->filled('business_unit_id'), fn ($q) => $q->where('business_unit_id', $request->integer('business_unit_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->with(['businessUnit', 'department']);

        $this->applyListSearchSort($query, $request, $config);

        $perPage = $this->listPerPage($request);

        return view('ideas.drafts', [
            'ideas'         => $query->paginate($perPage)->withQueryString(),
            'perPage'       => $perPage,
            'businessUnits' => BusinessUnit::whereIn('id', (clone $base)->select('business_unit_id'))->orderBy('name')->get(),
            'departments'   => Department::whereIn('id', (clone $base)->whereNotNull('department_id')->select('department_id'))->orderBy('name')->get(),
        ] + $this->listSortState($request, $config));
    }

    /**
     * Detail ide (read-only). Hanya pemilik ide.
     */
    public function show(Request $request, Idea $idea)
    {
        abort_unless($idea->user_id === $request->user()->id, 403);

        $idea->load(['businessUnit', 'department', 'user.businessUnit', 'user.department', 'attachments']);

        return view('ideas.show', compact('idea'));
    }

    public function create()
    {
        return view('ideas.create', $this->formData());
    }

    public function store(Request $request)
    {
        $isSubmit  = $request->input('action') === 'submit';
        $validated = $this->validateIdea($request, $isSubmit);
        $org       = $this->resolveOrg($validated);
        unset($validated['business_unit'], $validated['department'], $validated['company'], $validated['location']);

        $idea = Idea::create($validated + $org + [
            'user_id' => $request->user()->id,
            'idea_id' => $this->generateIdeaId($org['business_unit_name'] ?? null),
            'status'  => $isSubmit ? 'submitted' : 'draft',
        ]);

        $this->storeAttachments($request, $idea);

        return redirect()
            ->route($isSubmit ? 'ideas.index' : 'ideas.drafts')
            ->with('success', $isSubmit
                ? "Idea \"{$idea->idea_name}\" submitted ({$idea->idea_id})."
                : "Draft \"{$idea->idea_name}\" saved.");
    }

    public function edit(Request $request, Idea $idea)
    {
        $this->authorizeOwnerDraft($request, $idea);

        return view('ideas.edit', $this->formData() + ['idea' => $idea]);
    }

    public function update(Request $request, Idea $idea)
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

        return redirect()
            ->route($isSubmit ? 'ideas.index' : 'ideas.drafts')
            ->with('success', $isSubmit
                ? "Idea \"{$idea->idea_name}\" submitted ({$idea->idea_id})."
                : "Draft \"{$idea->idea_name}\" updated.");
    }

    public function destroy(Request $request, Idea $idea)
    {
        $this->authorizeOwnerDraft($request, $idea);

        $idea->delete();

        return redirect()
            ->route('ideas.drafts')
            ->with('success', 'Draft deleted.');
    }

    /**
     * Review Ideas — antrean ide yang menunggu review oleh committee ini
     * (di layer masing-masing, sesuai routing waterfall).
     */
    public function review(Request $request, IdeaWorkflowService $wf)
    {
        $config = [
            'searchable'   => ['idea_id', 'idea_name', 'problem'],
            'sortable'     => [
                'idea_id'       => 'idea_id',
                'idea_name'     => 'idea_name',
                'current_layer' => 'current_layer',
                'status'        => 'status',
                'modified_at'   => 'modified_at',
            ],
            'default_sort' => 'modified_at',
            'default_dir'  => 'desc',
        ];

        $base = $wf->reviewQueueFor($request->user());

        $query = (clone $base)
            ->when($request->filled('business_unit_id'), fn ($q) => $q->where('business_unit_id', $request->integer('business_unit_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->with(['businessUnit', 'department', 'user']);

        $this->applyListSearchSort($query, $request, $config);

        $perPage = $this->listPerPage($request);

        return view('ideas.review', [
            'ideas'         => $query->paginate($perPage)->withQueryString(),
            'perPage'       => $perPage,
            'businessUnits' => BusinessUnit::whereIn('id', (clone $base)->select('business_unit_id'))->orderBy('name')->get(),
            'departments'   => Department::whereIn('id', (clone $base)->whereNotNull('department_id')->select('department_id'))->orderBy('name')->get(),
        ] + $this->listSortState($request, $config));
    }

    /**
     * Detail review + aksi approve/reject.
     */
    public function reviewShow(Request $request, Idea $idea, IdeaWorkflowService $wf)
    {
        $user = $request->user();

        // Hanya committee dari BU ide ini (di layer mana pun) yang boleh melihat.
        $isCommitteeOfBu = CommitteeAssignment::where('business_unit_id', $idea->business_unit_id)
            ->where('user_id', $user->id)
            ->exists();
        abort_unless($isCommitteeOfBu, 403);

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

        return redirect()->route('ideas.review')
            ->with('success', "Idea \"{$idea->idea_name}\" approved for your layer.");
    }

    public function reject(Request $request, Idea $idea, IdeaWorkflowService $wf)
    {
        abort_unless($wf->isCurrentReviewer($idea, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $wf->reject($idea, $request->user(), $note);

        return redirect()->route('ideas.review')
            ->with('success', "Idea \"{$idea->idea_name}\" rejected.");
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

    public function destroyAttachment(Request $request, Idea $idea, IdeaAttachment $attachment)
    {
        $this->authorizeOwnerDraft($request, $idea); // hanya pemilik & saat draft
        abort_unless($attachment->idea_id === $idea->id, 404);

        $this->deleteAttachmentFile($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Lampiran dihapus.');
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
        // Saat submit semua mandatory; saat draft cukup idea_name.
        $required = $isSubmit ? 'required' : 'nullable';

        return $request->validate([
            'idea_name'        => ['required', 'string', 'max:255'],
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
     * Format: I-{BU}-YYYYMMDD-00000. BU = singkatan Business Unit TARGET ide.
     */
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
