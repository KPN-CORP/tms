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
    public const EDITABLE_STATUSES = ['draft', 'revision'];

    /** Status "project sedang berjalan" (fase eksekusi/tracking). */
    public const EXECUTION_STATUSES = ['approved', 'ongoing', 'delayed'];

    /** Peta status → [label, kelas badge warna]. Dipakai index & detail. */
    /**
     * Warna badge per status — SEMUA berbeda satu sama lain agar status tidak
     * tertukar saat dibaca sekilas. Dua status akhir yang negatif memakai merah:
     * Rejected (merah muda) dan Cancelled (merah tua) — sewarna tapi tetap
     * bisa dibedakan.
     *
     * Pemetaan per fase menu:
     *   Proposal       : draft, submitted, revision, committee_review, approved, rejected
     *   Implementation : ongoing, delayed
     *   Completion     : completion_review, completed
     *   Lintas fase    : cancelled
     */
    public const STATUS_BADGES = [
        'draft'             => ['Draft', 'bg-gray-100 text-gray-700'],
        'submitted'         => ['Submitted', 'bg-blue-100 text-blue-700'],
        'revision'          => ['Revision Required', 'bg-amber-100 text-amber-800'],
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

    /** Peta status review → jenis approval SLA. */
    public const SLA_REVIEW_TYPES = [
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

    /* ---- Baseline Actual Implementation (draft → pending → baselined) ---- */

    public function actualIsDraft(): bool
    {
        return in_array($this->actual_status, [null, '', 'draft'], true);
    }

    public function actualIsPending(): bool
    {
        return $this->actual_status === 'pending';
    }

    public function actualIsBaselined(): bool
    {
        return $this->actual_status === 'baselined';
    }

    /** Actual boleh diedit langsung hanya oleh anggota tim, saat berjalan & masih draft. */
    public function canEditActual(User $user): bool
    {
        return $this->isInExecution() && $this->isTeamMember($user) && $this->actualIsDraft();
    }

    /** [label, kelas badge] untuk status baseline Actual. */
    public function actualStatusBadge(): array
    {
        return match ($this->actual_status) {
            'pending'   => ['Waiting Sponsor Approval', 'bg-amber-100 text-amber-700'],
            'baselined' => ['Actual Baselined', 'bg-green-100 text-green-700'],
            default     => ['Actual Draft', 'bg-gray-100 text-gray-700'],
        };
    }

    /** [label, kelas warna badge] untuk status saat ini. */
    /** Kolom ber-ID user pada project. */
    protected function activityUserFields(): array
    {
        return ['project_leader_id', 'project_sponsor_id'];
    }

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
     * Leader boleh mengedit proposal saat draft/revision, ATAU saat ada
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
        'current_layer',
        'actual_status',
        'project_sponsor_id',
        'project_leader_id',
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
        return $this->hasMany(ProjectStatusLog::class)->latest();
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
     *   implementation : authorizeTeamExecution() — anggota tim, project berjalan,
     *                    dan Actual masih draft (belum di-baseline).
     *   completion     : tidak ada yang bisa diubah lagi.
     */
    public function isEditableInPhase(User $user, ?string $phase = null): bool
    {
        return match ($phase) {
            'implementation' => $this->isTeamMember($user) && $this->isInExecution() && $this->actualIsDraft(),
            'completion'     => false,
            default          => $this->canLeaderEditProposal($user),
        };
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
