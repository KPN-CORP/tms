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
    $isProjectCommittee = $user->hasRole('Super Admin')
        || \App\Models\CommitteeAssignment::whereIn('approval_type', ['project_proposal', 'project_completion'])
            ->where('user_id', $user->id)->exists();

    // Dashboard hanya untuk admin (Admin / Super Admin).
    $isAdmin = $user->hasAnyRole(['Admin', 'Super Admin']);

    // Item: [label, route, permission(null=semua login), show(override boolean), active].
    // Tampil bila route ada DAN (show!==false) DAN (permission null / user punya izin).
    $topMenu = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'show' => $isAdmin, 'active' => ['dashboard']],
        
    ];

    // Menu dikelompokkan: "Judul Grup" => [ item, ... ]
    $groups = [
        'Ideas' => [
            ['label' => 'My Ideas',       'route' => 'ideas.index',  'permission' => 'idea.create', 'active' => ['ideas.index', 'ideas.create', 'ideas.edit']],
            ['label' => 'Task Box',       'route' => 'ideas.taskbox', 'permission' => null, 'show' => $isIdeaCommittee, 'active' => ['ideas.taskbox', 'ideas.review.*', 'projects.create']],
        ],
        'Project' => [
            ['label' => 'My Project',  'route' => 'projects.index',  'permission' => null, 'show' => $canManageProject, 'active' => ['projects.index', 'projects.show']],
            ['label' => 'Task Box', 'route' => 'projects.review', 'permission' => null, 'show' => $isProjectCommittee, 'active' => ['projects.review']],
        ],
        'Admin Setting' => [
            ['label' => 'Committee Assignment', 'route' => 'admin.committee.index', 'permission' => 'committee.assign', 'active' => ['admin.committee.*']],
            ['label' => 'Project Category',     'route' => 'admin.project-categories.index', 'permission' => 'project-category.manage', 'active' => ['admin.project-categories.*']],
            ['label' => 'Role Management',      'route' => 'admin.roles.index', 'permission' => 'role.manage', 'active' => ['admin.roles.*']],
            // User Management di-hide sementara (menu saja). Uncomment untuk mengaktifkan kembali.
            // ['label' => 'User Management',      'route' => 'admin.users.index', 'permission' => 'user.manage', 'active' => ['admin.users.*']],
            ['label' => 'SLA Setting',          'route' => 'admin.sla.index', 'permission' => 'sla.manage', 'active' => ['admin.sla.*']],
            ['label' => 'Activity Log',         'route' => 'admin.activity-logs.index', 'permission' => 'audit.view', 'active' => ['admin.activity-logs.*']],
        ],
        'Guidelines' => [
            ['label' => 'Guidelines', 'route' => 'guidelines.index', 'permission' => 'guideline.view', 'active' => ['guidelines.*']],
        ]
    ];

    // Cek visibilitas satu item.
    $visible = function ($item) use ($user) {
        if (! Route::has($item['route'])) return false;
        if (array_key_exists('show', $item) && ! $item['show']) return false;
        if ($item['permission'] && ! $user->can($item['permission'])) return false;
        return true;
    };

    $itemClass    = 'flex items-center px-4 py-3 rounded-xl mb-1 transition text-gray-700 hover:bg-gray-100';
    $activeClass  = 'flex items-center px-4 py-3 rounded-xl mb-1 transition bg-red-50 text-red-700 font-semibold';
    $headingClass = 'px-4 pt-5 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider';
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
                    <a href="{{ route($item['route']) }}"
                       class="{{ request()->routeIs(...$item['active']) ? $activeClass : $itemClass }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            @endif
        @endforeach

    </nav>

    {{-- Footer --}}
    <div class="border-t p-4">
        <a href="{{ route('profile.edit') }}"
           class="block px-4 py-3 rounded-xl hover:bg-gray-100 mb-2">Profile</a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full text-left px-4 py-3 rounded-xl text-red-600 hover:bg-red-50">Logout</button>
        </form>
    </div>

</aside>
