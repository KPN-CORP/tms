<?php

namespace App\Services\Idea;

use App\Models\CommitteeAssignment;
use App\Models\Idea;
use App\Models\IdeaApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Alur review ide multi-layer (waterfall).
 *
 * submitted -> (layer 1 buka) review -> layer approve -> layer berikutnya
 *           -> layer terakhir approve -> approved
 *           -> reject di layer mana pun -> rejected (routing berhenti)
 *
 * T-95: committee routing di-tie dengan Business Unit + Unit (Department).
 * Assignment department-specific (department_id = department ide) mengalahkan
 * assignment BU-wide (department_id NULL). Bila tidak ada yang department-specific
 * untuk department ide, dipakai chain BU-wide.
 */
class IdeaWorkflowService
{
    /** Catatan pada approval otomatis (pengaju = committee layer tsb). */
    public const AUTO_APPROVE_NOTE = 'Auto-approved: the submitter is the assigned committee for this layer.';

    /** Batas aman jumlah layer yang boleh dilewati otomatis. */
    private const MAX_AUTO_SKIP = 20;

    /**
     * Department efektif untuk routing ide: department ide bila ada assignment
     * department-specific, selain itu NULL (pakai chain BU-wide).
     */
    public function effectiveDepartment(Idea $idea): ?int
    {
        $hasSpecific = CommitteeAssignment::where('approval_type', 'idea')
            ->where('business_unit_id', $idea->business_unit_id)
            ->where('department_id', $idea->department_id)
            ->exists();

        return $hasSpecific ? $idea->department_id : null;
    }

    /** Query committee efektif (BU + department efektif) untuk sebuah ide. */
    private function committeeQuery(Idea $idea): Builder
    {
        $dept = $this->effectiveDepartment($idea);

        return CommitteeAssignment::where('approval_type', 'idea')
            ->where('business_unit_id', $idea->business_unit_id)
            ->when(
                $dept === null,
                fn ($q) => $q->whereNull('department_id'),
                fn ($q) => $q->where('department_id', $dept)
            );
    }

    /** Layer terakhir (tertinggi) pada chain committee efektif ide ini. */
    public function maxLayer(Idea $idea): ?int
    {
        return $this->committeeQuery($idea)->max('layer');
    }

    /**
     * Chain committee efektif ide: koleksi ['layer','user_id','name'] urut layer.
     * name = nama user (hcis). Dipakai untuk tooltip Layer di Task Box.
     */
    public function committeeChain(Idea $idea): \Illuminate\Support\Collection
    {
        $rows  = $this->committeeQuery($idea)->orderBy('layer')->get(['layer', 'user_id']);
        $names = User::whereIn('id', $rows->pluck('user_id')->unique())->pluck('name', 'id');

        return $rows->map(fn ($r) => [
            'layer'   => (int) $r->layer,
            'user_id' => (int) $r->user_id,
            'name'    => $names[$r->user_id] ?? ('User #' . $r->user_id),
        ])->values();
    }

    /**
     * Peserta committee PER LAYER untuk sebuah ide (untuk tooltip Layer).
     * Menggabungkan HISTORI aktual (idea_approvals: siapa yang benar-benar
     * approve/reject di tiap layer) dengan config committee efektif saat ini
     * (untuk layer yang belum diputus / belum tercapai). Ini menghindari kasus
     * "current_layer=3 tapi chain config cuma 1 layer" karena config berubah
     * setelah ide berjalan. Nama diambil dari hcis; bila tak ada (data user
     * lama), fallback ke tabel users tm_system.
     *
     * @return \Illuminate\Support\Collection<int, array{layer:int,user_id:?int,name:string}>
     */
    public function layerParticipants(Idea $idea): \Illuminate\Support\Collection
    {
        // Histori aktual: layer => approval terakhir di layer itu.
        $approvals = IdeaApproval::where('idea_id', $idea->id)
            ->orderBy('created_at')->get()->groupBy('layer')->map(fn ($g) => $g->last());

        // Config committee efektif saat ini: layer => assignment.
        $committee = $this->committeeQuery($idea)->orderBy('layer')->get()->keyBy('layer');

        $layers = collect($approvals->keys())
            ->merge($committee->keys())
            ->push($idea->current_layer)
            ->map(fn ($l) => (int) $l)
            ->filter(fn ($l) => $l >= 1)
            ->unique()->sort()->values();

        // Prioritas: approver aktual di layer itu; kalau belum ada → committee config.
        $pick = fn ($l) => optional($approvals->get($l))->user_id ?? optional($committee->get($l))->user_id;

        $ids = $layers->map($pick)->filter()->unique()->values();

        // Label "Fullname - employee_id" dari hcis; fallback ke users tm_system (legacy).
        $labels = User::whereIn('id', $ids->all())->get(['id', 'name', 'employee_id'])
            ->mapWithKeys(fn ($u) => [$u->id => $this->nameWithEmp($u->name, $u->employee_id)]);
        $missing = $ids->reject(fn ($id) => $labels->has($id));
        if ($missing->isNotEmpty()) {
            DB::connection('mysql')->table('users')->whereIn('id', $missing->all())->get(['id', 'name', 'employee_id'])
                ->each(fn ($u) => $labels->put($u->id, $this->nameWithEmp($u->name, $u->employee_id)));
        }

        return $layers->map(function ($l) use ($pick, $labels) {
            $uid = $pick($l);

            return [
                'layer'   => $l,
                'user_id' => $uid ? (int) $uid : null,
                'name'    => $uid ? $labels->get($uid, '#' . $uid) : '—',
            ];
        })->values();
    }

    /** Gabungkan "Fullname - employee_id" (employee_id opsional). */
    private function nameWithEmp(?string $name, $empId): string
    {
        $name  = trim((string) $name);
        $empId = trim((string) $empId);

        return $empId !== '' ? ($name . ' - ' . $empId) : $name;
    }

    /** Apakah $user committee di layer terakhir chain efektif ide ini. */
    public function isLastLayerCommittee(Idea $idea, User $user): bool
    {
        $max = $this->maxLayer($idea);

        return $max && (clone $this->committeeQuery($idea))
            ->where('layer', $max)
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Apakah user adalah committee yang menangani layer ide saat ini. */
    public function isCurrentReviewer(Idea $idea, User $user): bool
    {
        return in_array($idea->status, ['submitted', 'review'])
            && (clone $this->committeeQuery($idea))
                ->where('layer', $idea->current_layer)
                ->where('user_id', $user->id)
                ->exists();
    }

    /** FR-078: begitu dibuka committee layer aktif, status jadi On Review. */
    public function markOnReview(Idea $idea): void
    {
        if ($idea->status === 'submitted') {
            $idea->update(['status' => 'review']);
        }
    }

    public function approve(Idea $idea, User $user, ?string $note = null): void
    {
        IdeaApproval::create([
            'idea_id'  => $idea->id,
            'layer'    => $idea->current_layer,
            'user_id'  => $user->id,
            'decision' => 'approve',
            'note'     => $note,
        ]);

        $next = $idea->current_layer + 1;

        $hasNextLayer = (clone $this->committeeQuery($idea))
            ->where('layer', $next)
            ->exists();

        if ($hasNextLayer) {
            $idea->update(['current_layer' => $next, 'status' => 'review']);
        } else {
            $idea->update(['status' => 'approved']);
        }
    }

    /**
     * Auto-approve layer yang approver-nya adalah PENGAJU ide itu sendiri.
     *
     * Dipanggil tepat setelah ide disubmit. Selama committee di layer aktif
     * adalah si pengaju, layer itu langsung di-approve (tercatat di
     * idea_approvals sebagai jejak) dan routing lanjut ke layer berikutnya —
     * sehingga ide langsung masuk Task Box layer selanjutnya. Bila SEMUA layer
     * dipegang pengaju, ide langsung berstatus approved.
     */
    public function autoApproveSubmitterLayers(Idea $idea): void
    {
        $submitter = $idea->user_id ? User::find($idea->user_id) : null;
        if (! $submitter) {
            return;
        }

        $skipped = 0;

        // Guard: chain committee paling dalam pun terbatas; cegah loop tak berujung
        // bila config layer aneh (mis. duplikat layer).
        for ($i = 0; $i < self::MAX_AUTO_SKIP; $i++) {
            if (! in_array($idea->status, ['submitted', 'review'], true)) {
                break; // sudah approved/rejected
            }

            $isSelf = (clone $this->committeeQuery($idea))
                ->where('layer', $idea->current_layer)
                ->where('user_id', $submitter->id)
                ->exists();

            if (! $isSelf) {
                break;
            }

            $this->approve($idea, $submitter, self::AUTO_APPROVE_NOTE);
            $idea->refresh();
            $skipped++;
        }

        // approve() menandai 'review' saat maju layer. Kembalikan ke 'submitted'
        // agar FR-078 tetap berlaku: status baru jadi "On Review" ketika committee
        // layer berikutnya benar-benar membuka idenya.
        if ($skipped > 0 && $idea->status === 'review') {
            $idea->update(['status' => 'submitted']);
        }
    }

    public function reject(Idea $idea, User $user, ?string $note = null): void
    {
        IdeaApproval::create([
            'idea_id'  => $idea->id,
            'layer'    => $idea->current_layer,
            'user_id'  => $user->id,
            'decision' => 'reject',
            'note'     => $note,
        ]);

        $idea->update(['status' => 'rejected']);
    }

    /**
     * Query ide yang menunggu review oleh $user (di layer-nya masing-masing).
     * Match assignment department-specific ATAU BU-wide (bila tak ada yang
     * department-specific untuk department ide tsb) — "specific overrides general".
     */
    public function reviewQueueFor(User $user): Builder
    {
        return Idea::query()
            ->whereIn('status', ['submitted', 'review'])
            ->whereExists(function ($q) use ($user) {
                $q->selectRaw('1')
                    ->from('committee_assignments as ca')
                    ->where('ca.approval_type', 'idea')
                    ->whereColumn('ca.business_unit_id', 'ideas.business_unit_id')
                    ->whereColumn('ca.layer', 'ideas.current_layer')
                    ->where('ca.user_id', $user->id)
                    ->where(function ($w) {
                        $w->whereColumn('ca.department_id', 'ideas.department_id')
                            ->orWhere(function ($ww) {
                                $ww->whereNull('ca.department_id')
                                    ->whereNotExists(function ($s) {
                                        $s->selectRaw('1')
                                            ->from('committee_assignments as cs')
                                            ->where('cs.approval_type', 'idea')
                                            ->whereColumn('cs.business_unit_id', 'ideas.business_unit_id')
                                            ->whereColumn('cs.department_id', 'ideas.department_id');
                                    });
                            });
                    });
            });
    }

    /**
     * Query ide untuk Task Box: SEMUA ide yang PERNAH mencapai layer committee
     * $user (ca.layer <= ideas.current_layer), apa pun statusnya (submitted/
     * review/approved/rejected). Sekali ide masuk ke akun user sebagai committee,
     * ia tetap tampil setelah di-approve/reject/lanjut layer. Ide yang belum /
     * tidak pernah mencapai layer user (mis. ditolak di layer bawah) tidak muncul.
     * Aturan department: specific (department_id = department ide) mengalahkan BU-wide.
     */
    public function taskBoxQueueFor(User $user): Builder
    {
        return Idea::query()
            ->whereExists(function ($q) use ($user) {
                $q->selectRaw('1')
                    ->from('committee_assignments as ca')
                    ->where('ca.approval_type', 'idea')
                    ->whereColumn('ca.business_unit_id', 'ideas.business_unit_id')
                    ->whereColumn('ca.layer', '<=', 'ideas.current_layer') // ide sudah mencapai layer user
                    ->where('ca.user_id', $user->id)
                    ->where(function ($w) {
                        $w->whereColumn('ca.department_id', 'ideas.department_id')
                            ->orWhere(function ($ww) {
                                $ww->whereNull('ca.department_id')
                                    ->whereNotExists(function ($s) {
                                        $s->selectRaw('1')
                                            ->from('committee_assignments as cs')
                                            ->where('cs.approval_type', 'idea')
                                            ->whereColumn('cs.business_unit_id', 'ideas.business_unit_id')
                                            ->whereColumn('cs.department_id', 'ideas.department_id');
                                    });
                            });
                    });
            });
    }
}
