<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use LogsActivity;

    public const CATEGORIES = ['QCC', 'QCP', 'SS'];

    /** Status di mana Project Leader masih boleh mengedit proposal. */
    public const EDITABLE_STATUSES = ['draft', 'revision_required'];

    /** Status "project sedang berjalan" (fase eksekusi/tracking). */
    public const EXECUTION_STATUSES = ['approved', 'ongoing', 'delayed'];

    /**
     * Status yang berarti proposal SUDAH disetujui — mulai dari approved sampai
     * selesai. Dipakai menentukan tampilnya field Actual: selama masih di fase
     * proposal, Actual belum relevan.
     */
    public const POST_PROPOSAL_STATUSES = [
        'approved', 'ongoing', 'delayed', 'completion_review', 'completed',
    ];

    /**
     * Apakah proposal project ini sudah melewati approval?
     *
     * Project yang dibatalkan tidak bisa dinilai dari statusnya saja — cancel bisa
     * terjadi sebelum maupun sesudah approval — jadi riwayat statusnya ditelusuri.
     */
    public function proposalApproved(): bool
    {
        if (in_array($this->status, self::POST_PROPOSAL_STATUSES, true)) {
            return true;
        }

        return $this->status === 'cancelled'
            && $this->statusLogs()->whereIn('new_status', self::POST_PROPOSAL_STATUSES)->exists();
    }

    /** Peta status → [label, kelas badge warna]. Dipakai index & detail. */
    /**
     * Warna badge per status — SEMUA berbeda satu sama lain agar status tidak
     * tertukar saat dibaca sekilas. Dua status akhir yang negatif memakai merah:
     * Rejected (merah muda) dan Cancelled (merah tua) — sewarna tapi tetap
     * bisa dibedakan.
     *
     * Pemetaan per fase menu:
     *   Proposal       : draft, submitted, revision_required, committee_review, approved, rejected
     *   Implementation : ongoing, delayed
     *   Completion     : completion_review, completed
     *   Lintas fase    : cancelled
     */
    public const STATUS_BADGES = [
        'draft'             => ['Draft', 'bg-gray-100 text-gray-700'],
        'submitted'         => ['Submitted', 'bg-blue-100 text-blue-700'],
        'revision_required' => ['Revision Required', 'bg-amber-100 text-amber-800'],
        'committee_review'  => ['On Review', 'bg-yellow-100 text-yellow-800'],
        'approved'          => ['Approved', 'bg-green-100 text-green-700'],
        'rejected'          => ['Rejected', 'bg-red-100 text-red-700'],
        'ongoing'           => ['Ongoing', 'bg-indigo-100 text-indigo-700'],
        'delayed'           => ['Delayed', 'bg-orange-100 text-orange-700'],
        'completion_review' => ['Completion Review', 'bg-purple-100 text-purple-700'],
        'completed'         => ['Completed', 'bg-teal-100 text-teal-800'],
        'cancelled'         => ['Cancelled', 'bg-red-200 text-red-900'],
    ];

    /** Label untuk audit trail (LogsActivity). */
    public function activityLabel(): string
    {
        return $this->project_id ?? ($this->project_name ?? 'Project #' . $this->getKey());
    }

    /**
     * Status Project Shell di menu Project Shell — DITURUNKAN dari ada/tidaknya
     * shell untuk sebuah ide (lihat shellStatusFor() & ProjectController::shellIndex),
     * bukan kolom status lifecycle project:
     *   not_assigned : ide sudah approved tapi belum dibuatkan project shell
     *   draft        : shell disimpan sebagai draft (belum dibuat, bisa dihapus)
     *   assigned     : shell sudah dibuat & Leader/Sponsor ditetapkan
     *   cancelled    : project-nya dibatalkan (status lifecycle 'cancelled') —
     *                  satu-satunya status shell yang membaca projects.status,
     *                  agar baris batal tidak terhitung sebagai Assigned.
     */
    public const SHELL_STATUS_BADGES = [
        'not_assigned' => ['Not Assigned', 'bg-gray-100 text-gray-600'],
        'draft'        => ['Draft', 'bg-amber-100 text-amber-800'],
        'assigned'     => ['Assigned', 'bg-green-100 text-green-700'],
        'cancelled'    => ['Cancelled', 'bg-red-200 text-red-900'],
    ];

    /** Status shell untuk satu baris menu Project Shell (null = belum ada shell). */
    public static function shellStatusFor(?self $shell): string
    {
        if ($shell === null) {
            return 'not_assigned';
        }

        if ($shell->is_shell_draft) {
            return 'draft';
        }

        return $shell->status === 'cancelled' ? 'cancelled' : 'assigned';
    }

    /** [label, kelas badge] untuk status shell. */
    public static function shellStatusBadge(string $status): array
    {
        return self::SHELL_STATUS_BADGES[$status]
            ?? [ucwords(str_replace('_', ' ', $status)), 'bg-gray-100 text-gray-700'];
    }

    /**
     * Draft shell TIDAK PERNAH ikut pada query Project mana pun (My Project,
     * Implementation, Completion, review queue, dashboard, report, email) —
     * shell yang masih draft belum boleh dilihat Leader/Sponsor. Satu-satunya
     * pintu masuknya adalah scope withShellDrafts()/onlyShellDrafts() yang
     * dipakai menu Project Shell dan form Create Project Shell.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('excludeShellDrafts', function (Builder $query) {
            $query->where($query->qualifyColumn('is_shell_draft'), false);
        });
    }

    /** Sertakan draft shell (mis. saat melanjutkan draft di form Create Project Shell). */
    public function scopeWithShellDrafts(Builder $query): Builder
    {
        return $query->withoutGlobalScope('excludeShellDrafts');
    }

    /** HANYA draft shell. */
    public function scopeOnlyShellDrafts(Builder $query): Builder
    {
        return $query->withoutGlobalScope('excludeShellDrafts')
            ->where($query->qualifyColumn('is_shell_draft'), true);
    }

    /**
     * Peta status review → jenis approval SLA. 'submitted' ikut memakai SLA
     * project_proposal karena keputusan Project Sponsor ADALAH Layer 1 alur
     * proposal — item-nya juga tampil di Task Box Sponsor.
     */
    public const SLA_REVIEW_TYPES = [
        'submitted'         => 'project_proposal',
        'committee_review'  => 'project_proposal',
        'completion_review' => 'project_completion',
    ];

    /** Jenis approval untuk SLA sesuai status review saat ini (null bila tak sedang direview). */
    public function slaApprovalType(): ?string
    {
        return self::SLA_REVIEW_TYPES[$this->status] ?? null;
    }

    /** Kapan masuk layer review saat ini (approval terakhir, atau update terakhir). */
    public function reviewSince(): ?\Illuminate\Support\Carbon
    {
        return $this->approvals()->first()?->created_at ?? $this->updated_at;
    }

    public function isInExecution(): bool
    {
        return in_array($this->status, self::EXECUTION_STATUSES, true);
    }

    /** Kolom ber-ID user pada project. */
    protected function activityUserFields(): array
    {
        return ['project_leader_id', 'project_sponsor_id'];
    }

    /** [label, kelas warna badge] untuk status saat ini. */
    public function statusBadge(): array
    {
        return self::STATUS_BADGES[$this->status]
            ?? [ucwords(str_replace('_', ' ', $this->status)), 'bg-gray-100 text-gray-700'];
    }

    /**
     * Log pembatalan terakhir. Alasan cancel tidak punya kolom sendiri — disimpan
     * di project_status_logs.remarks dengan awalan "Cancelled: " oleh cancelProject().
     */
    public function cancellation(): ?ProjectStatusLog
    {
        return $this->statusLogs()
            ->where('new_status', 'cancelled')
            ->latest('created_at')
            ->first();
    }

    /** Alasan pembatalan tanpa awalan "Cancelled: ". */
    public function cancellationReason(): ?string
    {
        $remarks = optional($this->cancellation())->remarks;

        return $remarks === null
            ? null
            : trim(preg_replace('/^Cancelled:\s*/i', '', $remarks));
    }

    /**
     * Leader boleh mengedit proposal saat draft/revision required, ATAU saat ada
     * update request yang sudah di-approve (belum di-apply).
     */
    public function canLeaderEditProposal(User $user): bool
    {
        return $this->project_leader_id === $user->id
            && (in_array($this->status, self::EDITABLE_STATUSES, true)
                || $this->updates()->where('status', 'approved')->exists());
    }

    protected $fillable = [
        'project_id',
        'idea_id',
        'project_name',
        'project_scope',
        'expected_outcome',
        'notes',
        'project_summary',
        'project_category',
        'project_category_id',
        'status',
        'is_shell_draft',
        'current_layer',
        'actual_status',
        'project_sponsor_id',
        'project_leader_id',
    ];

    protected $casts = [
        'is_shell_draft' => 'boolean',
    ];

    public function isEditableByLeader(User $user): bool
    {
        return $this->project_leader_id === $user->id
            && in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function idea()
    {
        return $this->belongsTo(Idea::class, 'idea_id', 'idea_id');
    }

    public function sponsor()
    {
        return $this->belongsTo(User::class, 'project_sponsor_id');
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'project_leader_id');
    }

    public function category()
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function implementationPlans()
    {
        return $this->hasMany(ImplementationPlan::class)->orderBy('sequence_no');
    }

    public function indicators()
    {
        return $this->hasMany(ImplementationIndicator::class)->orderBy('sort_order');
    }

    public function budgets()
    {
        return $this->hasMany(ProjectBudget::class);
    }

    /** Total budget rencana (Σ qty × unit_price) — dihitung di sisi DB. */
    public function budgetTotal(): float
    {
        return (float) $this->budgets()
            ->selectRaw('COALESCE(SUM(qty * unit_price), 0) as t')
            ->value('t');
    }

    public function statusLogs()
    {
        // created_at DESC = terbaru di atas. Tie-break id ASC: bila beberapa log
        // ditulis pada detik yang sama (mis. approval Sponsor L1 lalu auto-approve
        // L2), urutannya tetap sesuai kejadian - L1 dulu, baru L2.
        return $this->hasMany(ProjectStatusLog::class)
            ->orderByDesc('created_at')
            ->orderBy('id');
    }

    public function approvals()
    {
        return $this->hasMany(ProjectApproval::class)->latest();
    }

    public function updates()
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    /** Anggota tim (Leader / Sponsor / Members) — berhak mengajukan update. */
    /**
     * Apakah $user boleh MENGUBAH project ini pada fase yang sedang dibuka?
     * Dipakai daftar My Project untuk memutuskan tombol pensil (edit) muncul
     * atau hanya tombol mata (lihat saja). Aturannya menyalin gate controller:
     *
     *   proposal       : canLeaderEditProposal() — Leader saat draft/revision,
     *                    atau setelah ada update request yang disetujui.
     *   implementation : authorizeTeamExecution() — anggota tim & project berjalan.
     *   completion     : tidak ada yang bisa diubah lagi.
     */
    public function isEditableInPhase(User $user, ?string $phase = null): bool
    {
        // Selain Leader, pemegang hak ubah Sponsor/Leader juga perlu membuka halaman
        // dalam mode sunting — mis. Project Sponsor yang hendak mengganti Leader.
        // Tanpa ini ia hanya mendapat ikon mata (read-only) dari daftar My Project.
        $hak = $this->leadershipRights($user);

        return match ($phase) {
            'implementation' => $this->isTeamMember($user) && $this->isInExecution(),
            'completion'     => false,
            default          => $this->canLeaderEditProposal($user) || $hak['sponsor'] || $hak['leader'],
        };
    }

    /**
     * Siapa boleh mengubah apa pada Sponsor/Leader, dan apakah langsung berlaku.
     * SATU sumber kebenaran — dipakai controller (gate & tampilan form) maupun
     * isEditableInPhase() (menentukan ikon pensil di daftar).
     *
     *   Committee layer terakhir ide : keduanya, LANGSUNG berlaku (ia penyetujunya).
     *   Admin (override.role)        : keduanya; saat project berjalan lewat approval.
     *   Project Sponsor              : hanya Project Leader, lewat approval.
     *   Project Leader               : keduanya, mengikuti fase seperti field proposal.
     *
     * @return array{sponsor:bool, leader:bool, instant:bool}
     */
    public function leadershipRights(User $user): array
    {
        $lastLayer = $this->idea
            && app(\App\Services\Idea\IdeaWorkflowService::class)->isLastLayerCommittee($this->idea, $user);

        if ($lastLayer) {
            return ['sponsor' => true, 'leader' => true, 'instant' => true];
        }

        // Peran pada PROJECT INI diperiksa lebih dulu daripada izin global. Seorang
        // admin yang kebetulan menjadi Sponsor project ini tetap tunduk pada aturan
        // Sponsor: hanya boleh mengganti Leader, dan lewat approval — bukan langsung.
        if ($this->project_sponsor_id === $user->id) {
            return ['sponsor' => false, 'leader' => true, 'instant' => false];
        }

        // Kewenangan admin hanya berlaku dalam konteks Report.
        if (\App\Support\ReportOverride::aktif()) {
            return ['sponsor' => true, 'leader' => true, 'instant' => ! $this->isInExecution()];
        }

        if ($this->canLeaderEditProposal($user)
            || ($this->project_leader_id === $user->id && $this->isInExecution())) {
            return ['sponsor' => true, 'leader' => true, 'instant' => ! $this->isInExecution()];
        }

        return ['sponsor' => false, 'leader' => false, 'instant' => false];
    }

    public function isTeamMember(User $user): bool
    {
        return $this->project_leader_id === $user->id
            || $this->project_sponsor_id === $user->id
            || $this->members()->where('user_id', $user->id)->exists();
    }

    public function businessUnitId(): ?int
    {
        return $this->idea?->business_unit_id;
    }

    public function departmentId(): ?int
    {
        return $this->idea?->department_id;
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class)->latest();
    }

    /**
     * Project yang boleh diakses user: submitter ide, member, leader,
     * sponsor, atau committee (layer mana pun) dari BU project tsb.
     */
    public function scopeRelatedTo(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('project_leader_id', $userId)
                ->orWhere('project_sponsor_id', $userId)
                ->orWhereHas('idea', fn ($i) => $i->where('user_id', $userId))
                ->orWhereHas('members', fn ($m) => $m->where('user_id', $userId))
                ->orWhereExists(function ($sub) use ($userId) {
                    $sub->selectRaw('1')
                        ->from('committee_assignments as ca')
                        ->join('ideas as ix', 'ix.idea_id', '=', 'projects.idea_id')
                        ->whereColumn('ca.business_unit_id', 'ix.business_unit_id')
                        ->where('ca.user_id', $userId);
                });
        });
    }
}
