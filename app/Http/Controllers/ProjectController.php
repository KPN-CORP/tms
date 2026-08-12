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
                'project_leader_id'  => $leaderUser ? null : 'Leader belum punya akun user di hcis.',
                'project_sponsor_id' => $sponsorUser ? null : 'Sponsor belum punya akun user di hcis.',
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
    public function index(Request $request)
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

        // Filter BU/Unit by NAMA (dropdown dari hcis) pada ide terkait project —
        // logika sama seperti My Ideas.
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

        // Tab lifecycle Project Proposal → status DB. 'all' = semua.
        $statusMap = [
            'draft'     => 'draft',
            'submitted' => 'submitted',
            'approved'  => 'approved',
            'review'    => 'committee_review', // "On Review"
            'revision'  => 'revision',         // "Revision Required"
            'rejected'  => 'rejected',
        ];
        $tab = array_key_exists($request->get('tab'), $statusMap) ? $request->get('tab') : 'all';
        if ($tab !== 'all') {
            $query->where('status', $statusMap[$tab]);
        }

        $perPage = $this->listPerPage($request);

        return view('projects.index', [
            'projects'  => $query->paginate($perPage)->withQueryString(),
            'perPage'   => $perPage,
            'buNames'   => KpnBusinessUnit::names(), // Business Unit dari master_bisnisunits; Unit cascade via AJAX
            'counts'    => $counts,
            'tab'       => $tab,
            'statusMap' => $statusMap,
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
            'idea.businessUnit', 'idea.department', 'idea.user',
            'sponsor', 'leader', 'members.user', 'budgets',
            'implementationPlans', 'indicators', 'statusLogs.changedBy', 'attachments.uploader',
            'updates.requester', 'updates.reviewer', 'approvals.user',
        ]);

        $user = $request->user();

        // Opsi PIC = anggota tim project (Leader + Sponsor + Members).
        $teamMembers = collect([$project->leader, $project->sponsor])
            ->merge($project->members->map(fn ($m) => $m->user))
            ->filter()
            ->unique('id')
            ->values();

        $updateSvc = app(ProjectUpdateService::class);

        return view('projects.show', [
            'project'            => $project,
            'canEdit'            => $project->canLeaderEditProposal($user),
            'isLeader'           => $project->project_leader_id === $user->id,
            'isSponsor'          => $project->project_sponsor_id === $user->id,
            'isReviewer'         => app(ProjectApprovalWorkflowService::class)->isCurrentReviewer($project, $user),
            'canTrack'           => $project->project_leader_id === $user->id && $project->isInExecution(),
            'canSubmitCompletion' => $project->project_leader_id === $user->id && $project->isInExecution(),
            'canRequestUpdate'   => $project->isInExecution() && $project->isTeamMember($user),
            'canCancel'          => $this->canCancel($project, $user) && ! in_array($project->status, ['completed', 'cancelled'], true),
            'reviewableUpdateIds' => $project->updates->filter(fn ($u) => $updateSvc->isReviewer($u, $user))->pluck('id'),
            'updateTypes'        => ProjectUpdate::CHANGE_TYPES,
            'users'              => User::orderBy('name')->get(),
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
            return back()->with('error', 'Minimal 1 Implementation Plan dan 1 Success Indicator sebelum submit.');
        }

        // Total weightage Success Indicators harus 100% (T-43/214).
        $totalWeight = (float) $project->indicators()->sum('weightage');
        if (abs($totalWeight - 100.0) > 0.01) {
            return back()->with('error', "Total weightage Success Indicators harus 100% (sekarang {$totalWeight}%).");
        }

        $this->transition($project, 'submitted', $request->user(), 'Proposal disubmit oleh Project Leader.');

        return back()->with('success', 'Proposal submitted ke Project Sponsor.');
    }

    public function sponsorApprove(Request $request, Project $project, ProjectApprovalWorkflowService $wf)
    {
        $this->authorizeSponsor($request, $project);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        // Alirkan ke committee proposal (atau langsung Approved bila belum ada committee).
        $wf->start($project, 'committee_review', $request->user(), $note ?? 'Disetujui Project Sponsor.');

        return back()->with('success', 'Proposal disetujui Sponsor.');
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
            'Hanya Project Leader dari project yang sedang berjalan yang boleh submit completion.'
        );

        $data = $request->validate([
            'project_summary' => ['required', 'string', 'max:20000'],
        ]);

        // Wajib minimal 1 Success Indicator (T-66/181).
        if ($project->indicators()->count() === 0) {
            return back()->with('error', 'Minimal 1 Success Indicator sebelum submit completion.');
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
            'Hanya anggota tim project berjalan yang boleh mengajukan update.');

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
            ? 'Update request otomatis disetujui. Silakan edit lalu Apply.'
            : 'Update request diajukan ke '.($approver === 'committee' ? 'Committee' : 'Sponsor').'.');
    }

    public function approveUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);
        abort_unless($svc->isReviewer($update, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $update->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'review_note' => $note]);

        return back()->with('success', 'Update request disetujui. Project Leader dapat mengedit lalu Apply.');
    }

    public function rejectUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);
        abort_unless($svc->isReviewer($update, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $update->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'review_note' => $note]);

        return back()->with('success', 'Update request ditolak.');
    }

    public function applyUpdate(Request $request, Project $project, ProjectUpdate $update, ProjectUpdateService $svc)
    {
        abort_unless($update->project_id === $project->id, 404);
        abort_unless($project->project_leader_id === $request->user()->id && $update->status === 'approved', 403,
            'Hanya Project Leader yang boleh menerapkan update yang sudah di-approve.');

        // Rekam kondisi setelah perubahan (before/after) lalu tutup update.
        $update->update(['status' => 'applied', 'snapshot_after' => $svc->snapshot($project->fresh())]);

        return back()->with('success', 'Perubahan diterapkan (update ditutup).');
    }

    /* ---- Project Cancellation (T-184/185/186/188/97) --------------- */

    public function cancelProject(Request $request, Project $project)
    {
        abort_unless($this->canCancel($project, $request->user()), 403,
            'Hanya Committee idea layer terakhir atau Super Admin yang boleh membatalkan.');

        if (in_array($project->status, ['completed', 'cancelled'], true)) {
            return back()->with('error', 'Project yang sudah Completed/Cancelled tidak bisa dibatalkan.');
        }

        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];

        $this->transition($project, 'cancelled', $request->user(), 'Cancelled: '.$reason);

        // T-97: pending approval/update otomatis hilang.
        $project->updates()->where('status', 'pending')->update(['status' => 'cancelled']);

        return back()->with('success', 'Project dibatalkan.');
    }

    private function canCancel(Project $project, User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        $idea = $project->idea;

        return $idea && app(IdeaWorkflowService::class)->isLastLayerCommittee($idea, $user);
    }

    public function sponsorRevision(Request $request, Project $project)
    {
        $this->authorizeSponsor($request, $project);

        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];
        $this->transition($project, 'revision', $request->user(), $note);

        return back()->with('success', 'Proposal dikembalikan ke Project Leader untuk revisi.');
    }

    private function authorizeSponsor(Request $request, Project $project): void
    {
        abort_unless(
            $project->project_sponsor_id === $request->user()->id && $project->status === 'submitted',
            403,
            'Hanya Project Sponsor yang boleh memutuskan proposal berstatus submitted.'
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

        $data = $request->validate([
            'activity'       => ['required', 'string', 'max:2000'],
            'planning_start' => ['nullable', 'date'],
            'planning_end'   => ['nullable', 'date'],
            'actual_start'   => ['nullable', 'date'],
            'actual_end'     => ['nullable', 'date'],
            'pic_user_ids'   => ['nullable', 'array'],
            'pic_user_ids.*' => ['integer', 'exists:users,id'],
            'remarks'        => ['nullable', 'string', 'max:2000'],
        ]);

        $project->implementationPlans()->create($data + [
            'sequence_no' => (int) $project->implementationPlans()->max('sequence_no') + 1,
        ]);

        return back()->with('success', 'Implementation activity added.');
    }

    public function destroyImplementation(Request $request, Project $project, ImplementationPlan $plan)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($plan->project_id === $project->id, 404);
        $plan->delete();

        return back()->with('success', 'Activity removed.');
    }

    /**
     * Update ACTUAL implementation saat project berjalan (tracking).
     * Status project otomatis dihitung ulang (Ongoing/Delayed) — T-63/176/177.
     */
    public function updateImplementationActual(Request $request, Project $project, ImplementationPlan $plan)
    {
        $this->authorizeLeaderExecution($request, $project);
        abort_unless($plan->project_id === $project->id, 404);

        $data = $request->validate([
            'actual_start' => ['nullable', 'date'],
            'actual_end'   => ['nullable', 'date'],
            'remarks'      => ['nullable', 'string', 'max:2000'],
        ]);

        $plan->update($data);

        $this->recomputeExecutionStatus($project->fresh(), $request->user());

        return back()->with('success', 'Actual implementation diperbarui.');
    }

    private function authorizeLeaderExecution(Request $request, Project $project): void
    {
        abort_unless(
            $project->project_leader_id === $request->user()->id && $project->isInExecution(),
            403,
            'Hanya Project Leader saat project berjalan (Approved/Ongoing/Delayed).'
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
            $this->transition($project, $new, $user, 'Auto: status eksekusi diperbarui dari progress activity.');
        }
    }

    public function storeIndicator(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        $data = $request->validate([
            'indicator'   => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'baseline'    => ['nullable', 'numeric'],
            'achievement' => ['nullable', 'numeric'],
            'uom'         => ['nullable', 'string', 'max:30'],
            'weightage'   => ['nullable', 'numeric', 'between:0,100'],
            'type'        => ['nullable', Rule::in(ImplementationIndicator::TYPES)],
        ]);

        $project->indicators()->create($data + [
            'improvement' => ImplementationIndicator::calcImprovement(
                $data['baseline'] ?? null,
                $data['achievement'] ?? null,
                $data['type'] ?? null
            ),
            'sort_order'  => (int) $project->indicators()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Success indicator added.');
    }

    public function destroyIndicator(Request $request, Project $project, ImplementationIndicator $indicator)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($indicator->project_id === $project->id, 404);
        $indicator->delete();

        return back()->with('success', 'Indicator removed.');
    }

    public function storeBudget(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        $data = $request->validate([
            'item'       => ['required', 'string', 'max:255'],
            'qty'        => ['nullable', 'numeric'],
            'uom'        => ['nullable', 'string', 'max:30'],
            'unit_price' => ['nullable', 'numeric'],
            'remarks'    => ['nullable', 'string', 'max:2000'],
        ]);

        $project->budgets()->create($data);

        return back()->with('success', 'Budget item added.');
    }

    public function destroyBudget(Request $request, Project $project, ProjectBudget $budget)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($budget->project_id === $project->id, 404);
        $budget->delete();

        return back()->with('success', 'Budget item removed.');
    }

    /** Actual budget tracking (T-61) — saat project berjalan. */
    public function updateBudgetActual(Request $request, Project $project, ProjectBudget $budget)
    {
        $this->authorizeLeaderExecution($request, $project);
        abort_unless($budget->project_id === $project->id, 404);

        $data = $request->validate([
            'actual_qty'   => ['nullable', 'numeric'],
            'actual_price' => ['nullable', 'numeric'],
        ]);

        $cost = (isset($data['actual_qty']) && isset($data['actual_price']))
            ? (float) $data['actual_qty'] * (float) $data['actual_price']
            : null;

        $budget->update($data + ['actual_cost' => $cost]);

        return back()->with('success', 'Actual budget diperbarui.');
    }

    public function storeMember(Request $request, Project $project)
    {
        $this->authorizeLeader($request, $project);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role'    => ['required', 'string', 'max:100'],
        ]);

        // T-86: batas jumlah anggota tim per kategori (bila diatur).
        $max = optional($project->category)->max_team_members;
        if ($max && $project->members()->count() >= $max) {
            return back()->withErrors(['user_id' => "Jumlah anggota tim sudah mencapai batas kategori ({$max})."]);
        }

        $project->members()->create($data + [
            'joined_at' => now()->toDateString(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Team member added.');
    }

    public function destroyMember(Request $request, Project $project, ProjectMember $member)
    {
        $this->authorizeLeader($request, $project);
        abort_unless($member->project_id === $project->id, 404);
        $member->delete();

        return back()->with('success', 'Team member removed.');
    }

    /* ---- Attachments (T-100) ----------------------------------------- */

    public function storeAttachment(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($project->isTeamMember($user) || $user->hasRole('Super Admin'), 403,
            'Hanya anggota tim project yang boleh mengunggah lampiran.');

        $request->validate(['file' => array_merge(['required'], $this->attachmentRules())]);

        $project->attachments()->create(
            $this->storeAttachmentFile($request->file('file'), "project-attachments/{$project->id}", $user->id)
        );

        return back()->with('success', 'Lampiran diunggah.');
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

    public function destroyAttachment(Request $request, Project $project, ProjectAttachment $attachment)
    {
        abort_unless($attachment->project_id === $project->id, 404);
        $user = $request->user();
        abort_unless(
            $attachment->uploaded_by === $user->id
                || $project->project_leader_id === $user->id
                || $user->hasRole('Super Admin'),
            403,
            'Hanya pengunggah atau Project Leader yang boleh menghapus lampiran.'
        );

        $this->deleteAttachmentFile($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Lampiran dihapus.');
    }

    private function authorizeLeader(Request $request, Project $project): void
    {
        abort_unless(
            $project->canLeaderEditProposal($request->user()),
            403,
            'Hanya Project Leader yang boleh mengubah (saat draft/revision atau ada update yang di-approve).'
        );
    }

    /* ----------------------------------------------------------------- */

    private function authorizeShellCreator(User $user, Idea $idea): void
    {
        abort_unless($idea->status === 'approved', 403, 'Idea belum approved.');

        $isLastLayer = app(IdeaWorkflowService::class)->isLastLayerCommittee($idea, $user);

        abort_unless($isLastLayer, 403, 'Hanya committee layer terakhir yang boleh membuat project.');
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
