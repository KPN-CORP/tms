<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileAttachments;
use App\Http\Controllers\Concerns\HasListQuery;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Idea;
use App\Models\ImplementationIndicator;
use App\Models\ImplementationPlan;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectBudget;
use App\Models\ProjectCategory;
use App\Models\ProjectMember;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\Idea\IdeaWorkflowService;
use App\Services\Project\ProjectApprovalWorkflowService;
use App\Services\Project\ProjectUpdateService;
use Illuminate\Http\Request;
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

    /**
     * Form Create Project Shell dari sebuah ide approved.
     */
    public function create(Request $request)
    {
        $idea = Idea::where('idea_id', $request->query('idea_id'))->firstOrFail();
        $this->authorizeShellCreator($request->user(), $idea);

        // --- (DI-COMMENT) Load semua employee — sekarang dropdown pakai AJAX search.
        // $employees = KpnEmployee::select('employee_id', 'fullname', 'designation')->orderBy('fullname')->get();

        // Pre-fill Leader/Sponsor saat ada old input (mis. kembali karena error validasi).
        $preselect = KpnEmployee::query()
            ->whereIn('employee_id', array_filter([old('project_leader_id'), old('project_sponsor_id')]))
            ->get()
            ->keyBy('employee_id');

        return view('projects.create', [
            'idea'       => $idea,
            'categories' => ProjectCategory::where('is_active', true)->orderBy('name')->get(),
            'preselect'  => $preselect,
            'searchUrl'  => route('org.employees'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'idea_id'             => ['required', 'exists:ideas,idea_id'],
            'project_name'        => ['required', 'string', 'max:255'],
            'project_category_id' => ['required', 'integer', 'exists:project_categories,id'],
            'project_scope'       => ['required', 'string', 'max:255'],
            'expected_outcome'    => ['required', 'string', 'max:500'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'project_sponsor_id'  => ['required', 'string', 'exists:kpncorp.employees,employee_id'],
            'project_leader_id'   => ['required', 'string', 'exists:kpncorp.employees,employee_id'],
        ]);

        $idea     = Idea::where('idea_id', $data['idea_id'])->firstOrFail();
        $this->authorizeShellCreator($request->user(), $idea);

        $leaderEmployee = KpnEmployee::where(
            'employee_id',
            $data['project_leader_id']
        )->firstOrFail();

        $sponsorEmployee = KpnEmployee::where(
            'employee_id',
            $data['project_sponsor_id']
        )->firstOrFail();

        $category = ProjectCategory::findOrFail($data['project_category_id']);

        // T-86: eligibility Leader/Sponsor terhadap grade kategori.

        $errors  = [];
        if (! $category->eligibleAsLeader($leaderEmployee->grade())) {
            $errors['project_leader_id'] = "Job level Leader tidak memenuhi syarat kategori {$category->code} ({$category->gradeRangeText($category->leader_grade_min, $category->leader_grade_max)}).";
        }
        if (! $category->eligibleAsSponsor($sponsorEmployee->grade())) {
            $errors['project_sponsor_id'] = "Job level Sponsor tidak memenuhi syarat kategori {$category->code} ({$category->gradeRangeText($category->sponsor_grade_min, $category->sponsor_grade_max)}).";
        }
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        $leaderUser  = $this->getOrCreateUser($leaderEmployee);
        $sponsorUser = $this->getOrCreateUser($sponsorEmployee);

        if (! $leaderUser || ! $sponsorUser) {
            return back()->withErrors(array_filter([
                'project_leader_id'  => $leaderUser ? null : 'The leader does not have a user account in hcis.',
                'project_sponsor_id' => $sponsorUser ? null : 'The sponsor does not have a user account in hcis.',
            ]))->withInput();
        }

        $data['project_leader_id']  = $leaderUser->id;
        $data['project_sponsor_id'] = $sponsorUser->id;



        $project = Project::create($data + [
            'project_category' => $category->code,
            'project_id'       => $this->generateProjectId($idea, $category->code),
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Project shell created ({$project->project_id}).");
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
            'phaseStatuses' => null, // tampilkan semua status terkait
            'statusMap'     => [
                'draft'     => 'draft',
                'submitted' => 'submitted',
                'approved'  => 'approved',
                'review'    => 'committee_review', // "On Review"
                'revision'  => 'revision',         // "Revision Required"
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

        $base = Project::relatedTo($request->user()->id)
            ->whereHas('idea', fn ($q) => $q->visibleTo($request->user()));

        // Batasi ke status fase ini (Implementation/Completion). Proposal: null = semua.
        if (! empty($opts['phaseStatuses'])) {
            $base->whereIn('status', $opts['phaseStatuses']);
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
        ] + $this->listSortState($request, $config));
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

        $updateSvc = app(ProjectUpdateService::class);

        // Hanya muat user PIC yang benar-benar dipakai (untuk resolusi nama di tabel plan) —
        // BUKAN seluruh user hcis. Penambahan member memakai search AJAX (org.users).
        $picIds = $project->implementationPlans
            ->pluck('pic_user_ids')->filter()->flatten()->unique()->values();

        return view('projects.show', [
            'project'            => $project,
            'showActual'         => $showActual,
            'phase'              => $isImplementationView ? 'implementation' : null,
            'canEdit'            => $project->canLeaderEditProposal($user),
            'isLeader'           => $project->project_leader_id === $user->id,
            'isSponsor'          => $project->project_sponsor_id === $user->id,
            'isReviewer'         => app(ProjectApprovalWorkflowService::class)->isCurrentReviewer($project, $user),
            'canTrack'           => $showActual && $project->isTeamMember($user),
            'canSubmitCompletion' => $project->project_leader_id === $user->id && $project->isInExecution(),
            'canRequestUpdate'   => $project->isInExecution() && $project->isTeamMember($user),
            'canCancel'          => $this->canCancel($project, $user) && ! in_array($project->status, ['completed', 'cancelled'], true),
            'reviewableUpdateIds' => $project->updates->filter(fn ($u) => $updateSvc->isReviewer($u, $user))->pluck('id'),
            'updateTypes'        => ProjectUpdate::CHANGE_TYPES,
            'users'              => User::whereIn('id', $picIds)->get(),
            'teamMembers'        => $teamMembers,
            'canUploadAttachment' => $project->isTeamMember($user) || $user->hasRole('Super Admin'),
        ]);
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
        $wf->start($project, 'committee_review', $request->user(), $note ?? 'Approved by the Project Sponsor.');

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

    public function reviewReject(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        abort_unless($wf->isCurrentReviewer($project, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
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

    public function approveUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);
        abort_unless($svc->isReviewer($update, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $update->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'review_note' => $note]);

        return back()->with('success', 'Update request approved. The Project Leader can edit and then Apply.');
    }

    public function rejectUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);
        abort_unless($svc->isReviewer($update, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
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
            'Only the last idea committee layer or a Super Admin may cancel.');

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
        // Cancel Project HANYA untuk Project Leader & Project Sponsor.
        return $project->project_leader_id === $user->id
            || $project->project_sponsor_id === $user->id;
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
        $this->transition($project, 'revision', $request->user(), $note);

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
            'rows.*.planning_end'     => ['nullable', 'date'],
            'rows.*.actual_start'     => ['nullable', 'date'],
            'rows.*.actual_end'       => ['nullable', 'date'],
            'rows.*.pic_user_ids'     => ['nullable', 'array'],
            'rows.*.pic_user_ids.*'   => ['integer', 'exists:kpncorp.users,id'],
        ]);

        $seq = (int) $project->implementationPlans()->max('sequence_no');
        foreach (array_values($request->input('rows')) as $r) {
            $project->implementationPlans()->create([
                'activity'       => $r['activity'],
                'planning_start' => $r['planning_start'] ?? null,
                'planning_end'   => $r['planning_end'] ?? null,
                'actual_start'   => $r['actual_start'] ?? null,
                'actual_end'     => $r['actual_end'] ?? null,
                'pic_user_ids'   => $r['pic_user_ids'] ?? [],
                'sequence_no'    => ++$seq,
            ]);
        }

        return $this->backToSection($project, 'section-implementation', 'Activity added.');
    }

    public function updateImplementation(Request $request, Project $project, ImplementationPlan $plan)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($plan->project_id === $project->id, 404);

        $data = $request->validate([
            'activity'       => ['required', 'string', 'max:2000'],
            'planning_start' => ['nullable', 'date'],
            'planning_end'   => ['nullable', 'date'],
            'pic_user_ids'   => ['nullable', 'array'],
            'pic_user_ids.*' => ['integer', 'exists:kpncorp.users,id'],
        ]);

        $plan->update([
            'activity'       => $data['activity'],
            'planning_start' => $data['planning_start'] ?? null,
            'planning_end'   => $data['planning_end'] ?? null,
            'pic_user_ids'   => $data['pic_user_ids'] ?? [],
        ]);

        return $this->backToSection($project, 'section-implementation', 'Activity updated.');
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

        $plan->fill([
            'actual_start' => $data['actual_start'] ?? null,
            'actual_end'   => $data['actual_end'] ?? null,
            'remarks'      => $data['remarks'] ?? null,
        ]);

        // Attachment: ganti file lama bila ada unggahan baru.
        if ($request->hasFile('attachment')) {
            if ($plan->attachment_path) {
                $this->deleteAttachmentFile($plan->attachment_path);
            }
            $file = $request->file('attachment');
            $plan->attachment_path = $file->store("project-implementation/{$project->id}");
            $plan->attachment_name = $file->getClientOriginalName();
        }

        $plan->save();

        $this->recomputeExecutionStatus($project->fresh(), $request->user());

        return $this->backToSection($project, 'section-implementation', 'Actual implementation updated.', ['phase' => 'implementation']);
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
    private function authorizeTeamExecution(Request $request, Project $project): void
    {
        abort_unless(
            $project->isTeamMember($request->user()) && $project->isInExecution(),
            403,
            'Only project team members while the project is running (Approved/Ongoing/Delayed).'
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

        $new = $anyDelayed ? 'delayed' : ($anyStarted ? 'ongoing' : 'approved');

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
            'rows.*.type'              => ['nullable', Rule::in(ImplementationIndicator::TYPES)],
        ]);

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
        $this->authorizeLeader($request, $project);
        abort_unless($indicator->project_id === $project->id, 404);

        $data = $request->validate([
            'indicator'         => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'baseline'          => ['nullable', 'numeric'],
            'achievement_value' => ['nullable', 'numeric'], // TARGET
            'achievement'       => ['nullable', 'numeric'], // aktual
            'uom'               => ['nullable', Rule::in(ImplementationIndicator::uoms())],
            'weightage'         => ['nullable', 'numeric', 'between:0,100'],
            'type'              => ['nullable', Rule::in(ImplementationIndicator::TYPES)],
        ]);

        $indicator->update([
            'indicator'         => $data['indicator'],
            'description'       => $data['description'] ?? null,
            'baseline'          => $data['baseline'] ?? null,
            'achievement_value' => $data['achievement_value'] ?? null,
            'achievement'       => $data['achievement'] ?? null,
            'uom'               => $data['uom'] ?? null,
            'weightage'         => $data['weightage'] ?? null,
            'type'              => $data['type'] ?? null,
            'improvement'       => ImplementationIndicator::calcImprovement($data['baseline'] ?? null, $data['achievement'] ?? null, $data['type'] ?? null),
        ]);

        return $this->backToSection($project, 'section-indicators', 'Indicator updated.');
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

        $indicator->update([
            'achievement' => $data['achievement'] ?? null,
            'improvement' => ImplementationIndicator::calcImprovement($indicator->baseline, $data['achievement'] ?? null, $indicator->type),
        ]);

        return $this->backToSection($project, 'section-indicators', 'Indicator achievement updated.', ['phase' => 'implementation']);
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
        $this->authorizeLeader($request, $project);
        abort_unless($budget->project_id === $project->id, 404);

        $data = $request->validate([
            'item'       => ['required', 'string', 'max:500'],
            'qty'        => ['nullable', 'numeric', 'min:0'],
            'uom'        => ['nullable', Rule::in(ImplementationIndicator::uoms())],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $budget->update([
            'item'       => $data['item'],
            'qty'        => $data['qty'] ?? null,
            'uom'        => $data['uom'] ?? null,
            'unit_price' => $data['unit_price'] ?? null,
        ]);

        return $this->backToSection($project, 'section-budget', 'Budget updated.');
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

        $budget->update($data + ['actual_cost' => $cost]);

        return $this->backToSection($project, 'section-budget', 'Actual budget updated.', ['phase' => 'implementation']);
    }

    public function storeMember(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        // Multi-add: rows[] berisi {user_id, role}. users ada di hcis (kpncorp).
        $request->validate([
            'rows'           => ['required', 'array', 'min:1'],
            'rows.*.user_id' => ['required', 'integer', 'exists:kpncorp.users,id'],
            'rows.*.role'    => ['required', 'string', 'max:100'],
        ]);
        $rows = array_values($request->input('rows'));

        // T-86: batas jumlah anggota tim per kategori (bila diatur).
        $max = optional($project->category)->max_team_members;
        if ($max && $project->members()->count() + count($rows) > $max) {
            return back()->withErrors(['rows' => "Melebihi batas anggota tim kategori ({$max})."]);
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

    public function updateMember(Request $request, Project $project, ProjectMember $member)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($member->project_id === $project->id, 404);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:kpncorp.users,id'],
            'role'    => ['required', 'string', 'max:100'],
        ]);

        $member->update(['user_id' => $data['user_id'], 'role' => $data['role']]);

        return $this->backToSection($project, 'section-team', 'Team member updated.');
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
