<?php

namespace App\Services\Project;

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
    /** Section halaman -> approval_type pada committee_assignments. */
    public const SECTION_TYPE = [
        'team'           => 'team_change',
        'plan_indicator' => 'plan_indicator_change',
        'budget'         => 'budget_change',
    ];

    /** Label manusiawi untuk pesan & tampilan. */
    public const TYPE_LABEL = [
        'team_change'           => 'Team Change',
        'plan_indicator_change' => 'Plan & Indicator Change',
        'budget_change'         => 'Budget Change',
    ];

    /** Model yang boleh di-stage, dipetakan ke section-nya. */
    private const MODEL_SECTION = [
        ProjectMember::class          => 'team',
        ImplementationPlan::class     => 'plan_indicator',
        ImplementationIndicator::class => 'plan_indicator',
        ProjectBudget::class          => 'budget',
    ];

    public function sectionOf(string $modelClass): ?string
    {
        return self::MODEL_SECTION[$modelClass] ?? null;
    }

    /**
     * Simpan perubahan sebagai draft. Satu draft per (project, change_type);
     * perubahan berikutnya digabungkan ke draft yang sama agar tidak menumpuk.
     */
    public function stage(Project $project, User $user, string $modelClass, int $modelId, array $changes): ?ProjectUpdate
    {
        $section = $this->sectionOf($modelClass);
        if (! $section || ! $changes) {
            return null;
        }

        $type = self::SECTION_TYPE[$section];

        return DB::transaction(function () use ($project, $user, $type, $modelClass, $modelId, $changes) {
            $draft = ProjectUpdate::where('project_id', $project->id)
                ->where('change_type', $type)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->first();

            $payload = $draft?->payload ?? [];
            $key     = $modelClass . '#' . $modelId;

            // Gabungkan dgn perubahan sebelumnya pada baris yang sama.
            $payload[$key] = array_merge($payload[$key] ?? [], $changes);

            if ($draft) {
                $draft->update(['payload' => $payload, 'requested_by' => $user->id]);

                return $draft;
            }

            return ProjectUpdate::create([
                'project_id'    => $project->id,
                'requested_by'  => $user->id,
                'change_type'   => $type,
                'description'   => self::TYPE_LABEL[$type] . ' (pending submission)',
                'approver_role' => 'sponsor',   // ditentukan ulang saat submit
                'status'        => 'draft',
                'payload'       => $payload,
            ]);
        });
    }

    /** Draft yang belum dikirim untuk project ini. */
    public function drafts(Project $project)
    {
        return ProjectUpdate::where('project_id', $project->id)->where('status', 'draft')->get();
    }

    /**
     * Kirim semua draft menjadi permintaan approval — SATU permintaan per jenis.
     * Mengembalikan daftar jenis yang berhasil dikirim.
     */
    public function submitDrafts(Project $project, User $user, ?string $note = null): array
    {
        $terkirim = [];

        foreach ($this->drafts($project) as $draft) {
            [$role, $layer] = $this->routing($project, $draft->change_type);

            $draft->update([
                'status'          => 'pending',
                'approver_role'   => $role,
                'current_layer'   => $layer,
                'description'     => $note ?: (self::TYPE_LABEL[$draft->change_type] ?? $draft->change_type),
                'snapshot_before' => app(ProjectUpdateService::class)->snapshot($project),
            ]);

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
        $layers = app(ProjectApprovalWorkflowService::class)->committeeLayersFor($project, $type);

        if ($layers->isEmpty()) {
            return ['sponsor', 1];
        }

        return ['committee', (int) $layers->min('layer')];
    }

    /** Apakah $user penilai permintaan ini pada layer yang sedang aktif? */
    public function isReviewer(ProjectUpdate $update, User $user): bool
    {
        if ($update->status !== 'pending') {
            return false;
        }

        $project = $update->project;

        if ($update->approver_role === 'sponsor') {
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

        if ($update->approver_role === 'committee') {
            $next = app(ProjectApprovalWorkflowService::class)
                ->committeeLayersFor($project, $update->change_type)
                ->where('layer', $update->current_layer + 1);

            if ($next->isNotEmpty()) {
                $update->update(['current_layer' => $update->current_layer + 1, 'reviewed_by' => $user->id, 'review_note' => $note]);

                return 'forwarded';
            }
        }

        $this->applyPayload($update);

        $update->update([
            'status'         => 'applied',
            'reviewed_by'    => $user->id,
            'review_note'    => $note,
            'snapshot_after' => app(ProjectUpdateService::class)->snapshot($project->fresh()),
        ]);

        return 'applied';
    }

    /** Tolak: payload dibuang, data tetap seperti semula. */
    public function reject(ProjectUpdate $update, User $user, ?string $note = null): void
    {
        $update->update([
            'status'      => 'rejected',
            'reviewed_by' => $user->id,
            'review_note' => $note,
            'payload'     => null,
        ]);
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

                $row = $class::find($id);
                if ($row && $row->project_id === $update->project_id) {
                    $row->update($changes);
                }
            }
        });
    }
}
