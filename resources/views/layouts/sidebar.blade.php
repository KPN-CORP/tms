@php
    $user = Auth::user();

    // Anggota committee idea (assignment) = boleh Review Ideas & buat Project Shell,
    // tanpa perlu permission terpisah. Super Admin selalu boleh.
    $isIdeaCommittee = $user->hasRole('Super Admin')
        || \App\Models\CommitteeAssignment::where('approval_type', 'idea')
            ->where('user_id', $user->id)->exists();

    // Manage Project tampil bila user: Project Leader / Sponsor / anggota tim,
    // atau pengaju ide yang idenya sudah dijadikan project. (Super Admin selalu boleh.)
    $canManageProject = $user->hasRole('Super Admin')
        || \App\Models\Project::where(function ($q) use ($user) {
            $q->where('project_leader_id', $user->id)
                ->orWhere('project_sponsor_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id))
                ->orWhereHas('idea', fn ($i) => $i->where('user_id', $user->id));
        })->exists();

    // Review Projects tampil bila user terdaftar sebagai committee untuk review
    // project (proposal/completion) di layer mana pun. (Super Admin selalu boleh.)
    // Committee change request (team/plan/budget) dan Project Sponsor juga menilai
    // permintaan perubahan, jadi keduanya ikut membuka Task Box.
    $changeQueue = app(\App\Services\Project\ProjectChangeStagingService::class)->reviewQueueFor($user);

    $isProjectCommittee = $user->hasRole('Super Admin')
        || $changeQueue->isNotEmpty()
        || \App\Models\CommitteeAssignment::whereIn('approval_type', [
                'project_proposal', 'project_completion',
                'team_change', 'plan_indicator_change', 'budget_change',
            ])->where('user_id', $user->id)->exists();

    // Badge Task Box = jumlah item yang MENUNGGU KEPUTUSAN user ini dan benar-benar
    // BISA ia tindak — yaitu antrean di layer committee-nya sendiri (reviewQueueFor),
    // gate yang sama dengan tombol Approve/Reject (isCurrentReviewer). Angkanya
    // berkurang sendiri begitu ia approve/reject, karena item pindah layer/status.
    //
    // Super Admin TIDAK dikecualikan: Task Box-nya memang menampilkan semua ide
    // untuk oversight, tapi ia hanya boleh memutus bila terdaftar sebagai committee.
    // Jadi selama belum terdaftar, badge-nya 0 (tidak ada tugas miliknya).
    //
    // Dihitung hanya bila menunya tampil, agar tidak menambah query sia-sia.
    $ideaTaskCount = $isIdeaCommittee
        ? app(\App\Services\Idea\IdeaWorkflowService::class)->reviewQueueFor($user)->count()
        : 0;

    // Badge = antrean proposal/completion DITAMBAH permintaan perubahan yang
    // menunggu keputusan user ini.
    $projectTaskCount = $isProjectCommittee
        ? app(\App\Services\Project\ProjectApprovalWorkflowService::class)->reviewQueueFor($user)->count()
            + $changeQueue->count()
        : 0;

    // Item: [label, route, permission(null=semua login), show(override boolean), active].
    // Tampil bila route ada DAN (show!==false) DAN (permission null / user punya izin).
    $topMenu = [
        // Dashboard paling atas dan terbuka untuk SEMUA employee (permission null =
        // semua user login). Redirect non-admin di DashboardController ikut dilepas,
        // kalau tidak menunya akan memantul ke My Ideas saat diklik.
        ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'active' => ['dashboard']],
    ];

    // Menu dikelompokkan: "Judul Grup" => [ item, ... ]
    $groups = [
        'Ideas' => [
            ['label' => 'My Ideas', 'route' => null, 'permission' => 'idea.create',
                'active' => ['ideas.index', 'ideas.create', 'ideas.edit', 'projects.shell', 'projects.shell.progress'],
                'children' => [
                    ['label' => 'Ideas',         'route' => 'ideas.index',    'active' => ['ideas.index', 'ideas.create', 'ideas.edit']],
                    ['label' => 'Project Shell', 'route' => 'projects.shell', 'active' => ['projects.shell', 'projects.shell.progress']],
                ],
            ],
            ['label' => 'Task Box',       'route' => 'ideas.taskbox', 'permission' => null, 'show' => $isIdeaCommittee, 'badge' => $ideaTaskCount, 'active' => ['ideas.taskbox', 'ideas.review.*', 'projects.create']],
        ],
        'Project' => [
            ['label' => 'My Project', 'route' => null, 'permission' => null, 'show' => $canManageProject, 'active' => ['projects.index', 'projects.implementation', 'projects.completion', 'projects.show'],
                'children' => [
                    ['label' => 'Project Proposal',       'route' => 'projects.index',          'active' => ['projects.index'],          'phase' => null],
                    ['label' => 'Project Implementation', 'route' => 'projects.implementation', 'active' => ['projects.implementation'], 'phase' => 'implementation'],
                    ['label' => 'Project Completion',     'route' => 'projects.completion',     'active' => ['projects.completion'],     'phase' => 'completion'],
                ],
            ],
            ['label' => 'Task Box', 'route' => 'projects.review', 'permission' => null, 'show' => $isProjectCommittee, 'badge' => $projectTaskCount, 'active' => ['projects.review']],
        ],
        'Admin Setting' => [
            ['label' => 'Committee Assignment', 'route' => 'admin.committee.index', 'permission' => 'committee.assign', 'active' => ['admin.committee.*']],
            ['label' => 'Project Category',     'route' => 'admin.project-categories.index', 'permission' => 'project-category.manage', 'active' => ['admin.project-categories.*']],
            ['label' => 'Role Management',      'route' => 'admin.roles.index', 'permission' => 'role.manage', 'active' => ['admin.roles.*']],
            // User Management di-hide sementara (menu saja). Uncomment untuk mengaktifkan kembali.
            // ['label' => 'User Management',      'route' => 'admin.users.index', 'permission' => 'user.manage', 'active' => ['admin.users.*']],
            ['label' => 'SLA', 'route' => null, 'permission' => null,
                'show' => $user->can('sla.manage') || $user->can('reminder.manage'),
                'active' => ['admin.sla.*', 'admin.email-notifications.*'],
                'children' => [
                    ['label' => 'SLA Settings',        'route' => 'admin.sla.index',                 'permission' => 'sla.manage',      'active' => ['admin.sla.*']],
                    ['label' => 'Email Notifications', 'route' => 'admin.email-notifications.index', 'permission' => 'reminder.manage', 'active' => ['admin.email-notifications.*']],
                ],
            ],
            // Activity Log — menu di-hide (pencatatan audit tetap jalan). Uncomment untuk memunculkan.
            // ['label' => 'Activity Log',         'route' => 'admin.activity-logs.index', 'permission' => 'audit.view', 'active' => ['admin.activity-logs.*']],
        ],
        'Report' => [
            ['label' => 'Report', 'route' => 'reports.index', 'permission' => 'report.view', 'active' => ['reports.*']],
        ],
        'Guidelines' => [
            ['label' => 'Guidelines', 'route' => 'guidelines.index', 'permission' => 'guideline.view', 'active' => ['guidelines.*']],
        ]
    ];

    // Cek visibilitas satu item.
    $visible = function ($item) use ($user) {
        // Parent ber-children: route boleh null (hanya toggle), lewati cek route.
        if (empty($item['children']) && ! Route::has($item['route'])) return false;
        if (array_key_exists('show', $item) && ! $item['show']) return false;
        if (! empty($item['permission']) && ! $user->can($item['permission'])) return false;
        return true;
    };

    $itemClass    = 'flex items-center px-4 py-3 rounded-xl mb-1 transition text-gray-700 hover:bg-gray-100';
    $activeClass  = 'flex items-center px-4 py-3 rounded-xl mb-1 transition bg-red-50 text-red-700 font-semibold';
    $headingClass = 'px-4 pt-5 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider';

    // Sub-menu aktif: di halaman detail (projects.show), tentukan dari ?phase agar
    // menu tetap pada fase yang membuka detail (Proposal/Implementation/Completion).
    $currentPhase = request()->query('phase');
    $childActive  = function ($child) use ($currentPhase) {
        if (request()->routeIs('projects.show')) {
            return ($child['phase'] ?? null) === $currentPhase;
        }
        return ! empty($child['active']) && request()->routeIs(...$child['active']);
    };
@endphp

<aside x-show="sidebarOpen" x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="-ml-64 opacity-0" x-transition:enter-end="ml-0 opacity-100"
       class="w-64 shrink-0 bg-white border-r border-gray-200 h-screen flex flex-col">

    {{-- Logo --}}
    <div class="px-6 py-5 border-b">
        <h1 class="text-2xl font-bold text-red-700">TMS</h1>
        <p class="text-sm text-gray-500">Transformation Management System</p>
    </div>

    {{-- Menu (dikelompokkan dengan judul grup) — scroll internal bila menu banyak --}}
    <nav class="flex-1 min-h-0 overflow-y-auto p-4">

        {{-- Menu atas tanpa grup --}}
        @foreach($topMenu as $item)
            @if($visible($item))
                <a href="{{ route($item['route']) }}"
                   class="{{ request()->routeIs(...$item['active']) ? $activeClass : $itemClass }}">
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach

        {{-- Grup: judul hanya tampil bila ada minimal 1 item yang terlihat --}}
        @foreach($groups as $groupLabel => $items)
            @php $groupItems = array_filter($items, $visible); @endphp
            @if(count($groupItems))
                <p class="{{ $headingClass }}">{{ $groupLabel }}</p>
                @foreach($groupItems as $item)
                    @if(! empty($item['children']))
                        {{-- Item dengan sub-menu (collapsible) --}}
                        <div x-data="{ open: {{ request()->routeIs(...$item['active']) ? 'true' : 'false' }} }">
                            <button type="button" @click="open = ! open"
                                    class="{{ request()->routeIs(...$item['active']) ? $activeClass : $itemClass }} w-full justify-between">
                                <span>{{ $item['label'] }}</span>
                                <svg class="w-4 h-4 shrink-0 transition-transform" :class="open ? 'rotate-90' : ''"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak class="ml-4 pl-2 border-l border-gray-200">
                                {{-- Sub-menu ikut disaring $visible: sub-menu dgn 'permission'
                                     hanya tampil bila user memang punya izinnya. --}}
                                @foreach(array_filter($item['children'], $visible) as $child)
                                    <a href="{{ $child['route'] ? route($child['route']) : '#' }}"
                                       class="{{ $childActive($child) ? $activeClass : $itemClass }} text-sm py-2">
                                        {{ $child['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        @php $badge = (int) ($item['badge'] ?? 0); @endphp
                        <a href="{{ route($item['route']) }}"
                           class="{{ request()->routeIs(...$item['active']) ? $activeClass : $itemClass }} {{ $badge ? 'justify-between' : '' }}">
                            <span>{{ $item['label'] }}</span>
                            @if($badge > 0)
                                {{-- Jumlah tugas yang masih menunggu keputusan user. --}}
                                <span class="ml-2 shrink-0 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-600 text-white text-xs font-semibold"
                                      title="{{ $badge }} task(s) waiting for your decision">{{ $badge > 99 ? '99+' : $badge }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

    </nav>

    {{-- Footer --}}
    <div class="border-t p-4">
        {{-- Menu Profile disembunyikan untuk semua user. --}}

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full text-left px-4 py-3 rounded-xl text-red-600 hover:bg-red-50">Logout</button>
        </form>
    </div>

</aside>
