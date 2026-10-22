<?php

namespace App\Support\Navigation;

use App\Models\CommitteeAssignment;
use App\Models\Project;
use App\Models\User;
use App\Services\Idea\IdeaWorkflowService;
use App\Services\Project\ProjectApprovalWorkflowService;
use App\Services\Project\ProjectChangeStagingService;
use App\Services\Project\ProjectUpdateService;
use Illuminate\Support\Facades\Route;

/**
 * Menyusun menu sidebar sebagai DATA (array siap kirim ke Inertia), bukan markup.
 *
 * Dulu seluruh logika ini hidup di resources/views/layouts/sidebar.blade.php dan
 * dijalankan ulang di setiap render halaman. Sekarang satu kelas saja yang tahu
 * aturan "menu mana tampil untuk siapa", dan hasilnya di-memoisasi per request --
 * penting karena sebagian item perlu query antrean tugas.
 *
 * Catatan kewenangan: Super Admin TIDAK otomatis dianggap committee. Di luar
 * menu Report ia employee biasa (lihat App\Support\ReportOverride), jadi badge
 * Task Box-nya 0 selama ia tidak terdaftar di committee_assignments.
 */
class SidebarBuilder
{
    private ?array $cache = null;

    public function __construct(
        private readonly IdeaWorkflowService $ideaWorkflow,
        private readonly ProjectApprovalWorkflowService $projectWorkflow,
        private readonly ProjectChangeStagingService $changeStaging,
        private readonly ProjectUpdateService $updates,
    ) {
    }

    /** @return array{groups: array<int, array{key: ?string, label: ?string, items: array}>} */
    public function build(User $user): array
    {
        return $this->cache ??= ['groups' => $this->groups($user)];
    }

    /* ---- Penyusunan grup ------------------------------------------------- */

    private function groups(User $user): array
    {
        $ideaTasks = $this->ideaTaskCount($user);
        $projectTasks = $this->projectTaskCount($user);

        $definitions = [
            [null, null, [
                // Dashboard terbuka untuk semua user yang login.
                $this->item('dashboard', 'Dashboard', 'dashboard', active: ['dashboard']),
            ]],

            ['ideas', 'Ideas', [
                $this->item('myIdeas', 'My Ideas', null, permission: 'idea.create',
                    active: ['ideas.index', 'ideas.create', 'ideas.edit', 'projects.shell', 'projects.shell.progress'],
                    children: [
                        $this->item('ideas', 'Ideas', 'ideas.index', active: ['ideas.index', 'ideas.create', 'ideas.edit']),
                        $this->item('projectShell', 'Project Shell', 'projects.shell', active: ['projects.shell', 'projects.shell.progress']),
                    ]),
                $this->item('ideaTaskBox', 'Task Box', 'ideas.taskbox',
                    show: $this->isIdeaCommittee($user),
                    badge: $ideaTasks,
                    active: ['ideas.taskbox', 'ideas.review.*', 'projects.create']),
            ]],

            ['project', 'Project', [
                $this->item('myProject', 'My Project', null,
                    show: $this->canManageProject($user),
                    active: ['projects.index', 'projects.implementation', 'projects.completion', 'projects.show'],
                    children: [
                        $this->item('projectProposal', 'Project Proposal', 'projects.index', active: ['projects.index'], phase: null),
                        $this->item('projectImplementation', 'Project Implementation', 'projects.implementation', active: ['projects.implementation'], phase: 'implementation'),
                        $this->item('projectCompletion', 'Project Completion', 'projects.completion', active: ['projects.completion'], phase: 'completion'),
                    ]),
                $this->item('projectTaskBox', 'Task Box', 'projects.review',
                    show: $this->isProjectCommittee($user, $projectTasks),
                    badge: $projectTasks,
                    active: ['projects.review']),
            ]],

            ['adminSetting', 'Admin Setting', [
                $this->item('committeeAssignment', 'Committee Assignment', 'admin.committee.index', permission: 'committee.assign', active: ['admin.committee.*']),
                $this->item('projectCategory', 'Project Category', 'admin.project-categories.index', permission: 'project-category.manage', active: ['admin.project-categories.*']),
                $this->item('roleManagement', 'Role Management', 'admin.roles.index', permission: 'role.manage', active: ['admin.roles.*']),
                // User Management sengaja disembunyikan -- lihat CLAUDE.md (User
                // ada di koneksi kpncorp yang read-only).
                $this->item('sla', 'SLA', null,
                    show: $user->can('sla.manage') || $user->can('reminder.manage'),
                    active: ['admin.sla.*', 'admin.email-notifications.*'],
                    children: [
                        $this->item('slaSettings', 'SLA Settings', 'admin.sla.index', permission: 'sla.manage', active: ['admin.sla.*']),
                        $this->item('emailNotifications', 'Email Notifications', 'admin.email-notifications.index', permission: 'reminder.manage', active: ['admin.email-notifications.*']),
                    ]),
            ]],

            ['report', 'Report', [
                $this->item('report', 'Report', 'reports.index', permission: 'report.view', active: ['reports.*']),
            ]],

            ['guidelines', 'Guidelines', [
                $this->item('guidelines', 'Guidelines', 'guidelines.index', permission: 'guideline.view', active: ['guidelines.*']),
            ]],
        ];

        $groups = [];

        foreach ($definitions as [$key, $label, $items]) {
            $visible = $this->resolveItems($items, $user);

            // Judul grup hanya muncul bila ada minimal satu item yang terlihat.
            if ($visible !== []) {
                $groups[] = ['key' => $key, 'label' => $label, 'items' => $visible];
            }
        }

        return $groups;
    }

    /* ---- Satu item ------------------------------------------------------- */

    /**
     * Definisi mentah satu item menu. Diterjemahkan jadi href/active oleh
     * resolveItems(); dipisah agar daftar menu di atas tetap terbaca.
     *
     * $key adalah kunci terjemahan di resources/js/locales/*.ts (bagian `nav`).
     * $label tetap dikirim sebagai teks cadangan -- menu baru yang belum punya
     * terjemahan tampil dalam bahasa Inggris, bukan sebagai baris kosong.
     *
     * $phase === false berarti "item ini tidak ikut aturan fase project".
     */
    private function item(
        string $key,
        string $label,
        ?string $route,
        ?string $permission = null,
        ?bool $show = null,
        int $badge = 0,
        array $active = [],
        array $children = [],
        string|false|null $phase = false,
    ): array {
        return compact('key', 'label', 'route', 'permission', 'show', 'badge', 'active', 'children', 'phase');
    }

    /** Saring item yang tidak boleh dilihat user, lalu ubah jadi bentuk siap render. */
    private function resolveItems(array $items, User $user): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if (! $this->visible($item, $user)) {
                continue;
            }

            $children = $this->resolveItems($item['children'], $user);

            // Parent tanpa route hanya berguna bila masih punya anak yang terlihat.
            if ($item['route'] === null && $children === [] && $item['children'] !== []) {
                continue;
            }

            $resolved[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'href' => $item['route'] ? route($item['route']) : null,
                'badge' => $item['badge'],
                'active' => $this->isActive($item),
                // Halaman yang masih Blade harus dibuka sebagai navigasi biasa,
                // bukan kunjungan Inertia (lihat InertiaRoutes). Parent yang
                // hanya membuka sub-menu tidak punya tujuan, jadi selalu false.
                'external' => $item['route'] !== null && ! InertiaRoutes::has($item['route']),
                'children' => $children,
            ];
        }

        return $resolved;
    }

    private function visible(array $item, User $user): bool
    {
        // Parent ber-children boleh punya route null (hanya toggle).
        if ($item['children'] === [] && ! Route::has((string) $item['route'])) {
            return false;
        }

        if ($item['show'] === false) {
            return false;
        }

        return $item['permission'] === null || $user->can($item['permission']);
    }

    /**
     * Di halaman detail project, sub-menu aktif ditentukan oleh ?phase agar menu
     * tetap menyorot fase yang membuka detail (Proposal/Implementation/Completion).
     */
    private function isActive(array $item): bool
    {
        if ($item['phase'] !== false && request()->routeIs('projects.show')) {
            return $item['phase'] === request()->query('phase');
        }

        return $item['active'] !== [] && request()->routeIs(...$item['active']);
    }

    /* ---- Hak & antrean tugas --------------------------------------------- */

    private function isIdeaCommittee(User $user): bool
    {
        return CommitteeAssignment::where('approval_type', 'idea')
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Leader / Sponsor / anggota tim, atau pengaju ide yang sudah jadi project. */
    private function canManageProject(User $user): bool
    {
        return Project::where(function ($q) use ($user) {
            $q->where('project_leader_id', $user->id)
                ->orWhere('project_sponsor_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id))
                ->orWhereHas('idea', fn ($i) => $i->where('user_id', $user->id));
        })->exists();
    }

    /**
     * Jumlah item yang menunggu keputusan user DAN benar-benar bisa ia tindak --
     * gate yang sama dengan tombol Approve/Reject, jadi badge turun sendiri
     * begitu ia memutus. Dihitung hanya bila menunya memang tampil.
     */
    private function ideaTaskCount(User $user): int
    {
        return $this->isIdeaCommittee($user)
            ? $this->ideaWorkflow->reviewQueueFor($user)->count()
            : 0;
    }

    /** Antrean proposal/completion + permintaan perubahan + update request legacy. */
    private function projectTaskCount(User $user): int
    {
        return $this->projectWorkflow->reviewQueueFor($user)->count()
            + $this->changeStaging->reviewQueueFor($user)->count()
            + $this->updates->reviewQueueFor($user)->count();
    }

    /**
     * Task Box project tampil bila user committee di layer mana pun, ATAU sedang
     * punya tugas -- yang terakhir membuka Task Box untuk Sponsor non-committee.
     */
    private function isProjectCommittee(User $user, int $taskCount): bool
    {
        return $taskCount > 0
            || CommitteeAssignment::whereIn('approval_type', [
                'project_proposal', 'project_completion',
                'team_change', 'plan_indicator_change', 'budget_change',
            ])->where('user_id', $user->id)->exists();
    }
}
