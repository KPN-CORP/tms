<?php

namespace App\Services\Project;

use App\Models\CommitteeAssignment;
use App\Models\ImplementationIndicator;
use App\Models\ImplementationPlan;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectMember;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staging perubahan data PROPOSAL saat project sudah berjalan.
 *
 * Alur: Leader mengedit di halaman -> perubahan TIDAK langsung berlaku, melainkan
 * ditampung sebagai ProjectUpdate berstatus 'draft'. Menekan "Update Project"
 * mengubahnya menjadi 'pending' dan mengarahkannya ke committee sesuai section:
 *
 *   Team Members                      -> team_change
 *   Implementation Plan & Indicator   -> plan_indicator_change
 *   Budget                            -> budget_change
 *
 * Bila committee untuk jenis itu belum dikonfigurasi, permintaan jatuh ke Project
 * Sponsor supaya tidak menggantung tanpa penanggung jawab.
 *
 * Data ACTUAL tidak lewat sini — realisasi lapangan tetap memakai alur baseline
 * (Submit Actual -> Sponsor Approve) yang sudah ada.
 */
class ProjectChangeStagingService
{
    /**
     * Penanda layer untuk leadership_change. Bukan layer committee_assignments —
     * penyetujunya committee layer terakhir ide, jadi nilainya sekadar penanda.
     */
    private const LAYER_IDE_TERAKHIR = 1;

    /** Section halaman -> approval_type pada committee_assignments. */
    public const SECTION_TYPE = [
        'team'           => 'team_change',
        'plan_indicator' => 'plan_indicator_change',
        'budget'         => 'budget_change',
        'leadership'     => 'leadership_change',
    ];

    /** Label manusiawi untuk pesan & tampilan. */
    public const TYPE_LABEL = [
        'team_change'           => 'Team Change',
        'plan_indicator_change' => 'Plan & Indicator Change',
        'budget_change'         => 'Budget Change',
        'leadership_change'     => 'Project Sponsor & Leader Change',
    ];

    /** Model yang boleh di-stage, dipetakan ke section-nya. */
    private const MODEL_SECTION = [
        ProjectMember::class          => 'team',
        ImplementationPlan::class     => 'plan_indicator',
        ImplementationIndicator::class => 'plan_indicator',
        ProjectBudget::class          => 'budget',
        // Sponsor & Leader adalah kolom pada baris project itu sendiri.
        Project::class                => 'leadership',
    ];

    public function sectionOf(string $modelClass): ?string
    {
        return self::MODEL_SECTION[$modelClass] ?? null;
    }

    /**
     * Simpan perubahan sebagai draft. Satu draft per (project, change_type);
     * perubahan berikutnya digabungkan ke draft yang sama agar tidak menumpuk.
     */
    public function stage(Project $project, User $user, string $modelClass, int $modelId, array $changes, array $before = []): ?ProjectUpdate
    {
        $section = $this->sectionOf($modelClass);
        if (! $section || ! $changes) {
            return null;
        }

        $type = self::SECTION_TYPE[$section];

        return DB::transaction(function () use ($project, $user, $type, $modelClass, $modelId, $changes, $before) {
            // Draft baru maupun permintaan yang dikembalikan untuk revisi sama-sama
            // menampung suntingan berikutnya, sehingga hasil revisi menyatu di
            // permintaan yang sama (bukan membuat permintaan baru).
            $draft = ProjectUpdate::where('project_id', $project->id)
                ->where('change_type', $type)
                ->whereIn('status', ['draft', 'revision_required'])
                ->lockForUpdate()
                ->first();

            $payload = $draft?->payload ?? [];
            $awal    = $draft?->payload_before ?? [];
            $key     = $modelClass . '#' . $modelId;

            // Gabungkan dgn perubahan sebelumnya pada baris yang sama.
            $payload[$key] = array_merge($payload[$key] ?? [], $changes);

            // Nilai SEBELUM hanya dicatat sekali per field: yang benar-benar asli,
            // bukan hasil suntingan sebelumnya di sesi yang sama.
            $awal[$key] = array_merge($before, $awal[$key] ?? []);

            if ($draft) {
                $draft->update([
                    'payload'        => $payload,
                    'payload_before' => $awal,
                    'requested_by'   => $user->id,
                ]);

                return $draft;
            }

            return ProjectUpdate::create([
                'project_id'     => $project->id,
                'requested_by'   => $user->id,
                'change_type'    => $type,
                'description'    => self::TYPE_LABEL[$type] . ' (pending submission)',
                'approver_role'  => 'sponsor',   // ditentukan ulang saat submit
                'status'         => 'draft',
                'payload'        => $payload,
                'payload_before' => $awal,
            ]);
        });
    }

    /**
     * Permintaan yang belum dikirim: draft baru DAN yang dikembalikan penilai
     * untuk revisi — keduanya menunggu Leader menekan "Update Project".
     */
    public function drafts(Project $project)
    {
        return ProjectUpdate::where('project_id', $project->id)
            ->whereIn('status', ['draft', 'revision_required'])
            ->get();
    }

    /**
     * Kirim semua draft menjadi permintaan approval — SATU permintaan per jenis.
     * Mengembalikan daftar jenis yang berhasil dikirim.
     */
    public function submitDrafts(Project $project, User $user, ?string $note = null, ?string $hanyaType = null): array
    {
        $terkirim = [];

        $daftar = $hanyaType
            ? $this->drafts($project)->where('change_type', $hanyaType)
            : $this->drafts($project);

        foreach ($daftar as $draft) {
            // Hasil revisi diperiksa ulang dari layer pertama agar layer yang sudah
            // menyetujui sebelumnya tidak terlewat menilai perubahan barunya.
            [$role, $layer] = $this->routing($project, $draft->change_type);

            $draft->update([
                'status'          => 'pending',
                'approver_role'   => $role,
                'current_layer'   => $layer,
                'description'     => $note ?: (self::TYPE_LABEL[$draft->change_type] ?? $draft->change_type),
                'snapshot_before' => app(ProjectUpdateService::class)->snapshot($project),
            ]);

            // Riwayat: siapa mengajukan, atas nama siapa, dan APA yang diubah.
            $this->catat($project, $user, sprintf(
                '%s submitted: %s. Waiting for %s.',
                self::TYPE_LABEL[$draft->change_type] ?? $draft->change_type,
                $this->ringkasPerubahan($draft),
                $this->sebutPenyetuju($project, $draft)
            ));

            $terkirim[] = self::TYPE_LABEL[$draft->change_type] ?? $draft->change_type;
        }

        return $terkirim;
    }

    /**
     * Tentukan penilai: committee sesuai jenis (mulai layer terkecil), atau
     * Sponsor bila committee jenis itu belum dikonfigurasi.
     *
     * @return array{0:string,1:int} [approver_role, layer]
     */
    public function routing(Project $project, string $type): array
    {
        // Penggantian Sponsor/Leader tidak lewat committee_assignments: yang berhak
        // memutus adalah committee layer TERAKHIR dari ide asal project ini.
        if ($type === 'leadership_change') {
            return $this->ideaCommitteeApprover($project)
                ? ['idea_committee', self::LAYER_IDE_TERAKHIR]
                // Belum ada committee layer terakhir -> permintaan MENGGANTUNG,
                // tersimpan tanpa penyetuju sampai orangnya ditentukan.
                : ['unassigned', self::LAYER_IDE_TERAKHIR];
        }

        // Layer 1 SELALU Project Sponsor. Committee (bila ada) menyusul dari Layer 2.
        return ['sponsor', CommitteeAssignment::SPONSOR_LAYER];
    }

    /**
     * Committee layer TERAKHIR dari ide asal project — penyetuju perubahan
     * Sponsor/Leader. NULL bila ide tak punya committee sama sekali.
     */
    public function ideaCommitteeApprover(Project $project): ?User
    {
        $idea = $project->idea;
        if (! $idea) {
            return null;
        }

        $wf  = app(\App\Services\Idea\IdeaWorkflowService::class);
        $max = $wf->maxLayerFor($idea);

        if (! $max) {
            return null;
        }

        $id = $wf->committeeUserIdAt($idea, $max);

        return $id ? User::find($id) : null;
    }

    /** Layer committee berikutnya setelah $layer (mengabaikan Layer 1 milik Sponsor). */
    private function nextCommitteeLayer(Project $project, string $type, int $layer): ?int
    {
        $next = app(ProjectApprovalWorkflowService::class)
            ->committeeLayersFor($project, $type)
            ->pluck('layer')
            ->map(fn ($l) => (int) $l)
            ->filter(fn ($l) => $l > max($layer, CommitteeAssignment::SPONSOR_LAYER))
            ->min();

        return $next ? (int) $next : null;
    }

    /** Apakah $user penilai permintaan ini pada layer yang sedang aktif? */
    public function isReviewer(ProjectUpdate $update, User $user): bool
    {
        if ($update->status !== 'pending') {
            return false;
        }

        $project = $update->project;

        // Menggantung: belum ada penyetuju sama sekali, jadi tak seorang pun bisa memutus.
        if ($update->approver_role === 'unassigned') {
            return false;
        }

        // Perubahan Sponsor/Leader: committee layer terakhir ide asal project.
        if ($update->approver_role === 'idea_committee') {
            $approver = $this->ideaCommitteeApprover($project);

            return $approver !== null && $approver->id === $user->id;
        }

        // Layer 1 = Project Sponsor; layer berikutnya = committee sesuai konfigurasi.
        if ((int) $update->current_layer === CommitteeAssignment::SPONSOR_LAYER) {
            return $project->project_sponsor_id === $user->id;
        }

        return app(ProjectApprovalWorkflowService::class)
            ->committeeLayersFor($project, $update->change_type)
            ->where('layer', $update->current_layer)
            ->pluck('user_id')
            ->contains($user->id);
    }

    /**
     * Setujui satu layer. Bila masih ada layer berikutnya, permintaan lanjut;
     * bila sudah layer terakhir, payload DITERAPKAN ke baris aslinya.
     */
    public function approve(ProjectUpdate $update, User $user, ?string $note = null): string
    {
        $project = $update->project;

        // Perubahan Sponsor/Leader hanya satu lapis (committee layer terakhir ide),
        // jadi persetujuannya langsung menerapkan payload.
        $next = $update->change_type === 'leadership_change'
            ? null
            : $this->nextCommitteeLayer($project, $update->change_type, (int) $update->current_layer);

        if ($next !== null) {
            $update->update([
                'current_layer' => $next,
                'approver_role' => 'committee',
                'reviewed_by'   => $user->id,
                'review_note'   => $note,
            ]);

            $this->catat($project, $user, sprintf(
                '%s approved at layer %d, continuing to layer %d.',
                self::TYPE_LABEL[$update->change_type] ?? $update->change_type,
                (int) $update->current_layer,
                $next
            ), $note);

            return 'forwarded';
        }

        $this->applyPayload($update);

        $this->catat($project, $user, sprintf(
            '%s approved and applied: %s.',
            self::TYPE_LABEL[$update->change_type] ?? $update->change_type,
            $this->ringkasPerubahan($update)
        ), $note);

        $update->update([
            'status'         => 'applied',
            'reviewed_by'    => $user->id,
            'review_note'    => $note,
            'snapshot_after' => app(ProjectUpdateService::class)->snapshot($project->fresh()),
        ]);

        return 'applied';
    }

    /**
     * Kembalikan ke pengaju untuk diperbaiki. Tersedia di SETIAP layer approval.
     * Payload sengaja dipertahankan supaya Leader menyunting dari perubahan yang
     * sudah dibuat, bukan mengulang dari nol.
     */
    public function requestRevision(ProjectUpdate $update, User $user, string $note): void
    {
        $update->update([
            'status'      => 'revision_required',
            'reviewed_by' => $user->id,
            'review_note' => $note,
        ]);

        $this->catat($update->project, $user, sprintf(
            '%s returned for revision at layer %d.',
            self::TYPE_LABEL[$update->change_type] ?? $update->change_type,
            (int) $update->current_layer
        ), $note);
    }

    /**
     * Perubahan yang BELUM berlaku, dikelompokkan per baris data:
     *
     *   ["App\\Models\\ImplementationPlan#27" => [
     *        'status' => 'draft',
     *        'fields' => ['actual_start' => '2026-09-01', 'remarks' => 'yes'],
     *   ]]
     *
     * Dipakai tabel detail untuk menampilkan nilai yang sedang menunggu approval,
     * supaya isian user tidak terlihat "hilang" hanya karena belum disetujui.
     */
    public function pendingByRow(Project $project): array
    {
        $hasil = [];

        $updates = ProjectUpdate::where('project_id', $project->id)
            ->whereIn('status', ['draft', 'revision_required', 'pending'])
            ->get();

        foreach ($updates as $u) {
            foreach ((array) $u->payload as $key => $fields) {
                $hasil[$key] = [
                    'status' => $u->status,
                    'fields' => array_merge($hasil[$key]['fields'] ?? [], (array) $fields),
                ];
            }
        }

        return $hasil;
    }

    /** Penyetuju yang sedang memegang permintaan ini (null bila menggantung). */
    public function approverOf(ProjectUpdate $update): ?User
    {
        $project = $update->project;

        if ($update->approver_role === 'unassigned') {
            return null;
        }

        if ($update->approver_role === 'idea_committee') {
            return $this->ideaCommitteeApprover($project);
        }

        if ((int) $update->current_layer === CommitteeAssignment::SPONSOR_LAYER) {
            return $project->project_sponsor_id ? User::find($project->project_sponsor_id) : null;
        }

        $id = app(ProjectApprovalWorkflowService::class)
            ->committeeLayersFor($project, $update->change_type)
            ->firstWhere('layer', $update->current_layer)?->user_id;

        return $id ? User::find($id) : null;
    }

    /** Permintaan perubahan yang menunggu keputusan $user (untuk Task Box). */
    public function reviewQueueFor(User $user)
    {
        return ProjectUpdate::with('project')
            ->where('status', 'pending')
            ->whereIn('change_type', array_keys(self::TYPE_LABEL))
            ->get()
            ->filter(fn ($u) => $u->project && $this->isReviewer($u, $user))
            ->values();
    }

    /** Permintaan pada satu project yang boleh dinilai $user. */
    public function reviewableOn(Project $project, User $user)
    {
        return ProjectUpdate::where('project_id', $project->id)
            ->where('status', 'pending')
            ->whereIn('change_type', array_keys(self::TYPE_LABEL))
            ->get()
            ->filter(fn ($u) => $this->isReviewer($u, $user))
            ->values();
    }

    /**
     * Permintaan yang boleh diputus $user — sebagai penilainya sendiri, ATAU
     * mewakili penilai lain lewat izin 'override.role'.
     *
     * Permintaan yang MENGGANTUNG (belum punya penyetuju) tidak ikut: tak ada
     * seorang pun yang bisa diwakili, jadi tak seorang pun boleh memutusnya.
     */
    public function decidableOn(Project $project, User $user)
    {
        $semua = ProjectUpdate::where('project_id', $project->id)
            ->where('status', 'pending')
            ->whereIn('change_type', array_keys(self::TYPE_LABEL))
            ->get();

        return $semua->filter(function ($u) use ($user) {
            if ($this->isReviewer($u, $user)) {
                return true;
            }

            return \App\Support\ReportOverride::aktif() && $this->approverOf($u) !== null;
        })->values();
    }

    /** Id project yang punya permintaan perubahan menunggu keputusan (untuk Report). */
    public function projectIdsWithPending(): \Illuminate\Support\Collection
    {
        return ProjectUpdate::where('status', 'pending')
            ->whereIn('change_type', array_keys(self::TYPE_LABEL))
            ->pluck('project_id')
            ->unique()
            ->values();
    }

    /**
     * Perbandingan before/after per baris & per field, siap ditampilkan.
     *
     * @return array<int, array{label:string, row:string, field:string, before:mixed, after:mixed}>
     */
    public function diff(ProjectUpdate $update): array
    {
        $sebelum = (array) $update->payload_before;
        $baris   = [];

        foreach ((array) $update->payload as $key => $changes) {
            [$class, $id] = array_pad(explode('#', $key), 2, null);

            $row   = class_exists($class) ? $class::find($id) : null;
            $label = $class === Project::class
                ? 'Project'
                : ($row && method_exists($row, 'activityLabel')
                    ? $row->activityLabel()
                    : class_basename((string) $class) . ' #' . $id);

            foreach ((array) $changes as $field => $after) {
                $baris[] = [
                    'label'  => $label,
                    'row'    => $key,
                    'field'  => $this->fieldLabel($field),
                    // Field ber-id user ditampilkan sebagai NAMA agar riwayat terbaca
                    // orang, bukan angka.
                    'before' => $this->nilaiTampil($field, $sebelum[$key][$field] ?? null),
                    'after'  => $this->nilaiTampil($field, $after),
                ];
            }
        }

        return $baris;
    }

    /** Nama field jadi label yang enak dibaca: planning_start -> "Planning Start". */
    private function fieldLabel(string $field): string
    {
        // Beberapa field punya nama baku yang lebih jelas daripada hasil konversi.
        $khusus = [
            'project_sponsor_id' => 'Project Sponsor',
            'project_leader_id'  => 'Project Leader',
            'pic_user_ids'       => 'PIC',
            'user_id'            => 'Member',
        ];

        return $khusus[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    /** Terjemahkan nilai field ber-id user menjadi nama orangnya. */
    private function nilaiTampil(string $field, $value)
    {
        if ($value === null || $value === '' || ! str_contains($field, 'user_id') && ! str_ends_with($field, '_id')) {
            return $value;
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($id) => optional(User::find($id))->name ?? ('#' . $id))
                ->implode(', ');
        }

        return optional(User::find($value))->name ?? ('#' . $value);
    }

    /** Tolak: payload dibuang, data tetap seperti semula. */
    public function reject(ProjectUpdate $update, User $user, ?string $note = null): void
    {
        $ringkas = $this->ringkasPerubahan($update);

        $update->update([
            'status'      => 'rejected',
            'reviewed_by' => $user->id,
            'review_note' => $note,
            'payload'     => null,
        ]);

        $this->catat($update->project, $user, sprintf(
            '%s rejected at layer %d: %s.',
            self::TYPE_LABEL[$update->change_type] ?? $update->change_type,
            (int) $update->current_layer,
            $ringkas
        ), $note);
    }

    /**
     * Tulis satu baris riwayat pada project.
     *
     * Selalu menyebut PELAKU, dan bila ia bertindak mewakili orang lain, nama yang
     * diwakili ikut ditulis — sehingga riwayat tidak pernah menyamarkan siapa yang
     * benar-benar menekan tombol.
     */
    private function catat(Project $project, User $user, string $pesan, ?string $note = null): void
    {
        $pelaku = $user->name;

        if ($this->atasNama && $this->atasNama->id !== $user->id) {
            $pelaku .= ' on behalf of ' . $this->atasNama->name;
        }

        $project->statusLogs()->create([
            'old_status' => $project->status,
            'new_status' => $project->status,
            'changed_by' => $user->id,
            'remarks'    => trim("{$pesan} By {$pelaku}." . ($note ? " Note: {$note}" : '')),
        ]);
    }

    /** Ringkasan "apa yang diubah": "Project Sponsor: A -> B; Activity: x -> y". */
    private function ringkasPerubahan(ProjectUpdate $update): string
    {
        $bagian = [];

        foreach ($this->diff($update) as $d) {
            // Hindari penyebutan ganda: label baris "Project" + field "Project Sponsor"
            // akan terbaca "Project Project Sponsor".
            $awalan = str_starts_with($d['field'], $d['label']) ? '' : $d['label'] . ' ';

            $bagian[] = sprintf('%s%s: %s -> %s',
                $awalan, $d['field'],
                $this->nilaiTeks($d['before']), $this->nilaiTeks($d['after']));
        }

        return $bagian ? implode('; ', array_slice($bagian, 0, 10)) : 'no field changes';
    }

    /** Nama penyetuju untuk kalimat riwayat. */
    private function sebutPenyetuju(Project $project, ProjectUpdate $update): string
    {
        if ($update->approver_role === 'unassigned') {
            return 'an approver to be determined (the idea has no final-layer committee yet)';
        }

        if ($update->approver_role === 'idea_committee') {
            return optional($this->ideaCommitteeApprover($project))->name
                ?? 'the final-layer committee of the idea';
        }

        return $update->approver_role === 'sponsor'
            ? (optional($project->sponsor)->name ?? 'the Project Sponsor')
            : 'the committee of layer ' . (int) $update->current_layer;
    }

    /** Nilai untuk kalimat riwayat; id user diterjemahkan jadi nama. */
    private function nilaiTeks($value): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }

        if (is_array($value)) {
            return implode(', ', $value) ?: '(empty)';
        }

        return (string) $value;
    }

    /** Committee yang sedang diwakili pada rangkaian aksi ini (lihat actingAs). */
    private ?User $atasNama = null;

    /** Jalankan satu aksi sebagai wakil dari $atasNama. */
    public function actingAs(?User $atasNama, callable $aksi)
    {
        $sebelum = $this->atasNama;
        $this->atasNama = $atasNama;

        try {
            return $aksi();
        } finally {
            $this->atasNama = $sebelum;
        }
    }

    /** Terapkan payload ke baris aslinya. */
    private function applyPayload(ProjectUpdate $update): void
    {
        DB::transaction(function () use ($update) {
            foreach ((array) $update->payload as $key => $changes) {
                [$class, $id] = explode('#', $key);

                if (! class_exists($class) || ! $this->sectionOf($class)) {
                    continue;   // abaikan payload asing
                }

                // Project::class -> barisnya adalah project itu sendiri (bukan anak).
                if ($class === Project::class) {
                    if ((int) $id === (int) $update->project_id) {
                        Project::whereKey($id)->update($changes);
                    }

                    continue;
                }

                $row = $class::find($id);
                if ($row && $row->project_id === $update->project_id) {
                    $row->update($changes);
                }
            }
        });
    }
}
