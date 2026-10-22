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
use App\Services\Dashboard\MetricScopeService;
use App\Support\Navigation\InertiaRoutes;
use App\Support\ReportOverride;
use App\Services\Project\ProjectUpdateService;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\KpnBusinessUnit;
use App\Models\KpnEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

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

        // Label Leader/Sponsor yang sudah terpilih di draft — dropdown-nya mencari
        // ke server, jadi label itu harus ikut dikirim agar pilihan tetap terbaca
        // tanpa menunggu pencarian.
        $preselect = KpnEmployee::query()
            ->whereIn('employee_id', array_filter([$draftLeader, $draftSponsor]))
            ->get()
            ->keyBy('employee_id');

        return Inertia::render('Projects/Create', [
            'idea' => [
                'idea_id' => $idea->idea_id,
                'idea_name' => $idea->idea_name,
            ],

            // Draft belum terlihat Leader/Sponsor sampai ditekan Create Project.
            'isDraft' => $draft !== null,

            'values' => [
                'idea_id' => $idea->idea_id,
                'project_name' => $draft->project_name ?? $idea->idea_name ?? '',
                'project_category_id' => $draft->project_category_id ?? null,
                'project_sponsor_id' => $draftSponsor,
                'project_leader_id' => $draftLeader,
                'project_scope' => $draft->project_scope ?? '',
                'expected_outcome' => $draft->expected_outcome ?? $idea->expected_outcome ?? '',
                'notes' => $draft->notes ?? '',
            ],

            'labels' => [
                'sponsor' => $draftSponsor ? $preselect[$draftSponsor]?->label() : null,
                'leader' => $draftLeader ? $preselect[$draftLeader]?->label() : null,
            ],

            'categories' => ProjectCategory::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (ProjectCategory $c) => [
                    'value' => $c->id,
                    'label' => "{$c->name} ({$c->code})",
                ])
                ->values(),

            'searchUrl' => route('org.employees'),
            'backUrl' => route('ideas.taskbox', ['tab' => 'approved']),
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
                ->with('success', __('flash.projects.shell_draft_saved'));
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
            ->with('success', __('flash.projects.shell_created', ['id' => $project->project_id]));
    }

    /**
     * Hapus draft Project Shell. Hanya draft yang boleh dihapus — shell yang
     * sudah dibuat dibatalkan lewat Cancel Project, bukan delete.
     */
    public function destroyShellDraft(Request $request, string $shell)
    {
        $user  = $request->user();
        $draft = Project::onlyShellDrafts()->findOrFail($shell);

        // Di luar Report, Super Admin tunduk aturan yang sama dgn user lain.
        if (! ReportOverride::aktif($request)) {
            $idea = Idea::where('idea_id', $draft->idea_id)->firstOrFail();
            $this->authorizeShellCreator($user, $idea);
        }

        $draft->delete();

        return redirect()
            ->route('projects.shell')
            ->with('success', __('flash.projects.shell_draft_deleted'));
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
            // Kunci terjemahan untuk judul/subjudul halaman (resources/js/locales).
            'key'           => 'proposal',
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
            'key'           => 'implementation',
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
            'key'           => 'completion',
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

        // Dibuka dari kartu Dashboard: status dibatasi ke definisi metrik, dan
        // batasan fase halaman ini dilewati — satu metrik bisa mencakup beberapa
        // fase sekaligus, sehingga jumlah barisnya tetap sama dgn angka kartunya.
        $metric = $request->get('metric');
        $metric = (MetricScopeService::exists($metric) && MetricScopeService::kindOf($metric) === 'project')
            ? $metric
            : null;

        if ($metric) {
            // Basis diganti seluruhnya dengan definisi metrik Dashboard. Tidak cukup
            // hanya menyaring status: relatedTo() di atas jauh lebih luas (ikut
            // menghitung keanggotaan tim & committee) sedangkan kartu Dashboard
            // hanya menghitung pengaju ide / Leader / Sponsor — jumlahnya akan beda.
            $base = app(MetricScopeService::class)->query(
                $metric,
                $request->user(),
                true,       // halaman ini memang khusus data milik user sendiri
                ['bu' => null, 'unit' => null, 'from' => null, 'to' => null]
            );
        }

        // Batasi ke status fase ini (Implementation/Completion). Proposal: null = semua.
        if (! $metric && ! empty($opts['phaseStatuses'])) {
            $base->whereIn('status', $opts['phaseStatuses']);
        }

        // Kebalikannya: buang status yang bukan urusan fase ini. Dipakai Proposal
        // agar status fase eksekusi/penyelesaian tidak ikut nongol. Memakai daftar
        // KECUALI (bukan daftar izin) supaya status baru tetap muncul secara default.
        if (! $metric && ! empty($opts['excludeStatuses'])) {
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

        $sortState = $this->listSortState($request, $config);
        $phase = $opts['phase'] ?? null;
        $detailRoute = $opts['detailRoute'] ?? 'projects.show';
        $user = $request->user();

        return Inertia::render('Projects/List', [
            'projects' => $query->paginate($perPage)->withQueryString()->through(
                function (Project $project) use ($detailRoute, $phase, $user) {
                    $params = $phase
                        ? ['project' => $project, 'phase' => $phase]
                        : ['project' => $project];

                    return [
                        'id' => $project->id,
                        'project_id' => $project->project_id,
                        'project_name' => $project->project_name,
                        'project_category' => $project->project_category,
                        'status' => $project->status,
                        'status_label' => $project->statusBadge()[0],
                        'leader' => $project->leader ? [
                            'name' => $project->leader->name,
                            'employee_id' => $project->leader->employee_id,
                        ] : null,
                        'sponsor' => $project->sponsor ? [
                            'name' => $project->sponsor->name,
                            'employee_id' => $project->sponsor->employee_id,
                        ] : null,

                        // Pensil hanya muncul bila user memang boleh mengubah pada
                        // fase ini; Project Shell read-only jadi tidak pernah.
                        'can_edit' => $detailRoute === 'projects.show'
                            && $project->isEditableInPhase($user, $phase),

                        'urls' => [
                            'edit' => route($detailRoute, $params),
                            'view' => route($detailRoute, $params + ['view' => 1]),
                        ],
                    ];
                }
            ),

            // Detail project masih halaman Blade — tautannya tidak boleh berupa
            // kunjungan Inertia. Lihat App\Support\Navigation\InertiaRoutes.
            'detailIsExternal' => ! InertiaRoutes::has($detailRoute),

            'filters' => [
                'q' => $request->get('q'),
                'bu' => $request->get('bu'),
                'unit' => $request->get('unit'),
                'tab' => $tab,
                'per_page' => $perPage,
                'sort' => $sortState['sort'],
                'dir' => $sortState['dir'],
            ],

            // Business Unit dari master_bisnisunits; Unit di-cascade saat dibutuhkan.
            'businessUnits' => KpnBusinessUnit::names()->values(),

            // Angka tab memetakan kunci tab -> status DB lewat statusMap.
            'tabs' => collect($opts['tabDefs'])->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'count' => $key === 'all'
                    ? (int) $counts->sum()
                    : (int) $counts->get($statusMap[$key] ?? '', 0),
            ])->values(),

            'page' => [
                'key' => $opts['key'],
                'title' => $opts['title'],
                'subtitle' => $opts['subtitle'],
                'url' => route($opts['route']),
                'createHint' => $opts['createHint'] ?? null,
            ],

            // Konteks kartu Dashboard (bila halaman ini dibuka dari sana).
            'metric' => $metric,
            'metricLabel' => $metric ? MetricScopeService::labelOf($metric) : null,
        ]);
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

        if (! ReportOverride::aktif($request)) {
            $base->whereIn('ideas.idea_id', $this->lastLayerCommitteeIdeaIds($user));
        }

        $config = [
            // Search ditangani manual di bawah: entri ber-titik pada 'searchable'
            // diartikan HasListQuery sebagai relasi, sedangkan di sini kolomnya
            // WAJIB berkualifikasi tabel (idea_id ada di kedua tabel).
            'searchable'   => [],
            'sortable'     => [
                'idea_id'          => 'ideas.idea_id',
                'project_id'       => 'ps.project_id',
                'project_name'     => 'ps.project_name',
                'project_category' => 'ps.project_category',
                // Status shell bukan kolom: ia diturunkan dari hasil JOIN, sama
                // persis dengan Project::shellStatusFor() dan dengan filter tab
                // di bawah. Ekspresi ini whitelist milik kode, bukan masukan
                // pengguna — yang dari request hanyalah kunci 'shell_status'.
                'shell_status'     => new Expression(
                    "case when ps.id is null then 'not_assigned'"
                    ." when ps.is_shell_draft = 1 then 'draft'"
                    ." when ps.status = 'cancelled' then 'cancelled'"
                    ." else 'assigned' end"
                ),
                'created_at'       => 'ideas.created_at',
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
        // Cocokkan dengan Project::shellStatusFor(): project yang dibatalkan
        // keluar dari Assigned dan pindah ke tab Cancelled, sehingga jumlah
        // seluruh tab tetap sama dengan tab All.
        $filters = [
            'draft'        => fn ($q) => $q->where('ps.is_shell_draft', true),
            'assigned'     => fn ($q) => $q->where('ps.is_shell_draft', false)
                ->where('ps.status', '!=', 'cancelled'),
            'not_assigned' => fn ($q) => $q->whereNull('ps.id'),
            'cancelled'    => fn ($q) => $q->where('ps.is_shell_draft', false)
                ->where('ps.status', 'cancelled'),
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
        $isAdminView = ReportOverride::aktif($request);
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

        $sortState = $this->listSortState($request, $config);

        return Inertia::render('Projects/ShellList', [
            'rows' => $rows->through(function (Idea $row) use ($shells) {
                // Satu baris = satu (ide, shell). shell null → ide belum dibuatkan shell.
                $shell = $row->shell_pk ? $shells->get($row->shell_pk) : null;
                $shellStatus = Project::shellStatusFor($shell);

                return [
                    'idea_id' => $row->idea_id,
                    'idea_name' => $row->idea_name,
                    'shell_id' => $shell?->id,
                    'project_id' => $shell?->project_id,
                    // Belum ada shell (atau draft tanpa nama): pakai nama ide.
                    'project_name' => $shell?->project_name ?: $row->idea_name,
                    'project_category' => $shell?->project_category,
                    'leader' => $shell?->leader ? [
                        'name' => $shell->leader->name,
                        'employee_id' => $shell->leader->employee_id,
                    ] : null,
                    'sponsor' => $shell?->sponsor ? [
                        'name' => $shell->sponsor->name,
                        'employee_id' => $shell->sponsor->employee_id,
                    ] : null,
                    'shell_status' => $shellStatus,
                    'shell_status_label' => Project::shellStatusBadge($shellStatus)[0],

                    // Hanya committee layer terakhir ide yang boleh membuat /
                    // melanjutkan shell. Baris yang tidak boleh tetap menampilkan
                    // tombolnya, lalu menjelaskan lewat dialog — bukan 403.
                    'can_create_shell' => (bool) $row->can_create_shell,

                    'urls' => [
                        'create' => route('projects.create', ['idea_id' => $row->idea_id]),
                        'progress' => $shell ? route('projects.shell.progress', $shell) : null,
                        'destroy_draft' => $shell ? route('projects.shell.draft.destroy', $shell->id) : null,
                    ],

                    // Halaman create & progress masih Blade. Lihat InertiaRoutes.
                    'external' => [
                        'create' => ! InertiaRoutes::has('projects.create'),
                        'progress' => ! InertiaRoutes::has('projects.shell.progress'),
                    ],
                ];
            }),

            'filters' => [
                'q' => $request->get('q'),
                'bu' => $request->get('bu'),
                'unit' => $request->get('unit'),
                'tab' => $tab,
                'per_page' => $perPage,
                'sort' => $sortState['sort'],
                'dir' => $sortState['dir'],
            ],

            'businessUnits' => KpnBusinessUnit::names()->values(),

            'tabs' => collect([
                'all' => 'All',
                'draft' => 'Draft',
                'assigned' => 'Assigned',
                'not_assigned' => 'Not Assigned',
                'cancelled' => 'Cancelled',
            ])->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'count' => $key === 'all' ? (int) $counts->sum() : (int) ($counts[$key] ?? 0),
            ])->values(),
        ]);
    }

    /** Halaman progress sebuah Project Shell: Progress Summary + Activities. */
    public function shellProgress(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canSeeShell($project, $user), 403,
            __('messages.abort.no_shell_access'));

        $project->load([
            'category', 'idea.businessUnit', 'idea.department', 'sponsor', 'leader', 'members.user',
            'implementationPlans', 'indicators',
            'statusLogs.changedBy', 'updates.requester', 'updates.reviewer',
        ]);

        $summary = $this->shellSummary($project);
        $cancellation = $project->status === 'cancelled' ? $project->cancellation() : null;
        $approver = app(ProjectChangeStagingService::class)->ideaCommitteeApprover($project);

        return Inertia::render('Projects/ShellProgress', [
            'project' => [
                'id' => $project->id,
                'project_id' => $project->project_id,
                'project_name' => $project->project_name,
                'project_category' => $project->project_category,
                'project_scope' => $project->project_scope,
                'expected_outcome' => $project->expected_outcome,
                'status' => $project->status,
                'status_label' => $project->statusBadge()[0],
                'created_at' => $project->created_at?->toIso8601ZuluString(),

                'leader_id' => $project->project_leader_id,
                'leader_name' => $project->leader?->name,
                'sponsor_id' => $project->project_sponsor_id,
                'sponsor_name' => $project->sponsor?->name,

                'idea_id' => $project->idea?->idea_id,
                // Snapshot nama dipakai lebih dulu, relasi jadi cadangan.
                'business_unit_name' => $project->idea?->business_unit_name
                    ?: $project->idea?->businessUnit?->name,
                'department_name' => $project->idea?->department_name
                    ?: $project->idea?->department?->name,
            ],

            'summary' => [
                'planTotal' => $summary['planTotal'],
                'planDone' => $summary['planDone'],
                'planPercent' => $summary['planPercent'],
                'memberCount' => $summary['memberCount'],
                'weightTotal' => (float) $summary['weightTotal'],
                'weightDone' => (float) $summary['weightDone'],
                'indicatorPercent' => $summary['indicatorPercent'],
                'lastActivity' => $summary['lastActivity']?->toIso8601ZuluString(),
            ],

            'activities' => $this->activityPayload($this->shellActivities($project)),

            'cancellation' => $cancellation ? [
                'reason' => $project->cancellationReason(),
                'by' => $cancellation->changedBy?->name,
                'at' => $cancellation->created_at?->toIso8601ZuluString(),
            ] : null,

            'canCancel' => $this->canCancel($project, $user)
                && ! in_array($project->status, ['completed', 'cancelled'], true),

            // Hak ubah Sponsor/Leader langsung dari halaman ini (committee layer
            // terakhir ide memakai menu Project Shell, bukan Project Detail).
            'leadershipRights' => $this->leadershipRights($project, $user),
            'ideaApprover' => $approver?->name,

            'urls' => [
                'back' => route('projects.shell'),
                'detail' => route('projects.show', $project),
                'leadership' => route('projects.leadership.update', $project),
                'cancel' => route('projects.cancel', $project),
                'userSearch' => route('org.users'),
            ],

            // Detail project masih halaman Blade. Lihat InertiaRoutes.
            'detailIsExternal' => ! InertiaRoutes::has('projects.show'),
        ]);
    }

    /** Linimasa aktivitas dalam bentuk siap render. */
    private function activityPayload(\Illuminate\Support\Collection $activities): \Illuminate\Support\Collection
    {
        return $activities->map(fn (array $item) => [
            'at' => $item['at']?->toIso8601ZuluString(),
            'kind' => $item['kind'],
            'title' => $item['title'],
            'note' => $item['note'],
            'actor' => $item['actor'],
        ])->values();
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
        if (ReportOverride::aktif()
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
        // Terkait langsung dengan project, ATAU datang LEWAT MENU REPORT. Memiliki
        // izin report.view saja tidak cukup: di luar Report, Super Admin diperlakukan
        // seperti employee biasa. Yang datang lewat Report dipaksa mode lihat-saja di
        // bawah, kecuali ia memang datang untuk bertindak (act=1).
        $lewatReport = ! Project::relatedTo($request->user()->id)->whereKey($project->id)->exists()
            && ReportOverride::aktif($request);

        abort_unless(
            $lewatReport || Project::relatedTo($request->user()->id)->whereKey($project->id)->exists(),
            403
        );

        $project->load([
            'category',
            'idea.businessUnit', 'idea.department', 'idea.user',
            'sponsor', 'leader', 'members.user', 'budgets',
            'implementationPlans', 'indicators', 'statusLogs.changedBy', 'attachments.uploader',
            'updates.requester', 'updates.reviewer', 'approvals.user', 'approvals.onBehalfOf',
        ]);

        $user = $request->user();

        // Permintaan perubahan pada project ini yang menunggu keputusan user.
        // Dihitung lebih awal karena ikut menentukan tampilnya kolom Actual.
        // Termasuk permintaan yang diputus mewakili penilai lain (override.role),
        // sehingga tombol pensil di Report benar-benar membuka panel keputusannya.
        $changeReviews = app(ProjectChangeStagingService::class)->decidableOn($project, $user);

        // Field "Actual" ditentukan oleh FASE PROJECT, bukan oleh pintu masuk:
        // selama proposal belum disetujui Actual disembunyikan, dan begitu proposal
        // di-approve ia tampil di mana pun detail dibuka (My Project, Task Box,
        // Report, dsb). Patokan ini stabil — tidak berubah mengikuti peran pembuka
        // halaman maupun ada-tidaknya parameter di URL.
        $isImplementationView = $request->query('phase') === 'implementation';
        $showActual = $project->proposalApproved();

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
            // Pengunjung dari Report umumnya read-only. Kecuali admin yang memang
            // datang untuk bertindak (act=1 + izin override.role) — ia perlu menyunting
            // Sponsor/Leader & Team Members serta memutus approval.
            'viewOnly'           => $request->boolean('view')
                || ($lewatReport && ! ($request->boolean('act') && $user->can('override.role'))),
            'isLeader'           => $project->project_leader_id === $user->id,
            'isSponsor'          => $project->project_sponsor_id === $user->id,
            'isReviewer'         => app(ProjectApprovalWorkflowService::class)->isCurrentReviewer($project, $user),
            // Super Admin (izin override.role) boleh memutus menggantikan committee
            // layer aktif. NULL bila ia memang reviewer-nya sendiri / tak berhak.
            'atasNama'           => $this->atasNamaProject($request, $project),
            // Actual tidak lagi punya baseline terpisah: pengisiannya menjadi change
            // request per section, jadi tidak ada status yang mengunci pengeditan.
            'canSubmitCompletion' => $project->project_leader_id === $user->id && $project->isInExecution(),
            'canRequestUpdate'   => $project->isInExecution() && $project->isTeamMember($user),
            'canCancel'          => $this->canCancel($project, $user) && ! in_array($project->status, ['completed', 'cancelled'], true),
            'reviewableUpdateIds' => $project->updates->filter(fn ($u) => $updateSvc->isReviewer($u, $user))->pluck('id'),
            'updateTypes'        => ProjectUpdate::CHANGE_TYPES,
            'users'              => User::whereIn('id', $picIds)->get(),
            'teamMembers'        => $teamMembers,
            'canUploadAttachment' => $project->isTeamMember($user) || ReportOverride::aktif($request),
            // Dialog Edit per baris: dibuka utk Leader (fase proposal) maupun anggota
            // tim (fase implementation). $proposalReadOnly menandai field asal proposal
            // yang tidak boleh diubah oleh anggota tim non-Leader.
            'canEditRow'       => $this->canEditRow($project, $user),
            // Perubahan proposal yang masih ditahan (belum diajukan approval).
            'pendingDrafts'    => app(ProjectChangeStagingService::class)->drafts($project),
            // Nilai per baris yang menunggu approval — ditampilkan di tabel agar
            // isian user terlihat, walau belum berlaku pada datanya.
            'pendingChanges'   => app(ProjectChangeStagingService::class)->pendingByRow($project),
            // Permintaan perubahan yang menunggu keputusan user ini, lengkap dgn
            // perbandingan before/after tiap field.
            'changeReviews'    => $changeReviews
                ->map(function ($u) use ($user) {
                    $svc = app(ProjectChangeStagingService::class);

                    return [
                        'update' => $u,
                        'label'  => ProjectChangeStagingService::TYPE_LABEL[$u->change_type] ?? $u->change_type,
                        'diff'   => $svc->diff($u),
                        // Nama penilai yang diwakili; null bila ia penilainya sendiri.
                        'atasNama' => $svc->isReviewer($u, $user) ? null : $svc->approverOf($u),
                    ];
                }),
            // Admin (override.role) juga boleh mengirimkan draft perubahan miliknya.
            'canSubmitChanges' => ($project->project_leader_id === $user->id || ReportOverride::aktif($request))
                && $project->isInExecution(),
            'canDeleteRow'     => $project->canLeaderEditProposal($user),
            // Boleh menyunting Team Members & Sponsor/Leader (Leader atau admin).
            'canEditTeam'      => $this->bolehUbahTim($project, $user),
            // Hak ubah Sponsor/Leader + apakah langsung berlaku (lihat leadershipRights).
            'leadershipRights' => $this->leadershipRights($project, $user),
            // Nama penyetuju perubahan Sponsor/Leader; null = permintaan menggantung.
            'ideaApprover'     => app(ProjectChangeStagingService::class)->ideaCommitteeApprover($project),
            'proposalReadOnly' => ! $this->leaderMayEditProposalFields($project, $user),
        ];

        // Mode lihat-saja (tombol mata di daftar My Project): matikan SEMUA
        // kemampuan mengubah, bukan hanya canEdit — supaya halaman benar-benar
        // read-only dan berbeda nyata dari tombol pensil.
        if ($data['viewOnly']) {
            foreach ([
                'canEdit',
                'canSubmitCompletion', 'canRequestUpdate', 'canCancel', 'canUploadAttachment',
                'canEditRow', 'canDeleteRow', 'canSubmitChanges', 'canEditTeam',
                'leadershipRights',
            ] as $flag) {
                // Array hak diganti dengan hak kosong, bukan false, agar view tetap
                // bisa membacanya sebagai array.
                $data[$flag] = $flag === 'leadershipRights'
                    ? ['sponsor' => false, 'leader' => false, 'instant' => false]
                    : false;
            }
            $data['reviewableUpdateIds'] = collect();
        }

        return Inertia::render('Projects/Show', $this->showPayload($request, $project, $data));
    }

    /**
     * Ubah $data hasil show() menjadi payload halaman Vue.
     *
     * Perhitungan hak akses TIDAK diulang di sini — $data sudah melewati
     * penguncian mode lihat-saja di show(), jadi kita hanya memindahkan isinya
     * ke bentuk yang dipakai komponen.
     */
    private function showPayload(Request $request, Project $project, array $data): array
    {
        $idea = $project->idea;
        $usersById = $data['users']->keyBy('id');
        $pending = $data['pendingChanges'];
        $showActual = $data['showActual'];

        // Perubahan Leader yang masih ditahan, kalau ada.
        $leadershipPending = $pending[Project::class . '#' . $project->id] ?? null;
        $leadershipPending = $leadershipPending && array_key_exists('project_leader_id', $leadershipPending['fields'])
            ? $leadershipPending
            : null;

        // Badge status: di tampilan Project Proposal, project yang sudah lewat
        // approval tetap "Approved" — snapshot proposal, bukan progres eksekusi.
        $badgeStatus = $project->status;

        if ($data['phase'] !== 'implementation'
            && in_array($project->status, ['ongoing', 'delayed', 'completion_review', 'completed'], true)) {
            $badgeStatus = 'approved';
        }

        $cancellation = $project->status === 'cancelled' ? $project->cancellation() : null;

        return [
            'project' => [
                'id' => $project->id,
                'project_id' => $project->project_id,
                'project_name' => $project->project_name,
                'project_category' => $project->project_category,
                'project_scope' => $project->project_scope,
                'expected_outcome' => $project->expected_outcome,
                'project_summary' => $project->project_summary,
                'status' => $project->status,
                'badge_status' => $badgeStatus,
                'badge_label' => Project::STATUS_BADGES[$badgeStatus][0]
                    ?? ucwords(str_replace('_', ' ', $badgeStatus)),
                'current_layer' => $project->current_layer,
                'created_at' => $project->created_at?->toIso8601ZuluString(),
                'leader_id' => $project->project_leader_id,
                'leader_name' => $project->leader?->name,
                'sponsor_id' => $project->project_sponsor_id,
                'sponsor_name' => $project->sponsor?->name,
                'category_code' => $project->category?->code,
            ],

            'idea' => $idea ? [
                'idea_id' => $idea->idea_id,
                'idea_name' => $idea->idea_name,
                'problem' => $idea->problem,
                'submitter' => $idea->user?->name,
                // Snapshot nama dipakai lebih dulu, relasi jadi cadangan.
                'business_unit_name' => $idea->business_unit_name ?: $idea->businessUnit?->name,
                'department_name' => $idea->department_name ?: $idea->department?->name,
            ] : null,

            'abilities' => [
                'canEdit' => (bool) $data['canEdit'],
                'viewOnly' => (bool) $data['viewOnly'],
                'isLeader' => (bool) $data['isLeader'],
                'isSponsor' => (bool) $data['isSponsor'],
                'isReviewer' => (bool) $data['isReviewer'],
                'canSubmitCompletion' => (bool) $data['canSubmitCompletion'],
                'canCancel' => (bool) $data['canCancel'],
                'canUploadAttachment' => (bool) $data['canUploadAttachment'],
                'canEditRow' => (bool) $data['canEditRow'],
                'canDeleteRow' => (bool) $data['canDeleteRow'],
                'canSubmitChanges' => (bool) $data['canSubmitChanges'],
                'canEditTeam' => (bool) $data['canEditTeam'],
                'proposalReadOnly' => (bool) $data['proposalReadOnly'],
            ],

            'showActual' => $showActual,

            'members' => $project->members->map(fn (ProjectMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'role' => $m->role,
                'version' => \App\Support\RecordVersion::of($m),
                'urls' => [
                    'update' => route('projects.members.update', [$project, $m]),
                    'destroy' => route('projects.members.destroy', [$project, $m]),
                ],
            ])->values(),

            // Slot role yang masih kurang untuk kategori project ini.
            'roleSlots' => collect($project->category?->requiredRoles() ?? [])
                ->map(function (array $required) use ($project) {
                    $filled = $project->members
                        ->filter(fn (ProjectMember $m) => mb_strtolower(trim((string) $m->role)) === mb_strtolower($required['role']))
                        ->count();

                    return ['role' => $required['role'], 'remaining' => max(0, $required['total'] - $filled)];
                })
                ->filter(fn (array $slot) => $slot['remaining'] > 0)
                ->values(),

            'plans' => $project->implementationPlans->map(fn (ImplementationPlan $plan) => [
                'id' => $plan->id,
                'activity' => $plan->activity,
                'planning_start' => $plan->planning_start?->format('Y-m-d'),
                'planning_end' => $plan->planning_end?->format('Y-m-d'),
                'actual_start' => $plan->actual_start?->format('Y-m-d'),
                'actual_end' => $plan->actual_end?->format('Y-m-d'),
                'actual_days' => $plan->actual_days,
                'pic_user_ids' => array_values((array) $plan->pic_user_ids),
                'pic_names' => collect($plan->pic_user_ids)
                    ->map(fn ($id) => $usersById[$id]?->name)
                    ->filter()
                    ->implode(', '),
                'remarks' => $plan->remarks,
                'status_label' => $plan->status_label,
                'attachments' => $plan->attachments->map(fn ($att) => [
                    'id' => $att->id,
                    'file_name' => $att->file_name,
                    'view_url' => route('projects.implementation.attachments.view', [$project, $plan, $att]),
                    'destroy_url' => route('projects.implementation.attachments.destroy', [$project, $plan, $att]),
                ])->values(),
                'pending' => $pending[ImplementationPlan::class . '#' . $plan->id] ?? null,
                'version' => \App\Support\RecordVersion::of($plan),
                'urls' => [
                    'update' => route('projects.implementation.update', [$project, $plan]),
                    'destroy' => route('projects.implementation.destroy', [$project, $plan]),
                ],
            ])->values(),

            'indicators' => $project->indicators->map(fn (ImplementationIndicator $ind) => [
                'id' => $ind->id,
                'indicator' => $ind->indicator,
                'description' => $ind->description,
                'baseline' => $ind->baseline,
                'achievement_value' => $ind->achievement_value,
                'achievement' => $ind->achievement,
                'uom' => $ind->uom,
                'weightage' => $ind->weightage,
                'type' => $ind->type,
                'improvement' => $ind->improvement,
                'version' => \App\Support\RecordVersion::of($ind),
                'urls' => [
                    'update' => route('projects.indicators.update', [$project, $ind]),
                    'destroy' => route('projects.indicators.destroy', [$project, $ind]),
                ],
            ])->values(),

            'budgets' => $project->budgets->map(fn (ProjectBudget $b) => [
                'id' => $b->id,
                'item' => $b->item,
                'qty' => $b->qty,
                'uom' => $b->uom,
                'unit_price' => $b->unit_price,
                'planned_total' => (float) $b->planned_total,
                'actual_qty' => $b->actual_qty,
                'actual_price' => $b->actual_price,
                'actual_cost' => $b->actual_cost === null ? null : (float) $b->actual_cost,
                'attachments' => $b->attachments->map(fn ($att) => [
                    'id' => $att->id,
                    'file_name' => $att->file_name,
                    'view_url' => route('projects.budgets.attachments.view', [$project, $b, $att]),
                    'destroy_url' => route('projects.budgets.attachments.destroy', [$project, $b, $att]),
                ])->values(),
                'version' => \App\Support\RecordVersion::of($b),
                'urls' => [
                    'update' => route('projects.budgets.update', [$project, $b]),
                    'destroy' => route('projects.budgets.destroy', [$project, $b]),
                ],
            ])->values(),

            // Sebelum proposal disubmit budget masih bisa diisi; sesudahnya
            // keadaannya sudah final.
            'budgetEmptyMessage' => __(in_array($project->status, Project::EDITABLE_STATUSES, true)
                ? 'messages.budget.empty_editable'
                : 'messages.budget.empty_final'),

            'attachments' => $project->attachments->map(function (ProjectAttachment $att) use ($project, $request, $data) {
                $ext = strtolower(pathinfo((string) $att->file_name, PATHINFO_EXTENSION));
                $mayDelete = $request->user()->id === $att->uploaded_by
                    || $data['isLeader']
                    || ReportOverride::aktif($request);

                return [
                    'id' => $att->id,
                    'file_name' => $att->file_name,
                    'file_size' => $att->file_size,
                    // Gambar & PDF bisa dibuka di tab baru; sisanya hanya diunduh.
                    'view_url' => in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)
                        ? route('projects.attachments.view', [$project, $att])
                        : null,
                    'download_url' => route('projects.attachments.download', [$project, $att]),
                    'destroy_url' => $mayDelete
                        ? route('projects.attachments.destroy', [$project, $att])
                        : null,
                ];
            })->values(),

            'approvals' => $project->approvals->map(fn (ProjectApproval $a) => [
                'id' => $a->id,
                'layer' => $a->layer,
                'decision' => $a->decision,
                'note' => $a->note,
                'decided_by' => $a->user?->name,
                'on_behalf_of' => $a->on_behalf_of_id
                    ? ($a->onBehalfOf?->name ?? '#' . $a->on_behalf_of_id)
                    : null,
                'created_at' => $a->created_at?->toIso8601ZuluString(),
            ])->values(),

            'statusLogs' => $project->statusLogs->map(fn ($log) => [
                'id' => $log->id,
                'old_status' => $log->old_status,
                'new_status' => $log->new_status,
                'remarks' => $log->remarks,
                'changed_by' => $log->changedBy?->name,
                'created_at' => $log->created_at?->toIso8601ZuluString(),
            ])->values(),

            'changeReviews' => $data['changeReviews']->map(function (array $review) use ($project) {
                $update = $review['update'];

                return [
                    'id' => $update->id,
                    // Kode + label: halaman menerjemahkan dari kodenya.
                    'change_type' => $update->change_type,
                    'label' => $review['label'],
                    'requester' => $update->requester?->name,
                    'requested_at' => $update->updated_at?->toIso8601ZuluString(),
                    'approver_role' => $update->approver_role,
                    'current_layer' => $update->current_layer,
                    'review_note' => $update->review_note,
                    'on_behalf_of' => $review['atasNama']?->name,
                    'diff' => $review['diff'],
                    'urls' => [
                        'approve' => route('projects.updates.approve', [$project, $update]),
                        'revision' => route('projects.updates.revision', [$project, $update]),
                        'reject' => route('projects.updates.reject', [$project, $update]),
                    ],
                ];
            })->values(),

            'pendingLeadership' => $leadershipPending,
            'pendingLeaderName' => $leadershipPending
                ? User::find($leadershipPending['fields']['project_leader_id'])?->name
                : null,
            'pendingDraftCount' => $data['pendingDrafts']->count(),

            'leadershipRights' => $data['leadershipRights'] ?: ['sponsor' => false, 'leader' => false, 'instant' => false],
            'ideaApprover' => $data['ideaApprover']?->name,
            'onBehalfOf' => $data['atasNama']?->name,

            // Opsi PIC = anggota tim project (Leader + Sponsor + Members).
            'teamMembers' => $data['teamMembers']->map(fn (User $u) => [
                'value' => $u->id,
                'label' => $u->name,
            ])->values(),

            'uomGroups' => collect(ImplementationIndicator::UOM_GROUPS)
                ->map(fn (array $options, string $label) => [
                    'label' => $label,
                    'options' => collect($options)->map(fn (string $u) => ['value' => $u, 'label' => $u])->values(),
                ])
                ->values(),

            'indicatorTypes' => ImplementationIndicator::TYPES,

            // Bag error 'member' dipakai dialog Add Member (validasi / batas anggota).
            'memberErrors' => session()->get('errors')?->getBag('member')->all() ?? [],

            'cancellation' => $cancellation ? [
                'reason' => $project->cancellationReason(),
                'by' => $cancellation->changedBy?->name,
                'at' => $cancellation->created_at?->toIso8601ZuluString(),
            ] : null,

            'urls' => [
                'index' => route('projects.index'),
                'report' => route('reports.index', ['type' => 'project']),
                'userSearch' => route('org.users'),
                'leadership' => route('projects.leadership.update', $project),
                'membersStore' => route('projects.members.store', $project),
                'plansStore' => route('projects.implementation.store', $project),
                'indicatorsStore' => route('projects.indicators.store', $project),
                'budgetsStore' => route('projects.budgets.store', $project),
                'attachmentsStore' => route('projects.attachments.store', $project),
                'draft' => route('projects.draft', $project),
                'submit' => route('projects.submit', $project),
                'submitChanges' => route('projects.changes.submit', $project),
                'cancel' => route('projects.cancel', $project),
                'sponsorApprove' => route('projects.sponsor.approve', $project),
                'sponsorRevision' => route('projects.sponsor.revision', $project),
                'reviewApprove' => route('projects.review.approve', $project),
                'reviewRevision' => route('projects.review.revision', $project),
                'reviewReject' => route('projects.review.reject', $project),
            ],
        ];
    }

    /* ---- Submit proposal (Leader) & keputusan Sponsor ---------------- */

    public function submitProposal(Request $request, Project $project)
    {
        abort_unless($project->isEditableByLeader($request->user()), 403);

        // Syarat minimal submit (FR-181): minimal 1 activity & 1 indicator.
        if ($project->implementationPlans()->count() === 0 || $project->indicators()->count() === 0) {
            return back()->with('error', __('flash.projects.needs_plan_indicator'));
        }

        // Total weightage Success Indicators harus 100% (T-43/214).
        $totalWeight = (float) $project->indicators()->sum('weightage');
        if (abs($totalWeight - 100.0) > 0.01) {
            return back()->with('error', __('flash.projects.weight_must_be_100', ['total' => $totalWeight]));
        }

        $this->transition($project, 'submitted', $request->user(), 'Proposal disubmit oleh Project Leader.');

        return back()->with('success', __('flash.projects.proposal_submitted'));
    }

    public function sponsorApprove(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        $this->authorizeSponsor($request, $project);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        // Alirkan ke committee proposal (atau langsung Approved bila belum ada committee).
        $wf->start($project, 'committee_review', $request->user(), $note);

        return back()->with('success', __('flash.projects.sponsor_approved'));
    }

    /**
     * Committee layer aktif yang akan DIGANTIKAN oleh user ini.
     *
     * NULL bila user memang reviewer-nya sendiri, tidak punya izin 'override.role',
     * atau layer aktif tak punya penanggung jawab.
     */
    private function atasNamaProject(Request $request, Project $project): ?User
    {
        $user = $request->user();
        $wf   = app(ProjectApprovalWorkflowService::class);

        if ($wf->isCurrentReviewer($project, $user) || ! ReportOverride::aktif($request)) {
            return null;
        }

        $approver = $wf->currentApprover($project);

        return ($approver && $approver->id !== $user->id) ? $approver : null;
    }

    /** Gate bersama tiga aksi review: reviewer sendiri, atau bertindak atas nama. */
    private function authorizeReview(Request $request, Project $project, ProjectApprovalWorkflowService $wf): ?User
    {
        if ($wf->isCurrentReviewer($project, $request->user())) {
            return null;
        }

        $atasNama = $this->atasNamaProject($request, $project);
        abort_unless($atasNama !== null, 403,
            __('messages.abort.project_layer_committee_only'));

        return $atasNama;
    }

    /**
     * Ubah Project Sponsor dan/atau Project Leader.
     *
     * Tidak pernah menulis langsung: selalu menjadi change request
     * "Project Sponsor & Leader Change" yang disetujui committee layer TERAKHIR
     * dari ide asal project. Bila ide belum punya committee layer terakhir,
     * permintaannya MENGGANTUNG (approver_role 'unassigned') sampai penyetujunya
     * ditentukan — tidak diterapkan diam-diam.
     */
    public function updateLeadership(Request $request, Project $project, ProjectChangeStagingService $svc)
    {
        $hak = $this->leadershipRights($project, $request->user());

        abort_unless($hak['sponsor'] || $hak['leader'], 403,
            __('messages.abort.leadership_not_allowed'));

        $data = $request->validate([
            'project_sponsor_id' => ['nullable', 'integer', 'exists:kpncorp.users,id'],
            'project_leader_id'  => ['nullable', 'integer', 'exists:kpncorp.users,id'],
        ]);

        // Field yang TIDAK boleh ia ubah diabaikan, bukan sekadar disembunyikan di
        // layar — request manual pun tidak bisa menyelipkannya.
        $bolehField = array_filter([
            'project_sponsor_id' => $hak['sponsor'],
            'project_leader_id'  => $hak['leader'],
        ]);

        $baru    = [];
        $sebelum = [];
        foreach (array_keys($bolehField) as $field) {
            $nilai = $data[$field] ?? null;
            if ($nilai !== null && (int) $nilai !== (int) $project->{$field}) {
                $baru[$field]    = (int) $nilai;
                $sebelum[$field] = $project->{$field};
            }
        }

        if (! $baru) {
            return $this->backToSection($project, 'section-team', __('flash.projects.leadership_unchanged'));
        }

        // Langsung berlaku bila: pelakunya committee layer terakhir ide (ia memang
        // penyetujunya), atau project belum berjalan sehingga belum ada kesepakatan
        // yang perlu dilindungi. Selain itu → change request.
        if ($hak['instant']) {
            $ringkas = collect($baru)
                ->map(fn ($v, $k) => ($k === 'project_sponsor_id' ? 'Project Sponsor' : 'Project Leader')
                    . ': ' . (optional(User::find($sebelum[$k] ?? null))->name ?? '(empty)')
                    . ' -> ' . (optional(User::find($v))->name ?? $v))
                ->implode('; ');

            $project->update($baru);
            $this->transition($project, $project->status, $request->user(),
                "Sponsor/Leader changed: {$ringkas}. By {$request->user()->name}.");

            return $this->kembaliDariLeadership($request, $project, __('flash.projects.leadership_updated'));
        }

        // Langsung DIKIRIM, bukan sekadar disimpan sebagai draft: tombolnya memang
        // bertuliskan "Submit for Approval", dan Project Sponsor tidak punya tombol
        // "Update Project" — kalau ditinggal sebagai draft, permintaannya mengendap
        // tanpa pernah sampai ke penyetuju.
        $svc->stage($project, $request->user(), Project::class, $project->getKey(), $baru, $sebelum);
        $svc->submitDrafts($project, $request->user(), null, 'leadership_change');

        $penyetuju = optional($svc->ideaCommitteeApprover($project))->name;

        return $this->kembaliDariLeadership($request, $project, $penyetuju
            ? __('flash.projects.leadership_submitted', ['name' => $penyetuju])
            : __('flash.projects.leadership_pending'));
    }

    /**
     * Kembali ke halaman asal setelah mengubah Sponsor/Leader.
     * Form pada Project Shell Progress mengirim from=shell agar tidak terlempar
     * ke Project Detail yang bukan pintu masuknya.
     */
    private function kembaliDariLeadership(Request $request, Project $project, string $pesan)
    {
        if ($request->input('from') === 'shell') {
            return redirect()
                ->route('projects.shell.progress', $project)
                ->with('success', $pesan);
        }

        return $this->backToSection($project, 'section-team', $pesan);
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
                'status'        => 'status',
                'created_at'    => 'created_at',
            ],
            'default_sort' => 'created_at',
            'default_dir'  => 'desc',
        ];

        $base = $wf->reviewQueueFor($request->user());

        // Project yang punya permintaan perubahan menunggu keputusan user ini ikut
        // masuk Task Box — keputusannya diambil di panel Change Request pada detail.
        // Termasuk update request jenis lama (Budget/Planning/Team/General) yang
        // rutenya ke Sponsor, supaya semua tugas approval user ada di satu tempat.
        $changeIds = app(ProjectChangeStagingService::class)
            ->reviewQueueFor($request->user())
            ->pluck('project_id')
            ->merge(app(ProjectUpdateService::class)
                ->reviewQueueFor($request->user())
                ->pluck('project_id'))
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

        $sortState = $this->listSortState($request, $config);
        $sla = app(\App\Services\SlaService::class);

        return Inertia::render('Projects/Review', [
            'projects' => $query->paginate($perPage)->withQueryString()->through(
                function (Project $project) use ($sla) {
                    $evaluation = $sla->evaluate($project->slaApprovalType(), $project->reviewSince());

                    // Jenis tugas. Project yang sudah berjalan hanya masuk Task Box
                    // karena ada permintaan perubahan yang menunggu keputusan.
                    $kind = match (true) {
                        $project->status === 'completion_review' => 'completion',
                        $project->isInExecution() => 'change_request',
                        default => 'proposal',
                    };

                    return [
                        'id' => $project->id,
                        'project_id' => $project->project_id,
                        'project_name' => $project->project_name,
                        'leader_name' => $project->leader?->name,
                        'business_unit_name' => $project->idea?->businessUnit?->name,
                        'status' => $project->status,
                        'status_label' => $project->statusBadge()[0],
                        'current_layer' => $project->current_layer,
                        'review_kind' => $kind,

                        // Layer 1 proposal (status 'submitted') = keputusan Project Sponsor.
                        'as_sponsor' => $project->status === ProjectApprovalWorkflowService::SPONSOR_PENDING_STATUS,

                        'sla' => [
                            'state' => $evaluation['state'],
                            'due' => $evaluation['due']?->toIso8601ZuluString(),
                            'left' => $evaluation['left'],
                        ],

                        'urls' => ['review' => route('projects.show', $project)],

                        // Detail project masih Blade. Lihat InertiaRoutes.
                        'external' => ! InertiaRoutes::has('projects.show'),
                    ];
                }
            ),

            'filters' => [
                'q' => $request->get('q'),
                'bu' => $request->get('bu'),
                'unit' => $request->get('unit'),
                'per_page' => $perPage,
                'sort' => $sortState['sort'],
                'dir' => $sortState['dir'],
            ],

            // Business Unit dari master_bisnisunits; Unit di-cascade saat dibutuhkan.
            'businessUnits' => KpnBusinessUnit::names()->values(),
        ]);
    }

    public function reviewApprove(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        $atasNama = $this->authorizeReview($request, $project, $wf);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $wf->actingAs($atasNama, fn () => $wf->approve($project, $request->user(), $note));

        return redirect()->route('projects.review')->with('success', $atasNama
            ? __('flash.projects.review_approved_on_behalf',
                ['id' => $project->project_id, 'name' => $atasNama->name])
            : __('flash.projects.review_approved', ['id' => $project->project_id]));
    }

    public function reviewRevision(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        $atasNama = $this->authorizeReview($request, $project, $wf);
        abort_unless($project->status === 'committee_review', 403,
            __('messages.abort.revision_only_committee_review'));

        // Catatan wajib: Leader perlu tahu apa yang harus diperbaiki.
        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];
        $wf->actingAs($atasNama, fn () => $wf->requestRevision($project, $request->user(), $note));

        return redirect()->route('projects.review')->with('success', $atasNama
            ? __('flash.projects.review_revision_on_behalf',
                ['id' => $project->project_id, 'name' => $atasNama->name])
            : __('flash.projects.review_revision', ['id' => $project->project_id]));
    }

    public function reviewReject(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        $atasNama = $this->authorizeReview($request, $project, $wf);

        // Catatan wajib: penolakan bersifat final, pengaju berhak tahu alasannya.
        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];
        $wf->actingAs($atasNama, fn () => $wf->reject($project, $request->user(), $note));

        return redirect()->route('projects.review')->with('success', $atasNama
            ? __('flash.projects.review_rejected_on_behalf',
                ['id' => $project->project_id, 'name' => $atasNama->name])
            : __('flash.projects.review_rejected', ['id' => $project->project_id]));
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
            return back()->with('error', __('flash.projects.completion_needs_indicator'));
        }

        $project->update(['project_summary' => $data['project_summary']]);
        $wf->start($project->fresh(), 'completion_review', $request->user(), 'Completion request disubmit.');

        return back()->with('success', __('flash.projects.completion_submitted'));
    }

    /* ---- Project Update Request (T-260-268) ------------------------- */

    public function requestUpdate(Request $request, Project $project, ProjectUpdateService $svc)
    {
        $user = $request->user();
        abort_unless($project->isInExecution() && $project->isTeamMember($user), 403,
            __('messages.abort.only_team_requests_update'));

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
            'review_note'     => $auto ? __('flash.updates.auto_approved_note') : null,
            'snapshot_before' => $svc->snapshot($project),
        ]);

        return back()->with('success', $auto
            ? __('flash.updates.auto_approved')
            : __('flash.updates.submitted_to', [
                'approver' => __($approver === 'committee'
                    ? 'flash.updates.approver_committee'
                    : 'flash.updates.approver_sponsor'),
            ]));
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

    /**
     * Gate keputusan change request. Mengembalikan committee yang DIWAKILI bila
     * user memutus lewat izin 'override.role', atau null bila ia memang penilainya.
     */
    private function atasNamaUpdate(Request $request, ProjectUpdate $update, ProjectChangeStagingService $svc): ?User
    {
        $user = $request->user();

        if ($svc->isReviewer($update, $user)) {
            return null;
        }

        abort_unless(ReportOverride::aktif($request), 403,
            __('messages.abort.only_assigned_approver'));

        // Permintaan yang menggantung belum punya penyetuju — tak ada yang bisa diwakili.
        abort_unless($update->approver_role !== 'unassigned', 403,
            __('messages.abort.no_approver_yet'));

        return $svc->approverOf($update);
    }

    public function approveUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        if ($this->isStagedChange($update)) {
            $staging  = app(ProjectChangeStagingService::class);
            $atasNama = $this->atasNamaUpdate($request, $update, $staging);

            $hasil = $staging->actingAs($atasNama, fn () => $staging->approve($update, $request->user(), $note));
            $label = ProjectChangeStagingService::label($update->change_type);

            return back()->with('success', $hasil === 'applied'
                ? __('flash.updates.approved_applied', ['label' => $label])
                : __('flash.updates.approved_forwarded',
                    ['label' => $label, 'layer' => $update->fresh()->current_layer]));
        }

        abort_unless($svc->isReviewer($update, $request->user()), 403);
        $update->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'review_note' => $note]);

        return back()->with('success', __('flash.updates.legacy_approved'));
    }

    /** Kembalikan permintaan ke Project Leader untuk diperbaiki (semua layer). */
    public function revisionUpdate(Request $request, Project $project, ProjectUpdate $update)
    {
        abort_unless($update->project_id === $project->id, 404);
        abort_unless($this->isStagedChange($update), 404);

        $staging  = app(ProjectChangeStagingService::class);
        $atasNama = $this->atasNamaUpdate($request, $update, $staging);

        // Catatan wajib: Leader perlu tahu apa yang harus diperbaiki.
        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];
        $staging->actingAs($atasNama, fn () => $staging->requestRevision($update, $request->user(), $note));

        $label = ProjectChangeStagingService::label($update->change_type);

        return back()->with('success', __('flash.updates.revision', ['label' => $label]));
    }

    public function rejectUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);

        // Penolakan change request juga wajib disertai alasan.
        $note = $this->isStagedChange($update)
            ? $request->validate(['note' => ['required', 'string', 'max:2000']])['note']
            : ($request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null);

        if ($this->isStagedChange($update)) {
            $staging  = app(ProjectChangeStagingService::class);
            $atasNama = $this->atasNamaUpdate($request, $update, $staging);
            $staging->actingAs($atasNama, fn () => $staging->reject($update, $request->user(), $note));

            return back()->with('success', __('flash.updates.rejected',
                ['label' => ProjectChangeStagingService::label($update->change_type)]));
        }

        abort_unless($svc->isReviewer($update, $request->user()), 403);
        $update->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'review_note' => $note]);

        return back()->with('success', __('flash.updates.legacy_rejected'));
    }

    public function applyUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);
        abort_unless($project->project_leader_id === $request->user()->id && $update->status === 'approved', 403,
            __('messages.abort.only_leader_applies_update'));

        // Rekam kondisi setelah perubahan (before/after) lalu tutup update.
        $update->update(['status' => 'applied', 'snapshot_after' => $svc->snapshot($project->fresh())]);

        return back()->with('success', __('flash.updates.applied'));
    }

    /* ---- Project Cancellation (T-184/185/186/188/97) --------------- */

    public function cancelProject(Request $request, Project $project)
    {
        abort_unless($this->canCancel($project, $request->user()), 403,
            __('messages.abort.cancel_not_allowed'));

        if (in_array($project->status, ['completed', 'cancelled'], true)) {
            return back()->with('error', __('flash.projects.cancel_not_allowed'));
        }

        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];

        $this->transition($project, 'cancelled', $request->user(), 'Cancelled: '.$reason);

        // T-97: pending approval/update otomatis hilang.
        $project->updates()->where('status', 'pending')->update(['status' => 'cancelled']);

        return back()->with('success', __('flash.projects.cancelled'));
    }

    private function canCancel(Project $project, User $user): bool
    {
        // Project Leader & Project Sponsor (perilaku lama), ditambah Idea Committee
        // layer terakhir dan Super Admin yang membatalkan dari My Ideas > Project Shell.
        if ($project->project_leader_id === $user->id
            || $project->project_sponsor_id === $user->id
            || ReportOverride::aktif()) {
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
        abort_unless(($project->project_leader_id === $request->user()->id
            || ReportOverride::aktif($request)) && $project->isInExecution(), 403,
            __('messages.abort.only_leader_submits_changes'));

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        $terkirim = $svc->submitDrafts($project, $request->user(), $note);

        if (! $terkirim) {
            return back()->with('error', __('flash.projects.no_pending_change'));
        }

        return $this->backToSection($project, 'section-implementation',
            __('flash.projects.changes_submitted', ['items' => implode(', ', $terkirim)]),
            ['phase' => 'implementation']);
    }

    /** Pesan sukses yang menjelaskan bila perubahan masih menunggu pengajuan. */
    private function pesanSimpan(string $normal, int $distage): string
    {
        return $distage > 0
            ? __('flash.projects.saved_with_draft', ['count' => $distage])
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

        return back()->with('success', __('flash.projects.sponsor_revision'));
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

        return $this->backToSection($project, 'section-implementation', __('flash.projects.activity_added'));
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
            $this->pesanSimpan(__('flash.projects.activity_updated'), $distage),
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

        return $this->backToSection($project, 'section-implementation', __('flash.projects.activity_removed'));
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

        return $this->backToSection($project, 'section-implementation', __('flash.projects.actual_updated'), ['phase' => 'implementation']);
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
                || ReportOverride::aktif($request),
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

        return $this->backToSection($project, 'section-implementation', __('flash.projects.attachment_removed'),
            $project->isInExecution() ? ['phase' => 'implementation'] : []);
    }

    /** Buka satu lampiran baris Budget di tab baru. */
    public function viewBudgetAttachment(Request $request, Project $project, ProjectBudget $budget, ProjectBudgetAttachment $attachment)
    {
        abort_unless($budget->project_id === $project->id, 404);
        abort_unless($attachment->project_budget_id === $budget->id, 404);
        abort_unless(
            Project::relatedTo($request->user()->id)->whereKey($project->id)->exists()
                || ReportOverride::aktif($request),
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

        return $this->backToSection($project, 'section-budget', __('flash.projects.attachment_removed'),
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
                || ReportOverride::aktif($request),
            403
        );
        abort_unless($plan->attachment_path, 404, __('messages.abort.no_attachment'));

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
            __('messages.abort.only_leader_or_team_edits'));

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

        return $this->backToSection($project, 'section-indicators', __('flash.projects.indicator_added'));
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

            return $this->backToSection($project, 'section-indicators', __('flash.projects.achievement_updated'), ['phase' => 'implementation']);
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
            $this->pesanSimpan(__('flash.projects.indicator_updated'), $distage),
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
            $this->pesanSimpan(__('flash.projects.indicator_achievement_updated'), $distage),
            ['phase' => 'implementation']);
    }

    public function destroyIndicator(Request $request, Project $project, ImplementationIndicator $indicator)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($indicator->project_id === $project->id, 404);
        $indicator->delete();

        return $this->backToSection($project, 'section-indicators', __('flash.projects.indicator_removed'));
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

        return $this->backToSection($project, 'section-budget', __('flash.projects.budget_added'));
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
                $this->pesanSimpan(__('flash.projects.actual_budget_updated'), $distage),
                ['phase' => 'implementation']);
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
            $this->pesanSimpan(__('flash.projects.budget_updated'), $distage),
            $project->isInExecution() ? ['phase' => 'implementation'] : []);
    }

    public function destroyBudget(Request $request, Project $project, ProjectBudget $budget)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($budget->project_id === $project->id, 404);
        $budget->delete();

        return $this->backToSection($project, 'section-budget', __('flash.projects.budget_removed'));
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

        return $this->backToSection($project, 'section-budget', __('flash.projects.actual_budget_updated'), ['phase' => 'implementation']);
    }

    public function storeMember(Request $request, Project $project)
    {
        // Leader (fase proposal/berjalan) ATAU admin pemegang override.role.
        abort_unless($this->bolehUbahTim($project, $request->user()), 403,
            __('messages.abort.only_leader_or_admin_adds_members'));

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
            'rows.*.user_id.exists' => __('messages.validation.member_not_found'),
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

        return $this->backToSection($project, 'section-team', __('flash.projects.members_added', ['count' => count($rows)]));
    }

    /** Kembali ke detail project dengan dialog Add Member terbuka + daftar alasan penolakan. */
    private function backToMemberDialog(Project $project, array $messages)
    {
        return redirect()
            ->to(route('projects.show', $project) . '#section-team')
            ->withErrors(['rows' => $messages], 'member')
            ->with('memberDialogOpen', true);
    }

    /**
     * Boleh menyunting Team Members & Sponsor/Leader?
     *
     * Project Leader (fase proposal maupun berjalan) ATAU admin pemegang izin
     * 'override.role'. Hasil suntingan admin tetap melewati change request —
     * ia tidak menulis langsung ke data.
     */
    private function bolehUbahTim(Project $project, User $user): bool
    {
        return $this->leaderMayEditProposalFields($project, $user)
            || ReportOverride::aktif()
            || $this->isLastLayerIdeaCommittee($project, $user);
    }

    /** Committee layer TERAKHIR dari ide asal project ini? */
    private function isLastLayerIdeaCommittee(Project $project, User $user): bool
    {
        return $project->idea
            && app(IdeaWorkflowService::class)->isLastLayerCommittee($project->idea, $user);
    }

    /** Matriks hak ubah Sponsor/Leader — definisinya ada di Project::leadershipRights(). */
    private function leadershipRights(Project $project, User $user): array
    {
        return $project->leadershipRights($user);
    }

    public function updateMember(Request $request, Project $project, ProjectMember $member)
    {
        // Leader boleh mengubah anggota saat proposal MAUPUN saat project berjalan;
        // pada fase berjalan hasilnya masuk change request "Team Change", bukan
        // langsung berlaku. authorizeLeader() menolak fase berjalan, jadi tidak dipakai.
        abort_unless($this->bolehUbahTim($project, $request->user()), 403,
            __('messages.abort.only_leader_or_admin_edits_members'));
        abort_unless($member->project_id === $project->id, 404);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:kpncorp.users,id'],
            'role'    => ['required', 'string', 'max:100'],
        ]);

        $distage = $this->writeOrStage($request, $project, $member, [],
            ['user_id' => $data['user_id'], 'role' => $data['role']]);

        return $this->backToSection($project, 'section-team',
            $this->pesanSimpan(__('flash.projects.member_updated'), $distage),
            $project->isInExecution() ? ['phase' => 'implementation'] : []);
    }

    public function destroyMember(Request $request, Project $project, ProjectMember $member)
    {
        abort_unless($this->bolehUbahTim($project, $request->user()), 403,
            __('messages.abort.only_leader_or_admin_removes_members'));
        abort_unless($member->project_id === $project->id, 404);
        $member->delete();

        return $this->backToSection($project, 'section-team', __('flash.projects.member_removed'));
    }

    /**
     * Simpan sebagai draft — proposal memang tersimpan otomatis saat edit inline;
     * tombol ini sekadar konfirmasi + kembali ke daftar dengan notifikasi.
     */
    public function saveDraft(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        return redirect()->route('projects.index')->with('success', __('flash.projects.proposal_draft_saved'));
    }

    /* ---- Attachments (T-100) ----------------------------------------- */

    public function storeAttachment(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($project->isTeamMember($user) || ReportOverride::aktif($request), 403,
            __('messages.abort.only_team_uploads'));

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

        return $this->backToSection($project, 'section-attachments', __('flash.projects.attachments_uploaded', ['count' => count($files)]));
    }

    public function downloadAttachment(Request $request, Project $project, ProjectAttachment $attachment)
    {
        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless(
            Project::relatedTo($request->user()->id)->whereKey($project->id)->exists()
                || ReportOverride::aktif($request),
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
                || ReportOverride::aktif($request),
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
                || ReportOverride::aktif($request),
            403,
            'Only the uploader or the Project Leader may delete attachments.'
        );

        $this->deleteAttachmentFile($attachment->file_path);
        $attachment->delete();

        return $this->backToSection($project, 'section-attachments', __('flash.projects.attachment_deleted'));
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
        abort_unless($idea->status === 'approved', 403, __('messages.abort.idea_not_approved'));

        // TIDAK ada pembebasan Super Admin: membuat shell tetap hak committee
        // layer terakhir. Di menu Project Shell (yang memperlihatkan semua ide
        // approved ke Super Admin) tombolnya diganti dialog penjelasan lewat
        // flag can_create_shell — lihat shellIndex().
        $isLastLayer = app(IdeaWorkflowService::class)->isLastLayerCommittee($idea, $user);

        abort_unless($isLastLayer, 403, __('messages.abort.only_last_layer_creates_project'));
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
