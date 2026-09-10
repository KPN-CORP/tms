<?php

namespace App\Services\Dashboard;

use App\Models\Idea;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Definisi TUNGGAL setiap metrik Dashboard.
 *
 * Dipakai dua tempat: Dashboard (menghitung angkanya) dan Report (menampilkan
 * daftar barisnya saat kartu diklik). Karena keduanya memanggil query yang sama,
 * jumlah baris di Report dijamin persis sama dengan angka di kartu — tidak ada
 * dua definisi yang bisa berbeda diam-diam.
 */
class MetricScopeService
{
    /**
     * Peta metrik: key => [kind, label, statuses|source].
     *
     *  kind     : 'idea' | 'project' — menentukan tabel & tampilan kolom di Report.
     *  statuses : dibatasi ke status ini (null = semua).
     *  source   : 'from_my_ideas' — project yang LAHIR dari ide user (bukan dari
     *             perannya sebagai Leader/Sponsor).
     */
    public const METRICS = [
        // ---- Idea Monitoring ----
        'idea_draft' => [
            'kind' => 'idea', 'label' => 'Total Idea Draft',
            'statuses' => ['draft'],
        ],
        'idea_cumulative_submit' => [
            'kind' => 'idea', 'label' => 'Cumulative Total Submitted Ideas',
            'statuses' => ['submitted', 'review', 'approved', 'rejected', 'project_created'],
        ],
        'idea_in_submitted' => [
            'kind' => 'idea', 'label' => 'Total Idea in Submitted Status',
            'statuses' => ['submitted'],
        ],
        'idea_approved' => [
            'kind' => 'idea', 'label' => 'Total Idea Approved for Implementation',
            'statuses' => ['approved', 'project_created'],
        ],
        'idea_projects_generated' => [
            'kind' => 'project', 'label' => 'Cumulative Total Project Generated from Approved Idea',
            'source' => 'from_my_ideas',
        ],

        // ---- Project Monitoring ----
        'project_in_submitted' => [
            'kind' => 'project', 'label' => 'Total Project in Submitted Status',
            'statuses' => ['submitted'],
        ],
        'project_cumulative_appr' => [
            'kind' => 'project', 'label' => 'Cumulative Total Approved Projects',
            'statuses' => ['approved', 'ongoing', 'delayed', 'completion_review', 'completed'],
        ],
        'project_ongoing' => [
            'kind' => 'project', 'label' => 'Total Project in Ongoing Status',
            'statuses' => ['ongoing'],
        ],
        'project_delayed' => [
            'kind' => 'project', 'label' => 'Total Projects in Delayed Status',
            'statuses' => ['delayed'],
        ],
        'project_completed' => [
            'kind' => 'project', 'label' => 'Total Completed Projects',
            'statuses' => ['completed'],
        ],
        // Angkanya rata-rata, bukan cacah — daftarnya = project yang dihitung.
        'project_avg_improvement' => [
            'kind' => 'project', 'label' => 'Average % Improvement',
            'statuses' => ['completed'],
        ],
    ];

    public static function exists(?string $metric): bool
    {
        return $metric !== null && array_key_exists($metric, self::METRICS);
    }

    public static function kindOf(string $metric): string
    {
        return self::METRICS[$metric]['kind'] ?? 'idea';
    }

    public static function labelOf(string $metric): string
    {
        return self::METRICS[$metric]['label'] ?? $metric;
    }

    /**
     * Query baris untuk satu metrik.
     *
     * @param array $f ['bu' => ?string, 'unit' => ?string, 'from' => ?Carbon, 'to' => ?Carbon]
     */
    public function query(string $metric, User $user, bool $mine, array $f): Builder
    {
        $def = self::METRICS[$metric] ?? null;
        if (! $def) {
            return Idea::query()->whereRaw('1 = 0');
        }

        $query = ($def['source'] ?? null) === 'from_my_ideas'
            ? $this->projectsFromMyIdeasScope($user, $mine, $f)
            : ($def['kind'] === 'idea'
                ? $this->ideaScope($user, $mine, $f)
                : $this->projectScope($user, $mine, $f));

        if (! empty($def['statuses'])) {
            $query->whereIn('status', $def['statuses']);
        }

        return $query;
    }

    /** Ide: filter periode memakai tanggal ide dibuat. */
    public function ideaScope(User $user, bool $mine, array $f): Builder
    {
        return Idea::query()
            ->when($mine, fn ($q) => $q->where('user_id', $user->id))
            ->when($f['bu'] ?? null, fn ($q, $v) => $q->where('business_unit_name', $v))
            ->when($f['unit'] ?? null, fn ($q, $v) => $q->where('department_name', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v));
    }

    /** BU/Unit project mengikuti ide asalnya; periode memakai tanggal project dibuat. */
    public function applyProjectFilters(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['bu'] ?? null, fn ($qq, $v) => $qq->whereHas('idea', fn ($i) => $i->where('business_unit_name', $v)))
            ->when($f['unit'] ?? null, fn ($qq, $v) => $qq->whereHas('idea', fn ($i) => $i->where('department_name', $v)))
            ->when($f['from'] ?? null, fn ($qq, $v) => $qq->where('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($qq, $v) => $qq->where('created_at', '<=', $v));
    }

    /**
     * Project yang LAHIR DARI IDE user ini — dipakai kartu "Cumulative Total Project
     * Generated from Approved Idea" agar konsisten dengan angka ide di sebelahnya.
     */
    public function projectsFromMyIdeasScope(User $user, bool $mine, array $f): Builder
    {
        return $this->applyProjectFilters(
            Project::query()->when(
                $mine,
                fn ($q) => $q->whereHas('idea', fn ($i) => $i->where('user_id', $user->id))
            ),
            $f
        );
    }

    /**
     * Project milik sendiri = lahir dari ide user ini, ATAU user sebagai Project
     * Leader / Sponsor-nya. Keanggotaan committee tidak dihitung — itu peran
     * mereview, bukan "data yang dibuat sendiri".
     */
    public function projectScope(User $user, bool $mine, array $f): Builder
    {
        return $this->applyProjectFilters(
            Project::query()->when($mine, fn ($q) => $q->where(function ($w) use ($user) {
                $w->whereHas('idea', fn ($i) => $i->where('user_id', $user->id))
                    ->orWhere('project_leader_id', $user->id)
                    ->orWhere('project_sponsor_id', $user->id);
            })),
            $f
        );
    }
}
