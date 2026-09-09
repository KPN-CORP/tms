<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileAttachments;
use App\Http\Controllers\Concerns\HasListQuery;
use App\Http\Controllers\Concerns\PreventsConcurrentEdits;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Idea;
use App\Models\ImplementationIndicator;
use App\Models\ImplementationPlan;
use App\Models\ImplementationPlanAttachment;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectAttachment;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetAttachment;
use App\Models\ProjectCategory;
use App\Models\ProjectMember;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\Idea\IdeaWorkflowService;
use App\Services\Project\ProjectApprovalWorkflowService;
use App\Services\Project\ProjectChangeStagingService;
use App\Services\Project\ProjectUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\KpnBusinessUnit;
use App\Models\KpnEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    use HandlesFileAttachments;
    use HasListQuery;
    use PreventsConcurrentEdits;

    /**
     * Form Create Project Shell dari sebuah ide approved.
     */
    public function create(Request $request)
    {
        $idea = Idea::where('idea_id', $request->query('idea_id'))->firstOrFail();
        $this->authorizeShellCreator($request->user(), $idea);

        // --- (DI-COMMENT) Load semua employee — sekarang dropdown pakai AJAX search.
        // $employees = KpnEmployee::select('employee_id', 'fullname', 'designation')->orderBy('fullname')->get();

        // Draft shell yang sudah ada untuk ide ini → form melanjutkan draft tsb
        // (satu draft per ide, lihat store()).
        $draft = Project::onlyShellDrafts()->where('idea_id', $idea->idea_id)->first();

        // Dropdown Leader/Sponsor memakai employee_id, sedangkan draft menyimpan
        // users.id — dipetakan balik lewat relasi leader/sponsor.
        $draftLeader  = $draft ? optional($draft->leader)->employee_id : null;
        $draftSponsor = $draft ? optional($draft->sponsor)->employee_id : null;

        // Pre-fill Leader/Sponsor: old input (mis. kembali karena error validasi)
        // atau isi draft.
        $preselect = KpnEmployee::query()
            ->whereIn('employee_id', array_filter([
                old('project_leader_id', $draftLeader),
                old('project_sponsor_id', $draftSponsor),
            ]))
            ->get()
            ->keyBy('employee_id');

        return view('projects.create', [
            'idea'         => $idea,
            'draft'        => $draft,
            'draftLeader'  => $draftLeader,
            'draftSponsor' => $draftSponsor,
            'categories'   => ProjectCategory::where('is_active', true)->orderBy('name')->get(),
            'preselect'    => $preselect,
            'searchUrl'    => route('org.employees'),
        ]);
    }

    public function store(Request $request)
    {
        // Tombol "Save as Draft" mengirim action=draft; "Create Project" tidak.
        $isDraft = $request->input('action') === 'draft';

        $rules = [
            'idea_id'             => ['required', 'exists:ideas,idea_id'],
            'project_name'        => ['required', 'string', 'max:255'],
            'project_category_id' => ['required', 'integer', 'exists:project_categories,id'],
            'project_scope'       => ['required', 'string', 'max:255'],
            'expected_outcome'    => ['required', 'string', 'max:500'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'project_sponsor_id'  => ['required', 'string', 'exists:kpncorp.employees,employee_id'],
            'project_leader_id'   => ['required', 'string', 'exists:kpncorp.employees,employee_id'],
        ];

        if ($isDraft) {
            // Draft boleh belum lengkap: yang wajib jadi opsional, tapi batas
            // panjang & referensi tetap divalidasi. Kewajiban isi ditegakkan
            // saat "Create Project".
            foreach ($rules as $field => $rule) {
                if ($field === 'idea_id') {
                    continue;
                }
                $rules[$field] = array_map(fn ($r) => $r === 'required' ? 'nullable' : $r, $rule);
            }
        }

        $data = $request->validate($rules);

        $idea = Idea::where('idea_id', $data['idea_id'])->firstOrFail();
        $this->authorizeShellCreator($request->user(), $idea);

        $category = ! empty($data['project_category_id'])
            ? ProjectCategory::findOrFail($data['project_category_id'])
            : null;

        $leaderEmployee = ! empty($data['project_leader_id'])
            ? KpnEmployee::where('employee_id', $data['project_leader_id'])->firstOrFail()
            : null;

        $sponsorEmployee = ! empty($data['project_sponsor_id'])
            ? KpnEmployee::where('employee_id', $data['project_sponsor_id'])->firstOrFail()
            : null;

        // T-86: eligibility Leader/Sponsor terhadap grade kategori. Pada draft
        // yang belum lengkap pemeriksaan dilewati — dicek lagi saat Create Project.
        $errors = [];
        if ($category && $leaderEmployee && ! $category->eligibleAsLeader($leaderEmployee->grade())) {
            $errors['project_leader_id'] = "Job level Leader tidak memenuhi syarat kategori {$category->code} ({$category->gradeRangeText($category->leader_grade_min, $category->leader_grade_max)}).";
        }
        if ($category && $sponsorEmployee && ! $category->eligibleAsSponsor($sponsorEmployee->grade())) {
            $errors['project_sponsor_id'] = "Job level Sponsor tidak memenuhi syarat kategori {$category->code} ({$category->gradeRangeText($category->sponsor_grade_min, $category->sponsor_grade_max)}).";
        }
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        $leaderUser  = $leaderEmployee ? $this->getOrCreateUser($leaderEmployee) : null;
        $sponsorUser = $sponsorEmployee ? $this->getOrCreateUser($sponsorEmployee) : null;

        if (($leaderEmployee && ! $leaderUser) || ($sponsorEmployee && ! $sponsorUser)) {
            return back()->withErrors(array_filter([
                'project_leader_id'  => ($leaderEmployee && ! $leaderUser) ? 'The leader does not have a user account in hcis.' : null,
                'project_sponsor_id' => ($sponsorEmployee && ! $sponsorUser) ? 'The sponsor does not have a user account in hcis.' : null,
            ]))->withInput();
        }

        $data['project_leader_id']  = $leaderUser?->id;
        $data['project_sponsor_id'] = $sponsorUser?->id;

        // Satu draft per ide: Save as Draft / Create Project menimpa draft yang ada
        // (form Create Project Shell selalu memuat draft tsb bila sudah ada).
        $draft = Project::onlyShellDrafts()->where('idea_id', $idea->idea_id)->first();

        $attributes = $data + [
            'project_category' => $category?->code,
            'is_shell_draft'   => $isDraft,
        ];

        if ($isDraft) {
            $draft ? $draft->update($attributes) : Project::create($attributes);

            return redirect()
                ->route('projects.shell')
                ->with('success', 'Project shell saved as draft.');
        }

        // project_id baru dibuat saat shell benar-benar dibuat (draft belum punya).
        $attributes['project_id'] = $draft?->project_id
            ?: $this->generateProjectId($idea, $category->code);

        if ($draft) {
            $draft->update($attributes);
            $project = $draft;
        } else {
            $project = Project::create($attributes);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Project shell created ({$project->project_id}).");
    }

    /**
     * Hapus draft Project Shell. Hanya draft yang boleh dihapus — shell yang
     * sudah dibuat dibatalkan lewat Cancel Project, bukan delete.
     */
    public function destroyShellDraft(Request $request, string $shell)
    {
        $user  = $request->user();
        $draft = Project::onlyShellDrafts()->findOrFail($shell);

        if (! $user->hasRole('Super Admin')) {
            $idea = Idea::where('idea_id', $draft->idea_id)->firstOrFail();
            $this->authorizeShellCreator($user, $idea);
        }

        $draft->delete();

        return redirect()
            ->route('projects.shell')
            ->with('success', 'Draft project shell deleted.');
    }


    private function getOrCreateUser(KpnEmployee $employee): ?User
    {
        // User = tabel users hcis → cari by email (TANPA insert ke hcis) + pastikan role Employee.
        return app(\App\Services\HcisAuthService::class)
            ->mirror($employee->email, $employee->fullname, $employee->employee_id);
    }


    /**
     * Manage Project — daftar project yang berkaitan dengan user (row-level).
     */
    /** Project Proposal — semua project terkait user (lifecycle proposal). */
    public function index(Request $request)
    {
        return $this->projectList($request, [
            'title'         => 'My Project',
            'subtitle'      => 'Projects related to you (as submitter, member, leader, sponsor, or committee).',
            'route'         => 'projects.index',
            'phaseStatuses' => null, // tidak dibatasi daftar izin...
            // ...tapi status milik fase lain dibuang: Ongoing & Delayed ada di menu
            // Project Implementation, Completion Review ada di Project Completion.
            'excludeStatuses' => ['ongoing', 'delayed', 'completion_review'],
            'statusMap'     => [
                'draft'     => 'draft',
                'submitted' => 'submitted',
                'approved'  => 'approved',
                'review'    => 'committee_review', // "On Review"
                'revision'  => 'revision_required', // tab "Revision Required"
                'rejected'  => 'rejected',
            ],
            'tabDefs' => [
                'all'       => 'All',
                'draft'     => 'Draft',
                'submitted' => 'Submitted',
                'approved'  => 'Approved',
                'review'    => 'On Review',
                'revision'  => 'Revision Required',
                'rejected'  => 'Rejected',
            ],
        ]);
    }

    /** Project Implementation — fase eksekusi (approved/ongoing/delayed). */
    public function implementationIndex(Request $request)
    {
        return $this->projectList($request, [
            'title'         => 'Project Implementation',
            'subtitle'      => 'Projects that are approved and in progress (implementation).',
            'route'         => 'projects.implementation',
            'phase'         => 'implementation', // link Detail membuka mode Implementation (Actual aktif)
            'phaseStatuses' => Project::EXECUTION_STATUSES, // approved, ongoing, delayed
            'statusMap'     => [
                'approved' => 'approved',
                'ongoing'  => 'ongoing',
                'delayed'  => 'delayed',
            ],
            'tabDefs' => [
                'all'      => 'All',
                'approved' => 'Approved',
                'ongoing'  => 'Ongoing',
                'delayed'  => 'Delayed',
            ],
        ]);
    }

    /** Project Completion — fase penyelesaian (completion review / completed). */
    public function completionIndex(Request $request)
    {
        return $this->projectList($request, [
            'title'         => 'Project Completion',
            'subtitle'      => 'Projects under completion review or already completed.',
            'route'         => 'projects.completion',
            'phase'         => 'completion', // link Detail menandai konteks Completion (highlight sidebar)
            'phaseStatuses' => ['completion_review', 'completed'],
            'statusMap'     => [
                'review'    => 'completion_review',
                'completed' => 'completed',
            ],
            'tabDefs' => [
                'all'       => 'All',
                'review'    => 'Completion Review',
                'completed' => 'Completed',
            ],
        ]);
    }

    /**
     * Builder daftar project bersama (Proposal/Implementation/Completion).
     * Tampilan & filter BU/Unit identik; hanya fase status + tab yang berbeda.
     */
    private function projectList(Request $request, array $opts)
    {
        $config = [
            'searchable'   => ['project_id', 'project_name'],
            'sortable'     => [
                'project_id'       => 'project_id',
                'project_name'     => 'project_name',
                'project_category' => 'project_category',
                'status'           => 'status',
                'created_at'       => 'created_at',
            ],
            'default_sort' => 'created_at',
            'default_dir'  => 'desc',
        ];

        // Base query bisa di-override (mis. Project Shell yang basisnya committee
        // layer terakhir, bukan keterkaitan langsung user dengan project).
        $base = $opts['base'] ?? Project::relatedTo($request->user()->id)
            ->whereHas('idea', fn ($q) => $q->visibleTo($request->user()));

        // Batasi ke status fase ini (Implementation/Completion). Proposal: null = semua.
        if (! empty($opts['phaseStatuses'])) {
            $base->whereIn('status', $opts['phaseStatuses']);
        }

        // Kebalikannya: buang status yang bukan urusan fase ini. Dipakai Proposal
        // agar status fase eksekusi/penyelesaian tidak ikut nongol. Memakai daftar
        // KECUALI (bukan daftar izin) supaya status baru tetap muncul secara default.
        if (! empty($opts['excludeStatuses'])) {
            $base->whereNotIn('status', $opts['excludeStatuses']);
        }

        // Filter BU/Unit by NAMA (dropdown dari hcis) pada ide terkait project —
        // logika sama seperti My Ideas / My Project.
        $query = (clone $base)
            ->when($request->filled('bu'), fn ($q) => $q->whereHas('idea', fn ($i) => $i->where('business_unit_name', $request->get('bu'))))
            ->when($request->filled('unit'), fn ($q) => $q->whereHas('idea', fn ($i) => $i->where('department_name', $request->get('unit'))))
            ->with(['idea.businessUnit', 'sponsor', 'leader']);

        $this->applyListSearchSort($query, $request, $config);

        // Jumlah per status (angka di tab) — sadar search & filter, sebelum tab.
        $counts = (clone $query)->reorder()->getQuery()
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $statusMap = $opts['statusMap'];
        $tab = array_key_exists($request->get('tab'), $statusMap) ? $request->get('tab') : 'all';
        if ($tab !== 'all') {
            $query->where('status', $statusMap[$tab]);
        }

        $perPage = $this->listPerPage($request);

        return view('projects.list', [
            'projects'     => $query->paginate($perPage)->withQueryString(),
            'perPage'      => $perPage,
            'buNames'      => KpnBusinessUnit::names(), // Business Unit dari master_bisnisunits; Unit cascade via AJAX
            'counts'       => $counts,
            'tab'          => $tab,
            'statusMap'    => $statusMap,
            'tabDefs'      => $opts['tabDefs'],
            'pageTitle'    => $opts['title'],
            'pageSubtitle' => $opts['subtitle'],
            'routeName'    => $opts['route'],
            'detailPhase'  => $opts['phase'] ?? null, // ?phase=... pada link Detail
            'detailRoute'  => $opts['detailRoute'] ?? 'projects.show',
            'createHint'   => $opts['createHint'] ?? null,
        ] + $this->listSortState($request, $config));
    }

    /* ---- Project Shell (My Ideas > Project Shell) -------------------- */

    /**
     * Daftar Project Shell untuk Idea Committee layer terakhir. Barisnya adalah
     * IDE yang sudah approved LEFT JOIN project shell-nya, sehingga:
     *   - ide yang belum dibuatkan shell tetap tampil → status "Not Assigned",
     *   - ide dengan lebih dari satu shell tampil satu baris per shell.
     * Super Admin melihat semua ide approved.
     *
     * Status per baris (Project::shellStatusFor): Not Assigned (belum ada shell)
     * → Draft (shell masih draft, bisa dihapus) → Assigned (shell sudah dibuat).
     */
    public function shellIndex(Request $request)
    {
        $user = $request->user();

        // LEFT JOIN memakai alias 'ps' agar kolom project (id/status/created_at)
        // tidak menabrak kolom ide; 'ideas.*' didahulukan supaya model tetap Idea.
        $base = Idea::query()
            ->where('ideas.status', 'approved')
            ->leftJoin('projects as ps', 'ps.idea_id', '=', 'ideas.idea_id')
            ->select([
                'ideas.*',
                'ps.id as shell_pk',
                'ps.is_shell_draft as shell_is_draft',
            ]);

        if (! $user->hasRole('Super Admin')) {
            $base->whereIn('ideas.idea_id', $this->lastLayerCommitteeIdeaIds($user));
        }

        $config = [
            // Search ditangani manual di bawah: entri ber-titik pada 'searchable'
            // diartikan HasListQuery sebagai relasi, sedangkan di sini kolomnya
            // WAJIB berkualifikasi tabel (idea_id ada di kedua tabel).
            'searchable'   => [],
            'sortable'     => [
                'idea_id'      => 'ideas.idea_id',
                'project_id'   => 'ps.project_id',
                'project_name' => 'ps.project_name',
                'created_at'   => 'ideas.created_at',
            ],
            'default_sort' => 'created_at',
            'default_dir'  => 'desc',
        ];

        // Filter BU/Unit by NAMA (dropdown dari hcis) — kolom denormalisasi di ideas.
        $term  = trim((string) $request->get('q'));
        $query = (clone $base)
            ->when($request->filled('bu'), fn ($q) => $q->where('ideas.business_unit_name', $request->get('bu')))
            ->when($request->filled('unit'), fn ($q) => $q->where('ideas.department_name', $request->get('unit')))
            ->when($term !== '', fn ($q) => $q->where(function ($sub) use ($term) {
                foreach (['ideas.idea_id', 'ideas.idea_name', 'ps.project_id', 'ps.project_name'] as $col) {
                    $sub->orWhere($col, 'like', "%{$term}%");
                }
            }));

        $this->applyListSearchSort($query, $request, $config);

        // Status shell bukan kolom tunggal — disaring dari hasil JOIN.
        $filters = [
            'draft'        => fn ($q) => $q->where('ps.is_shell_draft', true),
            'assigned'     => fn ($q) => $q->where('ps.is_shell_draft', false),
            'not_assigned' => fn ($q) => $q->whereNull('ps.id'),
        ];

        // Jumlah per status (angka di tab) — sadar search & filter, sebelum tab.
        $counts = collect($filters)
            ->map(fn ($filter) => (clone $query)->reorder()->tap($filter)->count());

        $tab = array_key_exists($request->get('tab'), $filters) ? $request->get('tab') : 'all';
        if ($tab !== 'all') {
            $query->tap($filters[$tab]);
        }

        $perPage = $this->listPerPage($request);
        $rows    = $query->paginate($perPage)->withQueryString();

        // Boleh membuat/melanjutkan shell? Hanya committee layer terakhir ide ybs
        // (authorizeShellCreator). Daftar user non-admin SUDAH disaring dengan
        // syarat yang sama, jadi pemeriksaan per baris hanya perlu untuk Super
        // Admin — yang melihat semua ide approved, termasuk yang bukan miliknya.
        // Baris yang tidak boleh menampilkan dialog penjelasan, bukan tombol mati.
        $workflow    = app(IdeaWorkflowService::class);
        $isAdminView = $user->hasRole('Super Admin');
        $rows->getCollection()->transform(function (Idea $row) use ($workflow, $user, $isAdminView) {
            $row->can_create_shell = ! $isAdminView || $workflow->isLastLayerCommittee($row, $user);

            return $row;
        });

        // Shell (beserta Leader/Sponsor) untuk baris di halaman ini. Diambil
        // terpisah, bukan lewat JOIN, karena users ada di koneksi kpncorp.
        $shells = Project::withShellDrafts()
            ->with(['leader', 'sponsor'])
            ->whereIn('id', $rows->pluck('shell_pk')->filter()->all())
            ->get()
            ->keyBy('id');

        return view('projects.shell-list', [
            'rows'    => $rows,
            'shells'  => $shells,
            'perPage' => $perPage,
            'buNames' => KpnBusinessUnit::names(),
            'counts'  => $counts,
            'total'   => $counts->sum(),
            'tab'     => $tab,
            'tabDefs' => [
                'all'          => 'All',
                'draft'        => 'Draft',
                'assigned'     => 'Assigned',
                'not_assigned' => 'Not Assigned',
            ],
        ] + $this->listSortState($request, $config));
    }

    /** Halaman progress sebuah Project Shell: Progress Summary + Activities. */
    public function shellProgress(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canSeeShell($project, $user), 403,
            'You do not have access to this project shell.');

        $project->load([
            'category', 'idea.businessUnit', 'idea.department', 'sponsor', 'leader', 'members.user',
            'implementationPlans', 'indicators',
            'statusLogs.changedBy', 'updates.requester', 'updates.reviewer',
        ]);

        return view('projects.shell-progress', [
            'project'    => $project,
            'summary'    => $this->shellSummary($project),
            'activities' => $this->shellActivities($project),
            'canCancel'  => $this->canCancel($project, $user)
                && ! in_array($project->status, ['completed', 'cancelled'], true),
        ]);
    }

    /** Ringkasan progress project (angka-angka untuk "Project Progress Summary"). */
    private function shellSummary(Project $project): array
    {
        $plans      = $project->implementationPlans;
        $planDone   = $plans->filter(fn ($p) => $p->actual_end !== null)->count();
        $planTotal  = $plans->count();

        $indicators = $project->indicators;
        $weight     = (float) $indicators->sum('weightage');
        // Capaian tertimbang: bobot indikator dianggap tercapai bila achievement terisi.
        $achieved   = (float) $indicators
            ->filter(fn ($i) => filled($i->achievement))
            ->sum('weightage');

        return [
            'planTotal'    => $planTotal,
            'planDone'     => $planDone,
            'planPercent'  => $planTotal ? (int) round($planDone / $planTotal * 100) : null,
            'memberCount'  => $project->members->count(),
            'weightTotal'  => $weight,
            'weightDone'   => $achieved,
            'indicatorPercent' => $weight > 0 ? (int) round($achieved / $weight * 100) : null,
            'lastActivity' => $project->statusLogs->max('created_at'),
        ];
    }

    /**
     * Linimasa gabungan: perubahan status, Project Update, dan realisasi
     * Implementation Plan — terbaru di atas.
     */
    private function shellActivities(Project $project, int $limit = 30): \Illuminate\Support\Collection
    {
        $items = collect();

        foreach ($project->statusLogs as $log) {
            $from = $log->old_status ? Project::STATUS_BADGES[$log->old_status][0] ?? $log->old_status : '—';
            $to   = Project::STATUS_BADGES[$log->new_status][0] ?? $log->new_status;
            $items->push([
                'at'    => $log->created_at,
                'seq'   => $log->id,
                'kind'  => 'status',
                'title' => "Status: {$from} → {$to}",
                'note'  => $log->remarks,
                'actor' => optional($log->changedBy)->name,
            ]);
        }

        foreach ($project->updates as $upd) {
            $type = ProjectUpdate::CHANGE_TYPES[$upd->change_type] ?? $upd->change_type;
            $items->push([
                'at'    => $upd->created_at,
                'seq'   => $upd->id,
                'kind'  => 'update',
                'title' => "Update request ({$type}) — " . ucfirst($upd->status),
                'note'  => $upd->description,
                'actor' => optional($upd->requester)->name,
            ]);

            // Baris kedua bila sudah direview, memakai waktu review.
            if ($upd->reviewed_by && $upd->updated_at) {
                $items->push([
                    'at'    => $upd->updated_at,
                    'seq'   => $upd->id,
                    'kind'  => 'update',
                    'title' => "Update request ({$type}) " . ucfirst($upd->status),
                    'note'  => $upd->review_note,
                    'actor' => optional($upd->reviewer)->name,
                ]);
            }
        }

        foreach ($project->implementationPlans as $plan) {
            if (! $plan->actual_start && ! $plan->actual_end) {
                continue;
            }
            $range = trim(
                (optional($plan->actual_start)->format('d M Y') ?? '?')
                . ' → ' . (optional($plan->actual_end)->format('d M Y') ?? 'ongoing')
            );
            $items->push([
                'at'    => $plan->updated_at,
                'seq'   => $plan->id,
                'kind'  => 'plan',
                'title' => 'Implementation actual: ' . $plan->activity,
                'note'  => $range,
                'actor' => null,
            ]);
        }

        // Ambil dulu $limit entri TERBARU (-timestamp), lalu tampilkan menaik:
        // paling lama di atas, sehingga history terbaca sesuai urutan kejadian /
        // sequence layer (Submitted -> On Review dulu, baru On Review -> On Review).
        // Bila beberapa entri jatuh pada detik yang sama - mis. approval Sponsor L1
        // lalu auto-approve L2 - tie-break 'seq' menaik menjaga urutan: L1 dulu, baru L2.
        return $items
            ->filter(fn ($i) => $i['at'] !== null)
            ->sortBy(fn ($i) => [-$i['at']->getTimestamp(), $i['seq']])
            ->take($limit)
            ->sortBy(fn ($i) => [$i['at']->getTimestamp(), $i['seq']])
            ->values();
    }

    /**
     * idea_id dari ide-ide APPROVED yang user ini review di layer TERAKHIR —
     * dasar menu Project Shell (termasuk ide yang belum dibuatkan shell).
     * Dipersempit dulu lewat committee assignment BU agar pemeriksaan layer
     * per ide (di PHP) tidak menyapu seluruh tabel ideas.
     */
    private function lastLayerCommitteeIdeaIds(User $user): array
    {
        $candidates = Idea::query()
            ->where('status', 'approved')
            ->whereExists(function ($sub) use ($user) {
                $sub->selectRaw('1')
                    ->from('committee_assignments as ca')
                    ->where('ca.approval_type', 'idea')
                    ->where('ca.user_id', $user->id)
                    ->whereColumn('ca.business_unit_id', 'ideas.business_unit_id');
            })
            ->get();

        $workflow = app(IdeaWorkflowService::class);

        return $candidates
            ->filter(fn (Idea $idea) => $workflow->isLastLayerCommittee($idea, $user))
            ->pluck('idea_id')
            ->all();
    }

    /** Boleh melihat Project Shell ini? (committee layer terakhir / Super Admin / leader / sponsor) */
    private function canSeeShell(Project $project, User $user): bool
    {
        if ($user->hasRole('Super Admin')
            || $project->project_leader_id === $user->id
            || $project->project_sponsor_id === $user->id) {
            return true;
        }

        return $project->idea
            && app(IdeaWorkflowService::class)->isLastLayerCommittee($project->idea, $user);
    }

    /**
     * Detail project (read-only, 6 section).
     */
    public function show(Request $request, Project $project)
    {
        abort_unless(
            Project::relatedTo($request->user()->id)->whereKey($project->id)->exists(),
            403
        );

        $project->load([
            'category',
            'idea.businessUnit', 'idea.department', 'idea.user',
            'sponsor', 'leader', 'members.user', 'budgets',
            'implementationPlans', 'indicators', 'statusLogs.changedBy', 'attachments.uploader',
            'updates.requester', 'updates.reviewer', 'approvals.user',
        ]);

        $user = $request->user();

        // Field "Actual" hanya muncul saat detail dibuka dari konteks Project
        // Implementation (?phase=implementation) DAN project memang sedang berjalan.
        // Dibuka dari Project Proposal → tampilan tetap proposal (tanpa Actual).
        $isImplementationView = $request->query('phase') === 'implementation';
        $showActual = $project->isInExecution() && $isImplementationView;

        // Opsi PIC = anggota tim project (Leader + Sponsor + Members).
        $teamMembers = collect([$project->leader, $project->sponsor])
            ->merge($project->members->map(fn ($m) => $m->user))
            ->filter()
            ->unique('id')
            ->values();

        // Status berbasis tanggal (Ongoing saat mencapai Planned Start) dihitung ulang
        // setiap detail dibuka. Tanpa cron, inilah titik paling awal perubahan terlihat.
        if ($project->isInExecution()) {
            $this->recomputeExecutionStatus($project, $user);
            $project->refresh();
        }

        $updateSvc = app(ProjectUpdateService::class);

        // Hanya muat user PIC yang benar-benar dipakai (untuk resolusi nama di tabel plan) —
        // BUKAN seluruh user hcis. Penambahan member memakai search AJAX (org.users).
        $picIds = $project->implementationPlans
            ->pluck('pic_user_ids')->filter()->flatten()->unique()->values();

        $data = [
            'project'            => $project,
            'showActual'         => $showActual,
            'phase'              => $isImplementationView ? 'implementation' : null,
            // ?view=1 (tombol mata di daftar) memaksa halaman jadi lihat-saja,
            // walaupun user sebenarnya berwenang mengubah.
            'canEdit'            => ! $request->boolean('view') && $project->canLeaderEditProposal($user),
            'viewOnly'           => $request->boolean('view'),
            'isLeader'           => $project->project_leader_id === $user->id,
            'isSponsor'          => $project->project_sponsor_id === $user->id,
            'isReviewer'         => app(ProjectApprovalWorkflowService::class)->isCurrentReviewer($project, $user),
            // Actual tidak lagi punya baseline terpisah: pengisiannya menjadi change
            // request per section, jadi tidak ada status yang mengunci pengeditan.
            'canTrack'           => $showActual && $project->isTeamMember($user),
            'canSubmitCompletion' => $project->project_leader_id === $user->id && $project->isInExecution(),
            'canRequestUpdate'   => $project->isInExecution() && $project->isTeamMember($user),
            'canCancel'          => $this->canCancel($project, $user) && ! in_array($project->status, ['completed', 'cancelled'], true),
            'reviewableUpdateIds' => $project->updates->filter(fn ($u) => $updateSvc->isReviewer($u, $user))->pluck('id'),
            'updateTypes'        => ProjectUpdate::CHANGE_TYPES,
            'users'              => User::whereIn('id', $picIds)->get(),
            'teamMembers'        => $teamMembers,
            'canUploadAttachment' => $project->isTeamMember($user) || $user->hasRole('Super Admin'),
            // Dialog Edit per baris: dibuka utk Leader (fase proposal) maupun anggota
            // tim (fase implementation). $proposalReadOnly menandai field asal proposal
            // yang tidak boleh diubah oleh anggota tim non-Leader.
            'canEditRow'       => $this->canEditRow($project, $user),
            // Perubahan proposal yang masih ditahan (belum diajukan approval).
            'pendingDrafts'    => app(ProjectChangeStagingService::class)->drafts($project),
            // Permintaan perubahan yang menunggu keputusan user ini, lengkap dgn
            // perbandingan before/after tiap field.
            'changeReviews'    => app(ProjectChangeStagingService::class)
                ->reviewableOn($project, $user)
                ->map(fn ($u) => [
                    'update' => $u,
                    'label'  => ProjectChangeStagingService::TYPE_LABEL[$u->change_type] ?? $u->change_type,
                    'diff'   => app(ProjectChangeStagingService::class)->diff($u),
                ]),
            'canSubmitChanges' => $project->project_leader_id === $user->id && $project->isInExecution(),
            'canDeleteRow'     => $project->canLeaderEditProposal($user),
            'proposalReadOnly' => ! $this->leaderMayEditProposalFields($project, $user),
        ];

        // Mode lihat-saja (tombol mata di daftar My Project): matikan SEMUA
        // kemampuan mengubah, bukan hanya canEdit — supaya halaman benar-benar
        // read-only dan berbeda nyata dari tombol pensil.
        if ($data['viewOnly']) {
            foreach ([
                'canEdit', 'canTrack',
                'canSubmitCompletion', 'canRequestUpdate', 'canCancel', 'canUploadAttachment',
                'canEditRow', 'canDeleteRow', 'canSubmitChanges',
            ] as $flag) {
                $data[$flag] = false;
            }
            $data['reviewableUpdateIds'] = collect();
        }

        return view('projects.show', $data);
    }

    /* ---- Submit proposal (Leader) & keputusan Sponsor ---------------- */

    public function submitProposal(Request $request, Project $project)
    {
        abort_unless($project->isEditableByLeader($request->user()), 403);

        // Syarat minimal submit (FR-181): minimal 1 activity & 1 indicator.
        if ($project->implementationPlans()->count() === 0 || $project->indicators()->count() === 0) {
            return back()->with('error', 'At least 1 Implementation Plan and 1 Success Indicator are required before submitting.');
        }

        // Total weightage Success Indicators harus 100% (T-43/214).
        $totalWeight = (float) $project->indicators()->sum('weightage');
        if (abs($totalWeight - 100.0) > 0.01) {
            return back()->with('error', "Total Success Indicator weightage must be 100% (currently {$totalWeight}%).");
        }

        $this->transition($project, 'submitted', $request->user(), 'Proposal disubmit oleh Project Leader.');

        return back()->with('success', 'Proposal submitted to the Project Sponsor.');
    }

    public function sponsorApprove(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        $this->authorizeSponsor($request, $project);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        // Alirkan ke committee proposal (atau langsung Approved bila belum ada committee).
        $wf->start($project, 'committee_review', $request->user(), $note);

        return back()->with('success', 'Proposal approved by the Sponsor.');
    }

    /* ---- Committee review (Proposal & Completion) ------------------- */

    public function reviewQueue(Request $request, ProjectApprovalWorkflowService $wf)
    {
        $config = [
            'searchable'   => ['project_id', 'project_name'],
            'sortable'     => [
                'project_id'    => 'project_id',
                'project_name'  => 'project_name',
                'current_layer' => 'current_layer',
                'created_at'    => 'created_at',
            ],
            'default_sort' => 'created_at',
            'default_dir'  => 'desc',
        ];

        $base = $wf->reviewQueueFor($request->user());

        // Project yang punya permintaan perubahan menunggu keputusan user ini ikut
        // masuk Task Box — keputusannya diambil di panel Change Request pada detail.
        $changeIds = app(ProjectChangeStagingService::class)
            ->reviewQueueFor($request->user())
            ->pluck('project_id')
            ->unique();

        if ($changeIds->isNotEmpty()) {
            $antrean = (clone $base)->select('projects.id');
            $base = Project::query()->where(fn ($q) => $q
                ->whereIn('projects.id', $antrean)
                ->orWhereIn('projects.id', $changeIds));
        }

        // Filter BU/Unit by NAMA (dropdown dari hcis) pada ide terkait — sama seperti My Project.
        $query = (clone $base)
            ->when($request->filled('bu'), fn ($q) => $q->whereHas('idea', fn ($i) => $i->where('business_unit_name', $request->get('bu'))))
            ->when($request->filled('unit'), fn ($q) => $q->whereHas('idea', fn ($i) => $i->where('department_name', $request->get('unit'))))
            ->with(['idea.businessUnit', 'leader', 'sponsor']);

        $this->applyListSearchSort($query, $request, $config);

        $perPage = $this->listPerPage($request);

        return view('projects.review', [
            'projects' => $query->paginate($perPage)->withQueryString(),
            'perPage'  => $perPage,
            'buNames'  => KpnBusinessUnit::names(), // Business Unit dari master_bisnisunits; Unit cascade via AJAX
        ] + $this->listSortState($request, $config));
    }

    public function reviewApprove(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        abort_unless($wf->isCurrentReviewer($project, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $wf->approve($project, $request->user(), $note);

        return redirect()->route('projects.review')->with('success', "Keputusan tersimpan untuk {$project->project_id}.");
    }

    public function reviewRevision(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        abort_unless($wf->isCurrentReviewer($project, $request->user()), 403);
        abort_unless($project->status === 'committee_review', 403,
            'Revision Required only applies to a proposal that is under committee review.');

        // Catatan wajib: Leader perlu tahu apa yang harus diperbaiki.
        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];
        $wf->requestRevision($project, $request->user(), $note);

        return redirect()->route('projects.review')
            ->with('success', "Revision required: {$project->project_id} returned to the Project Leader.");
    }

    public function reviewReject(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        abort_unless($wf->isCurrentReviewer($project, $request->user()), 403);

        // Catatan wajib: penolakan bersifat final, pengaju berhak tahu alasannya.
        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];
        $wf->reject($project, $request->user(), $note);

        return redirect()->route('projects.review')->with('success', "Ditolak: {$project->project_id}.");
    }

    /* ---- Completion request (Project Leader) ------------------------ */

    public function submitCompletion(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        abort_unless(
            $project->project_leader_id === $request->user()->id && $project->isInExecution(),
            403,
            'Only the Project Leader of an ongoing project may submit completion.'
        );

        $data = $request->validate([
            'project_summary' => ['required', 'string', 'max:20000'],
        ]);

        // Wajib minimal 1 Success Indicator (T-66/181).
        if ($project->indicators()->count() === 0) {
            return back()->with('error', 'At least 1 Success Indicator is required before submitting completion.');
        }

        $project->update(['project_summary' => $data['project_summary']]);
        $wf->start($project->fresh(), 'completion_review', $request->user(), 'Completion request disubmit.');

        return back()->with('success', 'Completion request disubmit.');
    }

    /* ---- Project Update Request (T-260-268) ------------------------- */

    public function requestUpdate(Request $request, Project $project, ProjectUpdateService $svc)
    {
        $user = $request->user();
        abort_unless($project->isInExecution() && $project->isTeamMember($user), 403,
            'Only members of an ongoing project team may request an update.');

        $data = $request->validate([
            'change_type' => ['required', Rule::in(array_keys(ProjectUpdate::CHANGE_TYPES))],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $isSponsor = $project->project_sponsor_id === $user->id;
        $approver  = $svc->resolveApprover($data['change_type'], $isSponsor);

        // Fallback: routing committee tapi belum ada committee -> ke Sponsor.
        if ($approver === 'committee' && $svc->proposalCommitteeLastLayer($project)->isEmpty()) {
            $approver = 'sponsor';
        }

        $auto = $approver === 'auto';

        $project->updates()->create([
            'requested_by'    => $user->id,
            'change_type'     => $data['change_type'],
            'description'     => $data['description'],
            'approver_role'   => $approver,
            'status'          => $auto ? 'approved' : 'pending',
            'reviewed_by'     => $auto ? $user->id : null,
            'review_note'     => $auto ? 'Auto-approved (diajukan Sponsor).' : null,
            'snapshot_before' => $svc->snapshot($project),
        ]);

        return back()->with('success', $auto
            ? 'Update request auto-approved. Please edit and then Apply.'
            : 'Update request diajukan ke '.($approver === 'committee' ? 'Committee' : 'Sponsor').'.');
    }

    /**
     * Permintaan bertipe staged (team/plan_indicator/budget change) memakai
     * ProjectChangeStagingService: approve meneruskan ke layer berikutnya, dan
     * pada layer terakhir payload-nya benar-benar diterapkan ke data. Tipe lama
     * tetap memakai alur ProjectUpdateService.
     */
    private function isStagedChange(ProjectUpdate $update): bool
    {
        return array_key_exists($update->change_type, ProjectChangeStagingService::TYPE_LABEL);
    }

    public function approveUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        if ($this->isStagedChange($update)) {
            $staging = app(ProjectChangeStagingService::class);
            abort_unless($staging->isReviewer($update, $request->user()), 403);

            $hasil = $staging->approve($update, $request->user(), $note);
            $label = ProjectChangeStagingService::TYPE_LABEL[$update->change_type];

            return back()->with('success', $hasil === 'applied'
                ? "{$label} approved and applied to the project."
                : "{$label} approved; forwarded to layer {$update->fresh()->current_layer}.");
        }

        abort_unless($svc->isReviewer($update, $request->user()), 403);
        $update->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'review_note' => $note]);

        return back()->with('success', 'Update request approved. The Project Leader can edit and then Apply.');
    }

    /** Kembalikan permintaan ke Project Leader untuk diperbaiki (semua layer). */
    public function revisionUpdate(Request $request, Project $project, ProjectUpdate $update)
    {
        abort_unless($update->project_id === $project->id, 404);
        abort_unless($this->isStagedChange($update), 404);

        $staging = app(ProjectChangeStagingService::class);
        abort_unless($staging->isReviewer($update, $request->user()), 403);

        // Catatan wajib: Leader perlu tahu apa yang harus diperbaiki.
        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];
        $staging->requestRevision($update, $request->user(), $note);

        $label = ProjectChangeStagingService::TYPE_LABEL[$update->change_type];

        return back()->with('success', "{$label} returned to the Project Leader for revision.");
    }

    public function rejectUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);

        // Penolakan change request juga wajib disertai alasan.
        $note = $this->isStagedChange($update)
            ? $request->validate(['note' => ['required', 'string', 'max:2000']])['note']
            : ($request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null);

        if ($this->isStagedChange($update)) {
            $staging = app(ProjectChangeStagingService::class);
            abort_unless($staging->isReviewer($update, $request->user()), 403);
            $staging->reject($update, $request->user(), $note);

            return back()->with('success', ProjectChangeStagingService::TYPE_LABEL[$update->change_type] . ' rejected.');
        }

        abort_unless($svc->isReviewer($update, $request->user()), 403);
        $update->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'review_note' => $note]);

        return back()->with('success', 'Update request rejected.');
    }

    public function applyUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);
        abort_unless($project->project_leader_id === $request->user()->id && $update->status === 'approved', 403,
            'Only the Project Leader may apply an approved update.');

        // Rekam kondisi setelah perubahan (before/after) lalu tutup update.
        $update->update(['status' => 'applied', 'snapshot_after' => $svc->snapshot($project->fresh())]);

        return back()->with('success', 'Perubahan diterapkan (update ditutup).');
    }

    /* ---- Project Cancellation (T-184/185/186/188/97) --------------- */

    public function cancelProject(Request $request, Project $project)
    {
        abort_unless($this->canCancel($project, $request->user()), 403,
            'Only the Project Leader, Sponsor, last idea committee layer, or a Super Admin may cancel.');

        if (in_array($project->status, ['completed', 'cancelled'], true)) {
            return back()->with('error', 'A Completed/Cancelled project cannot be cancelled.');
        }

        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];

        $this->transition($project, 'cancelled', $request->user(), 'Cancelled: '.$reason);

        // T-97: pending approval/update otomatis hilang.
        $project->updates()->where('status', 'pending')->update(['status' => 'cancelled']);

        return back()->with('success', 'Project cancelled.');
    }

    private function canCancel(Project $project, User $user): bool
    {
        // Project Leader & Project Sponsor (perilaku lama), ditambah Idea Committee
        // layer terakhir dan Super Admin yang membatalkan dari My Ideas > Project Shell.
        if ($project->project_leader_id === $user->id
            || $project->project_sponsor_id === $user->id
            || $user->hasRole('Super Admin')) {
            return true;
        }

        return $project->idea
            && app(IdeaWorkflowService::class)->isLastLayerCommittee($project->idea, $user);
    }

    /**
     * Kirim seluruh perubahan proposal yang masih draft menjadi change request,
     * SATU permintaan per jenis section (Team / Plan & Indicator / Budget).
     */
    public function submitChanges(Request $request, Project $project, ProjectChangeStagingService $svc)
    {
        abort_unless($project->project_leader_id === $request->user()->id && $project->isInExecution(), 403,
            'Only the Project Leader of a running project may submit changes.');

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        $terkirim = $svc->submitDrafts($project, $request->user(), $note);

        if (! $terkirim) {
            return back()->with('error', 'There is no pending change to submit.');
        }

        return $this->backToSection($project, 'section-implementation',
            'Submitted for approval: ' . implode(', ', $terkirim) . '.',
            ['phase' => 'implementation']);
    }

    /** Pesan sukses yang menjelaskan bila perubahan masih menunggu pengajuan. */
    private function pesanSimpan(string $normal, int $distage): string
    {
        return $distage > 0
            ? "Saved. {$distage} proposal field(s) are held as a draft change and take effect only after approval - press Update Project to submit."
            : $normal;
    }

    /** Format persen tanpa desimal berlebih: 33.50 -> "33.5", 100.00 -> "100". */
    private function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /** Redirect ke halaman project + anchor section (agar tetap di posisi, bukan scroll ke atas). */
    private function backToSection(Project $project, string $anchor, string $msg, array $query = [])
    {
        return redirect()
            ->to(route('projects.show', ['project' => $project] + $query) . '#' . $anchor)
            ->with('success', $msg);
    }

    public function sponsorRevision(Request $request, Project $project)
    {
        $this->authorizeSponsor($request, $project);

        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];

        // Sponsor = Layer 1, jadi keputusannya ikut tercatat di Approval History.
        ProjectApproval::create([
            'project_id' => $project->id,
            'layer'      => ProjectApprovalWorkflowService::SPONSOR_LAYER,
            'user_id'    => $request->user()->id,
            'decision'   => 'revision',
            'note'       => $note,
        ]);

        $this->transition($project, 'revision_required', $request->user(),
            'Revision required at layer ' . ProjectApprovalWorkflowService::SPONSOR_LAYER
            . " (Project Sponsor); returned to the Project Leader. Note: {$note}");

        return back()->with('success', 'Proposal returned to the Project Leader for revision.');
    }

    private function authorizeSponsor(Request $request, Project $project): void
    {
        abort_unless(
            $project->project_sponsor_id === $request->user()->id && $project->status === 'submitted',
            403,
            'Only the Project Sponsor may decide on a submitted proposal.'
        );
    }

    private function transition(Project $project, string $new, User $user, ?string $remarks = null): void
    {
        $old = $project->status;
        $project->update(['status' => $new]);
        $project->statusLogs()->create([
            'old_status' => $old,
            'new_status' => $new,
            'changed_by' => $user->id,
            'remarks'    => $remarks,
        ]);
    }

    /* ---- Section editable (hanya Project Leader project ini) --------- */

    public function storeImplementation(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        $request->validate([
            'rows'                    => ['required', 'array', 'min:1'],
            'rows.*.activity'         => ['required', 'string', 'max:2000'],
            'rows.*.planning_start'   => ['nullable', 'date'],
            // End tidak boleh mendahului Start. Wildcard pada parameter aturan
            // diselesaikan per-baris oleh Laravel (rows.0, rows.1, ...).
            'rows.*.planning_end'     => ['nullable', 'date', 'after_or_equal:rows.*.planning_start'],
            'rows.*.actual_start'     => ['nullable', 'date'],
            'rows.*.actual_end'       => ['nullable', 'date', 'after_or_equal:rows.*.actual_start'],
            'rows.*.pic_user_ids'     => ['nullable', 'array'],
            'rows.*.pic_user_ids.*'   => ['integer', 'exists:kpncorp.users,id'],
            'rows.*.remarks'          => ['nullable', 'string', 'max:2000'],
            'rows.*.attachments'      => ['nullable', 'array'],
            'rows.*.attachments.*'    => $this->attachmentRules(),
        ], [
            'rows.*.planning_end.after_or_equal' => 'Planned End Date must be on or after Planned Start Date.',
            'rows.*.actual_end.after_or_equal'   => 'Actual End must be on or after Actual Start.',
        ]);

        $seq = (int) $project->implementationPlans()->max('sequence_no');
        // Berkas tidak ikut di $request->input(); diambil per indeks baris dari file bag.
        $berkasPerBaris = $request->file('rows') ?: [];

        foreach (array_values($request->input('rows')) as $i => $r) {
            $plan = $project->implementationPlans()->create([
                'activity'       => $r['activity'],
                'planning_start' => $r['planning_start'] ?? null,
                'planning_end'   => $r['planning_end'] ?? null,
                'actual_start'   => $r['actual_start'] ?? null,
                'actual_end'     => $r['actual_end'] ?? null,
                'pic_user_ids'   => $r['pic_user_ids'] ?? [],
                'remarks'        => $r['remarks'] ?? null,
                'sequence_no'    => ++$seq,
            ]);

            foreach (($berkasPerBaris[$i]['attachments'] ?? []) as $file) {
                $plan->attachments()->create([
                    'file_name'   => $file->getClientOriginalName(),
                    'file_path'   => $file->store("project-implementation/{$project->id}"),
                    'file_size'   => $file->getSize(),
                    'uploaded_by' => $request->user()->id,
                ]);
            }
        }

        return $this->backToSection($project, 'section-implementation', 'Activity added.');
    }

    public function updateImplementation(Request $request, Project $project, ImplementationPlan $plan)
    {
        // Satu dialog Edit untuk semua peran; yang membedakan hanya field mana yang
        // boleh ikut tersimpan. Field proposal dari member SENGAJA diabaikan di sini
        // (bukan cuma di-readonly di layar) agar tidak bisa ditembus lewat request manual.
        $mayEditProposal = $this->authorizeRowEdit($request, $project);
        abort_unless($plan->project_id === $project->id, 404);

        $rules = [
            'actual_start' => ['nullable', 'date'],
            'actual_end'   => ['nullable', 'date', 'after_or_equal:actual_start'],
            'remarks'      => ['nullable', 'string', 'max:2000'],
        ];

        if ($mayEditProposal) {
            $rules += [
                'activity'       => ['required', 'string', 'max:2000'],
                'planning_start' => ['nullable', 'date'],
                'planning_end'   => ['nullable', 'date', 'after_or_equal:planning_start'],
                'pic_user_ids'   => ['nullable', 'array'],
                'pic_user_ids.*' => ['integer', 'exists:kpncorp.users,id'],
            ];
        }

        $data = $request->validate($rules, [
            'planning_end.after_or_equal' => 'Planned End Date must be on or after Planned Start Date.',
            'actual_end.after_or_equal'   => 'Actual End must be on or after Actual Start.',
        ]);

        // Actual & Remarks = realisasi lapangan -> SELALU langsung tersimpan,
        // termasuk saat project berjalan. Hanya field rencana (Activity, Planned
        // date, PIC) yang ditahan sebagai change request "Plan & Indicator Change".
        $langsung = [
            'actual_start' => $data['actual_start'] ?? null,
            'actual_end'   => $data['actual_end'] ?? null,
            'remarks'      => $data['remarks'] ?? null,
        ];

        $proposal = $mayEditProposal ? [
            'activity'       => $data['activity'],
            'planning_start' => $data['planning_start'] ?? null,
            'planning_end'   => $data['planning_end'] ?? null,
            'pic_user_ids'   => $data['pic_user_ids'] ?? [],
        ] : [];

        $distage = $this->writeOrStage($request, $project, $plan, $langsung, $proposal);

        // Lampiran (boleh banyak) disimpan setelah baris tersimpan.
        $this->storePlanAttachments($request, $project, $plan->fresh());

        if ($project->isInExecution()) {
            $this->recomputeExecutionStatus($project->fresh(), $request->user());
        }

        return $this->backToSection($project, 'section-implementation',
            $this->pesanSimpan('Activity updated.', $distage),
            $project->isInExecution() ? ['phase' => 'implementation'] : []);
    }

    /** Simpan berkas lampiran baru (multi-file) untuk sebuah Implementation Plan. */
    private function storePlanAttachments(Request $request, Project $project, ImplementationPlan $plan): void
    {
        $files = $request->file('attachments') ?: array_filter([$request->file('attachment')]);
        if (! $files) {
            return;
        }

        $request->validate([
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => $this->attachmentRules(),
        ]);

        foreach ($files as $file) {
            $plan->attachments()->create([
                'file_name'   => $file->getClientOriginalName(),
                'file_path'   => $file->store("project-implementation/{$project->id}"),
                'file_size'   => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    public function destroyImplementation(Request $request, Project $project, ImplementationPlan $plan)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($plan->project_id === $project->id, 404);
        $plan->delete();

        return $this->backToSection($project, 'section-implementation', 'Activity removed.');
    }

    /**
     * Update ACTUAL implementation saat project berjalan (tracking).
     * Status project otomatis dihitung ulang (Ongoing/Delayed) — T-63/176/177.
     */
    public function updateImplementationActual(Request $request, Project $project, ImplementationPlan $plan)
    {
        $this->authorizeTeamExecution($request, $project);
        abort_unless($plan->project_id === $project->id, 404);

        $data = $request->validate([
            'actual_start' => ['nullable', 'date'],
            'actual_end'   => ['nullable', 'date', 'after_or_equal:actual_start'],
            'remarks'      => ['nullable', 'string', 'max:2000'],
            // 1 file per plan, maks 7 MB.
            'attachment'   => ['nullable', 'file', 'max:7168', 'mimes:pdf,docx,xlsx,jpg,jpeg,png,pptx'],
        ], [
            'actual_end.after_or_equal' => 'Actual Timeline End must be on or after Actual Timeline Start.',
        ]);

        // Baris dikunci selama proses ini: submit lain atas plan yang sama antre,
        // dan ditolak bila datanya sudah berubah sejak form dibuka.
        $this->withRecordLock($request, $plan, function (ImplementationPlan $plan) use ($request, $project, $data) {
            $plan->fill([
                'actual_start' => $data['actual_start'] ?? null,
                'actual_end'   => $data['actual_end'] ?? null,
                'remarks'      => $data['remarks'] ?? null,
            ]);

            // Attachment: ganti file lama bila ada unggahan baru. Setiap unggahan —
            // baik yang pertama maupun penggantian — dicatat siapa pelakunya dan kapan,
            // supaya jejaknya terlihat langsung di UI (bukan hanya di audit log).
            if ($request->hasFile('attachment')) {
                $isReplacement = (bool) $plan->attachment_path;

                if ($isReplacement) {
                    $this->deleteAttachmentFile($plan->attachment_path);
                }

                $file = $request->file('attachment');
                $plan->attachment_path          = $file->store("project-implementation/{$project->id}");
                $plan->attachment_name          = $file->getClientOriginalName();
                $plan->attachment_uploaded_by   = $request->user()->id;
                $plan->attachment_uploaded_at   = now();
                $plan->attachment_replace_count = (int) $plan->attachment_replace_count + ($isReplacement ? 1 : 0);
            }

            $plan->save();
        });

        $this->recomputeExecutionStatus($project->fresh(), $request->user());

        return $this->backToSection($project, 'section-implementation', 'Actual implementation updated.', ['phase' => 'implementation']);
    }

    /**
     * Buka satu lampiran Implementation Plan di tab baru. Berkas yang bisa
     * ditampilkan langsung (pdf/gambar) dikirim inline; sisanya diunduh.
     */
    public function viewPlanAttachment(Request $request, Project $project, ImplementationPlan $plan, ImplementationPlanAttachment $attachment)
    {
        abort_unless($plan->project_id === $project->id, 404);
        abort_unless($attachment->implementation_plan_id === $plan->id, 404);
        abort_unless(
            Project::relatedTo($request->user()->id)->whereKey($project->id)->exists()
                || $request->user()->hasRole('Super Admin'),
            403
        );

        return $attachment->isInlineViewable()
            ? $this->viewAttachmentFile($attachment->file_path, $attachment->file_name)
            : $this->downloadAttachmentFile($attachment->file_path, $attachment->file_name);
    }

    /** Hapus satu lampiran Implementation Plan (hak sama dgn mengedit barisnya). */
    public function destroyPlanAttachment(Request $request, Project $project, ImplementationPlan $plan, ImplementationPlanAttachment $attachment)
    {
        $this->authorizeRowEdit($request, $project);
        abort_unless($plan->project_id === $project->id, 404);
        abort_unless($attachment->implementation_plan_id === $plan->id, 404);

        $this->deleteAttachmentFile($attachment->file_path);
        $attachment->delete();

        return $this->backToSection($project, 'section-implementation', 'Attachment removed.',
            $project->isInExecution() ? ['phase' => 'implementation'] : []);
    }

    /** Buka satu lampiran baris Budget di tab baru. */
    public function viewBudgetAttachment(Request $request, Project $project, ProjectBudget $budget, ProjectBudgetAttachment $attachment)
    {
        abort_unless($budget->project_id === $project->id, 404);
        abort_unless($attachment->project_budget_id === $budget->id, 404);
        abort_unless(
            Project::relatedTo($request->user()->id)->whereKey($project->id)->exists()
                || $request->user()->hasRole('Super Admin'),
            403
        );

        return $attachment->isInlineViewable()
            ? $this->viewAttachmentFile($attachment->file_path, $attachment->file_name)
            : $this->downloadAttachmentFile($attachment->file_path, $attachment->file_name);
    }

    /** Hapus satu lampiran baris Budget (hak sama dgn mengedit barisnya). */
    public function destroyBudgetAttachment(Request $request, Project $project, ProjectBudget $budget, ProjectBudgetAttachment $attachment)
    {
        $this->authorizeRowEdit($request, $project);
        abort_unless($budget->project_id === $project->id, 404);
        abort_unless($attachment->project_budget_id === $budget->id, 404);

        $this->deleteAttachmentFile($attachment->file_path);
        $attachment->delete();

        return $this->backToSection($project, 'section-budget', 'Attachment removed.',
            $project->isInExecution() ? ['phase' => 'implementation'] : []);
    }

    /** Simpan berkas lampiran baru (multi-file) untuk sebuah baris Budget. */
    private function storeBudgetAttachments(Request $request, Project $project, ProjectBudget $budget): void
    {
        $files = $request->file('attachments') ?: [];
        if (! $files) {
            return;
        }

        $request->validate([
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => $this->attachmentRules(),
        ]);

        foreach ($files as $file) {
            $budget->attachments()->create([
                'file_name'   => $file->getClientOriginalName(),
                'file_path'   => $file->store("project-budget/{$project->id}"),
                'file_size'   => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    /** Unduh attachment sebuah Implementation Plan (akses = anggota project). */
    public function downloadImplementationAttachment(Request $request, Project $project, ImplementationPlan $plan)
    {
        abort_unless($plan->project_id === $project->id, 404);
        abort_unless(
            Project::relatedTo($request->user()->id)->whereKey($project->id)->exists()
                || $request->user()->hasRole('Super Admin'),
            403
        );
        abort_unless($plan->attachment_path, 404, 'No attachment.');

        return $this->downloadAttachmentFile($plan->attachment_path, $plan->attachment_name);
    }

    /**
     * Otorisasi update "actual" saat project berjalan — boleh SEMUA anggota tim
     * (Leader/Sponsor/Member), bukan hanya Leader (tracking implementation).
     */
    /**
     * Boleh membuka dialog Edit sebuah baris (Implementation Plan / Indicator / Budget)?
     * Leader saat proposal masih bisa diubah, ATAU anggota tim saat project berjalan
     * dan Actual masih draft. Yang membedakan: apa saja yang boleh ia ubah di dalamnya
     * (lihat leaderMayEditProposalFields()).
     */
    private function canEditRow(Project $project, User $user): bool
    {
        return $project->canLeaderEditProposal($user)
            || ($project->isTeamMember($user) && $project->isInExecution());
    }

    /**
     * Boleh menyentuh field asal proposal?
     *  - fase proposal  : Leader saat draft/revision -> tersimpan LANGSUNG.
     *  - fase berjalan  : Leader juga boleh, tapi hasilnya masuk STAGING dan baru
     *                     berlaku setelah change request disetujui.
     */
    private function leaderMayEditProposalFields(Project $project, User $user): bool
    {
        return $project->canLeaderEditProposal($user)
            || ($project->project_leader_id === $user->id && $project->isInExecution());
    }

    /** Perubahan field proposal harus ditampung (belum berlaku) alih-alih ditulis langsung? */
    private function stagesProposalChanges(Project $project, User $user): bool
    {
        // Saat project berjalan, suntingan field RENCANA melewati change request
        // per section. Data realisasi (Actual, Remarks, Achievement) tidak lewat
        // sini — writeOrStage() menulisnya langsung lewat argumen $langsung.
        return ! $project->canLeaderEditProposal($user)
            && $project->isInExecution()
            && ($project->project_leader_id === $user->id || $this->canEditRow($project, $user));
    }

    /**
     * Tulis perubahan: field proposal ke staging bila project sudah berjalan,
     * selebihnya langsung ke barisnya. Mengembalikan jumlah field yang di-stage.
     */
    private function writeOrStage(Request $request, Project $project, $row, array $langsung, array $proposal): int
    {
        $staging = $this->stagesProposalChanges($project, $request->user());

        $this->withRecordLock($request, $row, function ($row) use ($langsung, $proposal, $staging) {
            $row->update($staging ? $langsung : array_merge($langsung, $proposal));
        });

        if (! $staging || ! $proposal) {
            return 0;
        }

        // Hanya field yang benar-benar BERBEDA yang ditampung.
        $berubah = collect($proposal)
            ->reject(fn ($v, $k) => $this->sameValue($row->{$k} ?? null, $v))
            ->all();

        if ($berubah) {
            // Nilai lama ikut dikirim agar penilai bisa melihat perbandingan
            // before/after saat memeriksa permintaan.
            $sebelum = collect($berubah)
                ->map(fn ($v, $k) => $this->displayValue($row->{$k} ?? null))
                ->all();

            app(ProjectChangeStagingService::class)
                ->stage($project, $request->user(), $row::class, $row->getKey(), $berubah, $sebelum);
        }

        return count($berubah);
    }

    /** Bentuk nilai yang aman disimpan di payload (tanggal jadi Y-m-d, objek jadi skalar). */
    private function displayValue($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_array($value) ? $value : (is_scalar($value) || $value === null ? $value : (string) $value);
    }

    /** Bandingkan nilai lama vs baru dgn toleran terhadap tipe (tanggal, angka, array). */
    private function sameValue($lama, $baru): bool
    {
        if ($lama instanceof \DateTimeInterface) {
            $lama = $lama->format('Y-m-d');
        }
        if (is_array($lama) || is_array($baru)) {
            return json_encode((array) $lama) === json_encode((array) $baru);
        }

        return (string) $lama === (string) $baru;
    }

    /**
     * Gate bersama untuk dialog Edit: menolak bila tidak berhak sama sekali, dan
     * mengembalikan penanda apakah field proposal boleh ikut diubah.
     */
    private function authorizeRowEdit(Request $request, Project $project): bool
    {
        $user = $request->user();
        abort_unless($this->canEditRow($project, $user), 403,
            'Only the Project Leader (during draft/revision) or a team member while the project is running may edit this.');

        return $this->leaderMayEditProposalFields($project, $user);
    }

    private function authorizeTeamExecution(Request $request, Project $project): void
    {
        // Cukup anggota tim pada project yang berjalan. Tidak ada lagi syarat status
        // baseline Actual — isiannya memang tidak langsung berlaku, melainkan menjadi
        // change request yang menunggu persetujuan section terkait.
        abort_unless(
            $project->isTeamMember($request->user()) && $project->isInExecution(),
            403,
            'Actual can only be filled in by team members while the project is running.'
        );
    }

    /** Hitung ulang status eksekusi dari Implementation Plan (T-176/177). */
    private function recomputeExecutionStatus(Project $project, User $user): void
    {
        if (! $project->isInExecution()) {
            return;
        }

        $plans      = $project->implementationPlans()->get();
        $anyDelayed = $plans->contains(fn ($p) => $p->status_label === 'Delayed');
        $anyStarted = $plans->contains(fn ($p) => $p->actual_start !== null);

        // Ongoing juga terpicu oleh TANGGAL: begitu hari ini mencapai Planned Start
        // salah satu activity, project dianggap berjalan walau Actual belum diinput.
        $anyDue = $plans->contains(fn ($p) => $p->planning_start
            && now()->startOfDay()->gte($p->planning_start->startOfDay()));

        $new = $anyDelayed ? 'delayed' : (($anyStarted || $anyDue) ? 'ongoing' : 'approved');

        if ($new !== $project->status) {
            $this->transition($project, $new, $user, 'Auto: execution status updated from activity progress.');
        }
    }

    public function storeIndicator(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        $request->validate([
            'rows'                     => ['required', 'array', 'min:1'],
            'rows.*.indicator'         => ['required', 'string', 'max:255'],
            'rows.*.description'       => ['nullable', 'string', 'max:2000'],
            'rows.*.baseline'          => ['nullable', 'numeric'],
            'rows.*.achievement_value' => ['nullable', 'numeric'], // TARGET
            'rows.*.achievement'       => ['nullable', 'numeric'], // aktual
            'rows.*.uom'               => ['nullable', Rule::in(ImplementationIndicator::uoms())],
            'rows.*.weightage'         => ['nullable', 'numeric', 'between:0,100'],
            'rows.*.type'              => ['required', Rule::in(ImplementationIndicator::TYPES)],
        ], [
            'rows.*.type.required' => 'Type is required for every indicator.',
        ]);

        // Total weightage seluruh indikator tidak boleh melewati 100%.
        $rows     = array_values($request->input('rows'));
        $existing = (float) $project->indicators()->sum('weightage');
        $adding   = array_sum(array_map(fn ($r) => (float) ($r['weightage'] ?? 0), $rows));

        if ($existing + $adding > 100.0001) { // toleransi kecil utk pembulatan float
            return back()->withInput()->withErrors([
                'rows' => 'Total weightage would become ' . $this->pct($existing + $adding)
                    . '%, which exceeds 100%. Currently used: ' . $this->pct($existing)
                    . '%, still available: ' . $this->pct(max(0, 100 - $existing)) . '%.',
            ]);
        }

        $sort = (int) $project->indicators()->max('sort_order');
        foreach (array_values($request->input('rows')) as $r) {
            $project->indicators()->create([
                'indicator'         => $r['indicator'],
                'description'       => $r['description'] ?? null,
                'baseline'          => $r['baseline'] ?? null,
                'achievement_value' => $r['achievement_value'] ?? null,
                'achievement'       => $r['achievement'] ?? null,
                'uom'               => $r['uom'] ?? null,
                'weightage'         => $r['weightage'] ?? null,
                'type'              => $r['type'] ?? null,
                'improvement'       => ImplementationIndicator::calcImprovement($r['baseline'] ?? null, $r['achievement'] ?? null, $r['type'] ?? null),
                'sort_order'        => ++$sort,
            ]);
        }

        return $this->backToSection($project, 'section-indicators', 'Indicator added.');
    }

    public function updateIndicator(Request $request, Project $project, ImplementationIndicator $indicator)
    {
        $mayEditProposal = $this->authorizeRowEdit($request, $project);
        abort_unless($indicator->project_id === $project->id, 404);

        // Member hanya boleh mengisi Achievement (aktual); sisanya field proposal.
        if (! $mayEditProposal) {
            $data = $request->validate(['achievement' => ['nullable', 'numeric']]);

            $this->withRecordLock($request, $indicator, function ($indicator) use ($data) {
                $indicator->update([
                    'achievement' => $data['achievement'] ?? null,
                    'improvement' => ImplementationIndicator::calcImprovement($indicator->baseline, $data['achievement'] ?? null, $indicator->type),
                ]);
            });

            return $this->backToSection($project, 'section-indicators', 'Achievement updated.', ['phase' => 'implementation']);
        }

        $data = $request->validate([
            'indicator'         => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'baseline'          => ['nullable', 'numeric'],
            'achievement_value' => ['nullable', 'numeric'], // TARGET
            'achievement'       => ['nullable', 'numeric'], // aktual
            'uom'               => ['nullable', Rule::in(ImplementationIndicator::uoms())],
            'weightage'         => ['nullable', 'numeric', 'between:0,100'],
            'type'              => ['required', Rule::in(ImplementationIndicator::TYPES)],
        ]);

        // Batas 100% juga berlaku saat mengubah bobot — indikator ini sendiri
        // dikeluarkan dari hitungan agar tidak dihitung dua kali.
        $others = (float) $project->indicators()->whereKeyNot($indicator->getKey())->sum('weightage');
        if ($others + (float) ($data['weightage'] ?? 0) > 100.0001) {
            return back()->withInput()->withErrors([
                'weightage' => 'Total weightage would exceed 100%. Other indicators already use '
                    . $this->pct($others) . '%, so this one can be at most ' . $this->pct(max(0, 100 - $others)) . '%.',
            ]);
        }

        // Achievement = realisasi -> langsung tersimpan; field rencana indikator
        // tetap lewat change request saat project berjalan. Selama usulan rencana
        // masih ditahan, % Improvement dihitung dari baseline & type yang MASIH
        // berlaku — bukan dari usulan yang belum disetujui.
        $ditahan  = $this->stagesProposalChanges($project, $request->user());
        $baseline = $ditahan ? $indicator->baseline : ($data['baseline'] ?? null);
        $type     = $ditahan ? $indicator->type : ($data['type'] ?? null);

        $langsung = [
            'achievement' => $data['achievement'] ?? null,
            'improvement' => ImplementationIndicator::calcImprovement($baseline, $data['achievement'] ?? null, $type),
        ];
        $proposal = [
            'indicator'         => $data['indicator'],
            'description'       => $data['description'] ?? null,
            'baseline'          => $data['baseline'] ?? null,
            'achievement_value' => $data['achievement_value'] ?? null,
            'uom'               => $data['uom'] ?? null,
            'weightage'         => $data['weightage'] ?? null,
            'type'              => $data['type'] ?? null,
        ];

        $distage = $this->writeOrStage($request, $project, $indicator, $langsung, $proposal);

        return $this->backToSection($project, 'section-indicators',
            $this->pesanSimpan('Indicator updated.', $distage),
            $project->isInExecution() ? ['phase' => 'implementation'] : []);
    }

    /**
     * Input ACHIEVEMENT (aktual) indikator saat project berjalan — boleh semua
     * anggota tim. % Improvement dihitung ulang otomatis dari baseline & type.
     */
    public function updateIndicatorAchievement(Request $request, Project $project, ImplementationIndicator $indicator)
    {
        $this->authorizeTeamExecution($request, $project);
        abort_unless($indicator->project_id === $project->id, 404);

        $data = $request->validate([
            'achievement' => ['nullable', 'numeric'],
        ]);

        // Achievement = realisasi, bukan field rencana -> langsung tersimpan.
        $distage = $this->writeOrStage($request, $project, $indicator, [
            'achievement' => $data['achievement'] ?? null,
            'improvement' => ImplementationIndicator::calcImprovement($indicator->baseline, $data['achievement'] ?? null, $indicator->type),
        ], []);

        return $this->backToSection($project, 'section-indicators',
            $this->pesanSimpan('Indicator achievement updated.', $distage), ['phase' => 'implementation']);
    }

    public function destroyIndicator(Request $request, Project $project, ImplementationIndicator $indicator)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($indicator->project_id === $project->id, 404);
        $indicator->delete();

        return $this->backToSection($project, 'section-indicators', 'Indicator removed.');
    }

    public function storeBudget(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        $request->validate([
            'rows'              => ['required', 'array', 'min:1'],
            'rows.*.item'       => ['required', 'string', 'max:500'],
            'rows.*.qty'        => ['nullable', 'numeric', 'min:0'],
            'rows.*.uom'        => ['nullable', Rule::in(ImplementationIndicator::uoms())],
            'rows.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach (array_values($request->input('rows')) as $r) {
            $project->budgets()->create([
                'item'       => $r['item'],
                'qty'        => $r['qty'] ?? null,
                'uom'        => $r['uom'] ?? null,
                'unit_price' => $r['unit_price'] ?? null,
            ]);
        }

        return $this->backToSection($project, 'section-budget', 'Budget added.');
    }

    public function updateBudget(Request $request, Project $project, ProjectBudget $budget)
    {
        $mayEditProposal = $this->authorizeRowEdit($request, $project);
        abort_unless($budget->project_id === $project->id, 404);

        // Member hanya boleh mengisi Actual Qty & Actual Price.
        if (! $mayEditProposal) {
            $data = $request->validate([
                'actual_qty'   => ['nullable', 'numeric'],
                'actual_price' => ['nullable', 'numeric'],
            ]);
            $cost = (isset($data['actual_qty']) && isset($data['actual_price']))
                ? (float) $data['actual_qty'] * (float) $data['actual_price']
                : null;

            // Actual Qty/Price = realisasi -> langsung tersimpan, tidak ditahan
            // sebagai Budget Change (sama seperti bila Leader yang mengisinya).
            $distage = $this->writeOrStage($request, $project, $budget, $data + ['actual_cost' => $cost], []);

            $this->storeBudgetAttachments($request, $project, $budget);

            return $this->backToSection($project, 'section-budget',
                $this->pesanSimpan('Actual budget updated.', $distage), ['phase' => 'implementation']);
        }

        $data = $request->validate([
            'item'       => ['required', 'string', 'max:500'],
            'qty'        => ['nullable', 'numeric', 'min:0'],
            'uom'        => ['nullable', Rule::in(ImplementationIndicator::uoms())],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            // Dialog Edit kini memuat Actual juga; Leader boleh mengisi keduanya.
            'actual_qty'   => ['nullable', 'numeric'],
            'actual_price' => ['nullable', 'numeric'],
        ]);

        $cost = (isset($data['actual_qty']) && isset($data['actual_price']))
            ? (float) $data['actual_qty'] * (float) $data['actual_price']
            : null;

        // Actual Qty/Price/Cost = realisasi -> langsung tersimpan. Field budget
        // rencana tetap lewat change request "Budget Change" saat project berjalan.
        $langsung = [
            'actual_qty'   => $data['actual_qty'] ?? null,
            'actual_price' => $data['actual_price'] ?? null,
            'actual_cost'  => $cost,
        ];
        $proposal = [
            'item'       => $data['item'],
            'qty'        => $data['qty'] ?? null,
            'uom'        => $data['uom'] ?? null,
            'unit_price' => $data['unit_price'] ?? null,
        ];

        $distage = $this->writeOrStage($request, $project, $budget, $langsung, $proposal);

        $this->storeBudgetAttachments($request, $project, $budget);

        return $this->backToSection($project, 'section-budget',
            $this->pesanSimpan('Budget updated.', $distage),
            $project->isInExecution() ? ['phase' => 'implementation'] : []);
    }

    public function destroyBudget(Request $request, Project $project, ProjectBudget $budget)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($budget->project_id === $project->id, 404);
        $budget->delete();

        return $this->backToSection($project, 'section-budget', 'Budget item removed.');
    }

    /** Actual budget tracking (T-61) — saat project berjalan. */
    public function updateBudgetActual(Request $request, Project $project, ProjectBudget $budget)
    {
        $this->authorizeTeamExecution($request, $project);
        abort_unless($budget->project_id === $project->id, 404);

        $data = $request->validate([
            'actual_qty'   => ['nullable', 'numeric'],
            'actual_price' => ['nullable', 'numeric'],
        ]);

        $cost = (isset($data['actual_qty']) && isset($data['actual_price']))
            ? (float) $data['actual_qty'] * (float) $data['actual_price']
            : null;

        $this->withRecordLock($request, $budget, function ($budget) use ($data, $cost) {
            $budget->update($data + ['actual_cost' => $cost]);
        });

        return $this->backToSection($project, 'section-budget', 'Actual budget updated.', ['phase' => 'implementation']);
    }

    public function storeMember(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        // Multi-add: rows[] berisi {user_id, role}. users ada di hcis (kpncorp).
        // Slot role wajib boleh diisi bertahap → baris tanpa nama dilewati
        // (indeks bisa berlubang karena baris kosong tidak dikirim dari form).
        $request->merge(['rows' => collect($request->input('rows', []))
            ->filter(fn ($r) => filled($r['user_id'] ?? null) && filled($r['role'] ?? null))
            ->values()->all()]);

        // Error ditaruh di bag "member" + flag session supaya dialog Add Member terbuka
        // kembali dan menampilkan alasannya — bukan kembali diam-diam ke halaman detail.
        $validator = Validator::make($request->all(), [
            'rows'           => ['required', 'array', 'min:1'],
            'rows.*.user_id' => ['required', 'integer', 'exists:kpncorp.users,id'],
            'rows.*.role'    => ['required', 'string', 'max:100'],
        ], [
            'rows.required'         => 'Select at least one name before saving.',
            'rows.*.user_id.exists' => 'The selected name was not found in the employee directory.',
        ]);

        if ($validator->fails()) {
            return $this->backToMemberDialog($project, $validator->errors()->all());
        }

        $rows = $request->input('rows');

        // T-86: batas jumlah anggota tim per kategori (bila diatur).
        $max = optional($project->category)->max_team_members;
        if ($max && $project->members()->count() + count($rows) > $max) {
            return $this->backToMemberDialog($project, [
                "Team size limit for this category is {$max}; currently {$project->members()->count()} member(s).",
            ]);
        }

        foreach ($rows as $r) {
            $project->members()->create([
                'user_id'   => $r['user_id'],
                'role'      => $r['role'],
                'joined_at' => now()->toDateString(),
                'is_active' => true,
            ]);
        }

        return $this->backToSection($project, 'section-team', count($rows) . ' team member(s) added.');
    }

    /** Kembali ke detail project dengan dialog Add Member terbuka + daftar alasan penolakan. */
    private function backToMemberDialog(Project $project, array $messages)
    {
        return redirect()
            ->to(route('projects.show', $project) . '#section-team')
            ->withErrors(['rows' => $messages], 'member')
            ->with('memberDialogOpen', true);
    }

    public function updateMember(Request $request, Project $project, ProjectMember $member)
    {
        // Leader boleh mengubah anggota saat proposal MAUPUN saat project berjalan;
        // pada fase berjalan hasilnya masuk change request "Team Change", bukan
        // langsung berlaku. authorizeLeader() menolak fase berjalan, jadi tidak dipakai.
        abort_unless($this->leaderMayEditProposalFields($project, $request->user()), 403,
            'Only the Project Leader may change team members.');
        abort_unless($member->project_id === $project->id, 404);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:kpncorp.users,id'],
            'role'    => ['required', 'string', 'max:100'],
        ]);

        $distage = $this->writeOrStage($request, $project, $member, [],
            ['user_id' => $data['user_id'], 'role' => $data['role']]);

        return $this->backToSection($project, 'section-team',
            $this->pesanSimpan('Team member updated.', $distage),
            $project->isInExecution() ? ['phase' => 'implementation'] : []);
    }

    public function destroyMember(Request $request, Project $project, ProjectMember $member)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($member->project_id === $project->id, 404);
        $member->delete();

        return $this->backToSection($project, 'section-team', 'Team member removed.');
    }

    /**
     * Simpan sebagai draft — proposal memang tersimpan otomatis saat edit inline;
     * tombol ini sekadar konfirmasi + kembali ke daftar dengan notifikasi.
     */
    public function saveDraft(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        return redirect()->route('projects.index')->with('success', 'Project Proposal saved as draft.');
    }

    /* ---- Attachments (T-100) ----------------------------------------- */

    public function storeAttachment(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($project->isTeamMember($user) || $user->hasRole('Super Admin'), 403,
            'Only project team members may upload attachments.');

        // Multi-file (seperti Create Idea): terima attachments[] atau fallback file tunggal.
        $request->validate([
            'attachments'   => ['required_without:file', 'array'],
            'attachments.*' => $this->attachmentRules(),
            'file'          => array_merge(['required_without:attachments'], $this->attachmentRules()),
        ]);

        $files = $request->file('attachments') ?: array_filter([$request->file('file')]);
        foreach ($files as $file) {
            $project->attachments()->create(
                $this->storeAttachmentFile($file, "project-attachments/{$project->id}", $user->id)
            );
        }

        return $this->backToSection($project, 'section-attachments', count($files) . ' attachment(s) uploaded.');
    }

    public function downloadAttachment(Request $request, Project $project, ProjectAttachment $attachment)
    {
        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless(
            Project::relatedTo($request->user()->id)->whereKey($project->id)->exists()
                || $request->user()->hasRole('Super Admin'),
            403
        );

        return $this->downloadAttachmentFile($attachment->file_path, $attachment->file_name);
    }

    /** Buka lampiran inline (PDF/gambar) di tab baru — sama seperti Idea. */
    public function viewAttachment(Request $request, Project $project, ProjectAttachment $attachment)
    {
        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless(
            Project::relatedTo($request->user()->id)->whereKey($project->id)->exists()
                || $request->user()->hasRole('Super Admin'),
            403
        );

        return $this->viewAttachmentFile($attachment->file_path, $attachment->file_name);
    }

    public function destroyAttachment(Request $request, Project $project, ProjectAttachment $attachment)
    {
        abort_unless($attachment->project_id === $project->id, 404);
        $user = $request->user();
        abort_unless(
            $attachment->uploaded_by === $user->id
                || $project->project_leader_id === $user->id
                || $user->hasRole('Super Admin'),
            403,
            'Only the uploader or the Project Leader may delete attachments.'
        );

        $this->deleteAttachmentFile($attachment->file_path);
        $attachment->delete();

        return $this->backToSection($project, 'section-attachments', 'Attachment deleted.');
    }

    private function authorizeLeader(Request $request, Project $project): void
    {
        abort_unless(
            $project->canLeaderEditProposal($request->user()),
            403,
            'Only the Project Leader may make changes (during draft/revision, or when an update has been approved).'
        );
    }

    /* ----------------------------------------------------------------- */

    private function authorizeShellCreator(User $user, Idea $idea): void
    {
        abort_unless($idea->status === 'approved', 403, 'Idea is not approved yet.');

        // TIDAK ada pembebasan Super Admin: membuat shell tetap hak committee
        // layer terakhir. Di menu Project Shell (yang memperlihatkan semua ide
        // approved ke Super Admin) tombolnya diganti dialog penjelasan lewat
        // flag can_create_shell — lihat shellIndex().
        $isLastLayer = app(IdeaWorkflowService::class)->isLastLayerCommittee($idea, $user);

        abort_unless($isLastLayer, 403, 'Only the last committee layer may create a project.');
    }

    /**
     * Override kode BU untuk nama tertentu (sama seperti template Idea ID).
     * mis. "KPN Corporation" → CORP.
     */
    private const BU_CODE_MAP = [
        'KPN Corporation' => 'CORP',
        'Cement'          => 'CEME',
        'Downstream'      => 'DOWN',
        'KPN Sugar'       => 'SUGA',
        'Plantations'     => 'PLANT',
        'Property'        => 'PROP',
    ];

    /** Format: P-{BU}-YYYYMMDD-{CAT}-00000. BU dari nama BU ide terkait (via BU_CODE_MAP). */
    private function generateProjectId(Idea $idea, string $category): string
    {
        $buCode = $this->buCodeFromName($idea->business_unit_name);
        $date   = now()->format('Ymd');
        $seq    = Project::whereDate('created_at', now()->toDateString())->count() + 1;

        return sprintf('P-%s-%s-%s-%05d', $buCode, $date, $category, $seq);
    }

    /**
     * Singkatan kode BU dari namanya (identik dengan template Idea ID):
     *  - override BU_CODE_MAP bila terdaftar (mis. "KPN Corporation" → CORP),
     *  - inisial tiap kata bila ≥ 3 huruf, selain itu 3 huruf pertama.
     */
    private function buCodeFromName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'GEN';
        }

        foreach (self::BU_CODE_MAP as $key => $code) {
            if (strcasecmp($key, $name) === 0) {
                return $code;
            }
        }

        $words    = preg_split('/\s+/', preg_replace('/[^A-Za-z\s]/', '', $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = strtoupper(implode('', array_map(fn ($w) => $w[0], $words)));
        if (strlen($initials) >= 3) {
            return substr($initials, 0, 6);
        }

        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $name));

        return substr($letters, 0, 3) ?: 'GEN';
    }
}
