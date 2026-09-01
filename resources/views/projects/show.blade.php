<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Project Detail</h2>
    </x-slot>

    @php
        $box   = 'w-full border rounded-lg px-4 py-2 bg-gray-50 text-gray-600';
        $inp   = 'border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200';
        $idea  = $project->idea;
        $usersById = $users->keyBy('id');
        $totalWeight = (float) $project->indicators->sum('weightage');
        // $showActual dari controller: hanya true saat detail dibuka dari konteks
        // Project Implementation (?phase=implementation) DAN project sedang berjalan.
        // Dari Project Proposal → tetap tampilan proposal (Actual disembunyikan).

        // Badge status: di view Project Proposal, project yang sudah lewat approval
        // (eksekusi/completion) tetap "Approved" — snapshot proposal, tidak mengikuti
        // progress implementation. Di view Implementation → status live.
        $badgeStatus = $project->status;
        if (($phase ?? null) !== 'implementation'
            && in_array($project->status, ['ongoing', 'delayed', 'completion_review', 'completed'], true)) {
            $badgeStatus = 'approved';
        }
        [$stLabel, $stCls] = \App\Models\Project::STATUS_BADGES[$badgeStatus]
            ?? [ucwords(str_replace('_', ' ', $badgeStatus)), 'bg-gray-100 text-gray-700'];

        // Kelas header section (tombol toggle collapse).
        $secBtn = 'w-full bg-red-800 text-white px-6 py-3 font-semibold flex items-center justify-between';
        // Kelas overlay + kartu modal (dialog Add).
        $modalWrap = 'fixed inset-0 z-[60] flex items-center justify-center';
        $modalCard = 'relative bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 p-6 max-h-[85vh] overflow-y-auto';
    @endphp

    {{-- Chevron ^ saat terbuka; putar 180° (v) saat tertutup. --}}
    @php $chevron = '<svg class="w-4 h-4 shrink-0 transition-transform" :class="open ? \'\' : \'rotate-180\'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 15l-6-6-6 6"/></svg>'; @endphp

    {{-- Helper Blade: atribut x-data/x-init collapsible yang posisinya persist.
         window.tmsSec kini global (didefinisikan di layout). Prefix 'proj.sec.'
         dipertahankan agar setelan yang sudah tersimpan di browser user tetap terbaca. --}}
    @php
        $collapsible = function (string $key, string $extra = '') {
            $k = 'proj.sec.' . $key;

            return 'x-data="{ open: window.tmsSec.get(\'' . $k . '\', true)' . ($extra ? ', ' . $extra : '') . ' }" '
                . 'x-init="$watch(\'open\', v => window.tmsSec.set(\'' . $k . '\', v))"';
        };
    @endphp

    {{-- Lebar konten seperti Committee Assignment: max-w-7xl, terpusat di tengah. --}}
    <div class="p-6 space-y-6 mx-auto w-full max-w-7xl">

        <div class="flex items-start justify-between">
            <div>
                <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to My Project</a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $project->project_name }}</h1>
                <p class="font-mono text-red-700">{{ $project->project_id }}</p>
            </div>
            <div class="text-right space-y-1">
                <span class="inline-flex px-3 py-1 text-xs rounded-full {{ $stCls }}">{{ $stLabel }}</span>
                @if($canEdit)<div><span class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-700">You are the Project Leader — you can edit</span></div>@endif
            </div>
        </div>

        @if(session('success'))<div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">{{ session('error') }}</div>@endif

        {{-- Error validasi, termasuk penolakan karena record diubah orang lain
             (concurrent edit). Tanpa ini penolakan tidak terlihat sama sekali. --}}
        @if($errors->any())
            <ul class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm list-disc list-inside">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        @endif

        {{-- Alasan Project Cancellation — diambil dari status log terakhir (new_status=cancelled). --}}
        @if($project->status === 'cancelled')
            @php $cancelLog = $project->cancellation(); @endphp
            <div class="rounded-xl bg-gray-100 border border-gray-300 px-4 py-3">
                <p class="text-sm font-semibold text-gray-700">Project Cancellation</p>
                <p class="text-sm text-gray-600 mt-1 whitespace-pre-line">{{ $project->cancellationReason() ?: 'No reason recorded.' }}</p>
                @if($cancelLog)
                    <p class="text-xs text-gray-400 mt-1">
                        Cancelled by {{ optional($cancelLog->changedBy)->name ?? 'Unknown' }}
                        &middot; <x-datetime :value="$cancelLog->created_at" />
                    </p>
                @endif
            </div>
        @endif

        {{-- 1. Idea Detail --}}
        <div class="bg-white rounded-xl shadow overflow-hidden" {!! $collapsible('idea') !!}>
            <button type="button" @click="open = ! open" class="{{ $secBtn }}"><span>Idea Detail</span>{!! $chevron !!}</button>
            <div x-show="open" class="p-6 space-y-4">
                @if($idea)
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-semibold text-gray-600 mb-1">Idea ID</label><div class="{{ $box }} font-mono">{{ $idea->idea_id }}</div></div>
                        <div><label class="block text-sm font-semibold text-gray-600 mb-1">Submitter</label><div class="{{ $box }}">{{ optional($idea->user)->name }}</div></div>
                    </div>
                    {{-- Read-only: snapshot nama dipakai lebih dulu, relasi jadi cadangan (sama seperti halaman Idea). --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-semibold text-gray-600 mb-1">Targeted Business Unit</label><div class="{{ $box }}">{{ $idea->business_unit_name ?? optional($idea->businessUnit)->name ?? '-' }}</div></div>
                        <div><label class="block text-sm font-semibold text-gray-600 mb-1">Targeted Unit</label><div class="{{ $box }}">{{ $idea->department_name ?? optional($idea->department)->name ?? '-' }}</div></div>
                    </div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Idea Name</label><div class="{{ $box }}">{{ $idea->idea_name }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Problem / Root Cause</label><div class="{{ $box }} whitespace-pre-line">{{ $idea->problem }}</div></div>
                @else<p class="text-gray-400">Related idea not found.</p>@endif
            </div>
        </div>

        {{-- 2. Project Detail --}}
        <div class="bg-white rounded-xl shadow overflow-hidden" {!! $collapsible('detail') !!}>
            <button type="button" @click="open = ! open" class="{{ $secBtn }}"><span>Project Detail</span>{!! $chevron !!}</button>
            <div x-show="open" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Category</label><div class="{{ $box }}">{{ $project->project_category }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Created</label><div class="{{ $box }}"><x-datetime :value="$project->created_at" mode="date" /></div></div>
                </div>
                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Project Scope</label><div class="{{ $box }} whitespace-pre-line">{{ $project->project_scope }}</div></div>
                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Expected Outcome</label><div class="{{ $box }} whitespace-pre-line">{{ $project->expected_outcome }}</div></div>
                @if($project->project_summary)
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Project Summary (Completion)</label><div class="{{ $box }} whitespace-pre-line">{{ $project->project_summary }}</div></div>
                @endif
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Sponsor</label><div class="{{ $box }}">{{ optional($project->sponsor)->name }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Leader</label><div class="{{ $box }}">{{ optional($project->leader)->name }}</div></div>
                </div>
            </div>
        </div>

        {{-- 3. Team Members --}}
        <div id="section-team" class="bg-white rounded-xl shadow overflow-hidden scroll-mt-6" {!! $collapsible('team', 'addOpen: ' . (session('memberDialogOpen') ? 'true' : 'false')) !!}>
            <button type="button" @click="open = ! open" class="{{ $secBtn }}"><span>Team Members</span>{!! $chevron !!}</button>
            <div x-show="open">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Role in Project</th>@if($canEdit)<th class="px-4 py-3 text-right">Action</th>@endif</tr></thead>
                    <tbody class="divide-y">
                        @forelse($project->members as $m)
                            <tr><td class="px-4 py-3">{{ optional($m->user)->name }}</td><td class="px-4 py-3">{{ $m->role }}</td>
                                @if($canEdit)
                                <td class="px-4 py-3 text-right" x-data="{ editOpen: false }">
                                    <div class="flex items-center justify-end gap-3">
                                        <button type="button" @click="editOpen = true" class="text-red-700 text-xs hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('projects.members.destroy', [$project, $m]) }}">@csrf @method('DELETE')<button class="text-red-600 text-xs hover:underline" data-confirm="Delete this item?" data-confirm-title="Delete" data-confirm-ok="Yes, Delete">Delete</button></form>
                                    </div>
                                    {{-- Dialog: Edit Member --}}
                                    <div x-show="editOpen" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="editOpen = false">
                                        <div class="fixed inset-0 bg-black/40" @click="editOpen = false"></div>
                                        <div class="{{ $modalCard }} text-left" x-transition.opacity x-init="$nextTick(() => window.tmsInit && window.tmsInit($el))">
                                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Team Member</h3>
                                            <form method="POST" action="{{ route('projects.members.update', [$project, $m]) }}" class="space-y-3">
                                                @csrf @method('PUT')
                                                    <x-record-version :model="$m" />
                                                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Nama</label>
                                                    <select name="user_id" required data-no-search data-remote-search="{{ route('org.users') }}" data-remote-value="id" class="{{ $inp }} w-full"><option value="{{ $m->user_id }}" selected>{{ optional($m->user)->name }}</option></select></div>
                                                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Role in Project</label>
                                                    <input name="role" value="{{ $m->role }}" required class="{{ $inp }} w-full"></div>
                                                <div class="flex justify-end gap-3 pt-1">
                                                    <button type="button" @click="editOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                                                    <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canEdit ? 3 : 2 }}" class="px-4 py-6 text-center text-gray-400">No members yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($canEdit)
                    <div class="p-4 border-t flex justify-end">
                        <button type="button" @click="addOpen = true" class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800">+ Add Member</button>
                    </div>
                @endif
            </div>

            {{-- Dialog: Add Member — kebutuhan role kategori & tambah manual disatukan
                 dalam SATU form: semua baris tersimpan sekali klik "Add Member". --}}
            @if($canEdit)
                @php
                    $requiredRoles = optional($project->category) ? $project->category->requiredRoles() : [];
                    $roleSlots = collect($requiredRoles)->map(function ($rr) use ($project) {
                        $filled = $project->members->filter(fn ($m) => mb_strtolower(trim((string) $m->role)) === mb_strtolower($rr['role']))->count();
                        return $rr + ['filled' => $filled, 'remaining' => max(0, $rr['total'] - $filled)];
                    })->filter(fn ($rr) => $rr['remaining'] > 0)->values();

                    // Baris awal = satu baris per slot role yang masih kurang (role terkunci).
                    // Bila kategori tak punya kebutuhan role, mulai dengan satu baris manual kosong.
                    $memberRows = [];
                    foreach ($roleSlots as $rr) {
                        for ($n = 0; $n < $rr['remaining']; $n++) {
                            $memberRows[] = ['_id' => count($memberRows), 'role' => $rr['role'], 'locked' => true];
                        }
                    }
                    if (! count($memberRows)) $memberRows[] = ['_id' => 0, 'role' => '', 'locked' => false];
                @endphp
                <div x-show="addOpen" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="addOpen = false">
                    <div class="fixed inset-0 bg-black/40" @click="addOpen = false"></div>
                    <div class="{{ $modalCard }}" x-transition.opacity
                         x-data="{ rows: @js($memberRows), next: {{ count($memberRows) }}, err: '' }"
                         x-init="$nextTick(() => window.tmsInit && window.tmsInit($el))">
                        <h3 class="text-lg font-semibold text-gray-800 mb-1">Add Team Member</h3>
                        @if($roleSlots->isNotEmpty())
                            <p class="text-sm text-gray-600 mb-4">Required roles for category <b>{{ optional($project->category)->code }}</b> :</p>
                        @else
                            <div class="mb-4"></div>
                        @endif

                        {{-- Sebelum submit: baris tanpa nama di-disable agar tidak ikut terkirim
                             (slot required boleh diisi bertahap). Minimal satu nama harus dipilih. --}}
                        {{-- Error dari server (validasi / batas anggota) — tanpa ini penolakan
                             server tidak terlihat sama sekali dan terkesan "tombol tidak jalan". --}}
                        @if($errors->member->any())
                            <ul class="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700 list-disc list-inside">
                                @foreach($errors->member->all() as $e)<li>{{ $e }}</li>@endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('projects.members.store', $project) }}" class="space-y-2"
                              @submit="err = window.tmsMemberSubmit($event) ? '' : 'Select at least one name before saving.'">
                            @csrf
                            <div class="hidden sm:grid grid-cols-12 gap-3 px-1 text-xs font-semibold text-gray-500 uppercase">
                                <div class="col-span-3">Role in Project</div>
                                <div class="col-span-1"></div>
                            </div>
                            <template x-for="(row, i) in rows" :key="row._id">
                                <div data-member-row class="grid grid-cols-12 gap-3 items-center">
                                    <div class="col-span-12 sm:col-span-3">
                                        <label class="sm:hidden block text-xs font-semibold text-gray-600 mb-1">Role in Project</label>
                                        <div class="relative">
                                            <input data-member-role :name="'rows['+i+'][role]'" x-model="row.role" :readonly="row.locked" required
                                                   placeholder="e.g. Admin"
                                                   class="{{ $inp }} w-full pr-6" :class="row.locked ? 'bg-gray-50 text-gray-700 cursor-default' : ''">
                                            <span x-show="row.locked" class="absolute inset-y-0 right-2 flex items-center text-red-600 font-bold leading-none"
                                                  title="Wajib diisi">*</span>
                                        </div>
                                    </div>
                                    <div :class="(! row.locked && rows.length > 1) ? 'col-span-10 sm:col-span-8' : 'col-span-12 sm:col-span-9'">
                                        <label class="sm:hidden block text-xs font-semibold text-gray-600 mb-1">Name</label>
                                        <select data-member-name :name="'rows['+i+'][user_id]'" :required="! row.locked"
                                                data-no-search data-remote-search="{{ route('org.users') }}" data-remote-value="id"
                                                class="{{ $inp }} w-full"><option value="">Enter name / employee ID…</option></select>
                                    </div>
                                    <div class="col-span-2 sm:col-span-1 flex justify-end" x-show="! row.locked && rows.length > 1" x-cloak>
                                        <button type="button" @click="rows.splice(i, 1)"
                                                class="text-red-600 text-xl leading-none px-1" title="Hapus baris">&times;</button>
                                    </div>
                                </div>
                            </template>
                            <button type="button" @click="rows.push({ _id: next++, role: '', locked: false }); $nextTick(() => window.tmsInit && window.tmsInit($root))"
                                    class="mt-1 text-sm text-red-700 border border-red-300 rounded-lg px-3 py-1.5 hover:bg-red-50">+ Add row</button>
                            <p x-show="err" x-cloak x-text="err" class="text-sm text-red-600"></p>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="addOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Close</button>
                                <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Add Member</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- 4. Implementation Detail --}}
        <div id="section-implementation" class="bg-white rounded-xl shadow overflow-hidden scroll-mt-6" {!! $collapsible('impl', 'addOpen: false') !!}>
            <button type="button" @click="open = ! open" class="{{ $secBtn }}"><span>Implementation Detail</span>{!! $chevron !!}</button>
            <div x-show="open">
                {{-- Baseline Actual: status + Submit (Leader) / Approve-Reject (Sponsor). --}}
                @if($showActual)
                    <div class="px-4 pt-4 flex flex-wrap items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-2 text-sm">
                            <span class="text-gray-500">Actual status:</span>
                            <span class="text-xs rounded-full px-2 py-1 {{ $project->actualStatusBadge()[1] }}">{{ $project->actualStatusBadge()[0] }}</span>
                        </span>
                        <div class="flex items-center gap-2">
                            @if($canSubmitActual)
                                <form method="POST" action="{{ route('projects.actual.submit', $project) }}">
                                    @csrf
                                    <button data-confirm="Submit the Actual to the Project Sponsor for approval? The Actual will be locked while waiting." data-confirm-title="Submit Actual?" data-confirm-ok="Yes, Submit"
                                            class="px-4 py-1.5 text-sm bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Submit Actual</button>
                                </form>
                            @endif
                            @if($isActualSponsor)
                                <form method="POST" action="{{ route('projects.actual.approve', $project) }}">@csrf<button data-confirm="Approve the Actual baseline? It will be locked afterwards." data-confirm-title="Approve Actual?" data-confirm-ok="Yes, Approve" class="px-4 py-1.5 text-sm bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">Approve</button></form>
                                <form method="POST" action="{{ route('projects.actual.reject', $project) }}">@csrf<button data-confirm="Return the Actual to the team for revision?" data-confirm-title="Reject Actual?" data-confirm-ok="Yes, Reject" class="px-4 py-1.5 text-sm border border-red-300 text-red-600 rounded-lg font-semibold hover:bg-red-50">Reject</button></form>
                            @endif
                        </div>
                    </div>
                    @if($project->actualIsPending())
                        <p class="px-4 pt-2 text-xs text-amber-600">Actual is locked while waiting for Project Sponsor approval.</p>
                    @elseif($project->actualIsBaselined())
                        <p class="px-4 pt-2 text-xs text-green-600">Actual baseline approved &amp; locked. Further changes require a change request.</p>
                    @endif
                @endif
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr><th class="px-4 py-3">Activity</th><th class="px-4 py-3">Planning</th>@if($showActual)<th class="px-4 py-3">Actual</th>@endif<th class="px-4 py-3">PIC</th><th class="px-4 py-3">Status</th>@if($canEdit)<th class="px-4 py-3 text-right">Action</th>@endif</tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($project->implementationPlans as $plan)
                            <tr>
                                <td class="px-4 py-3">{{ $plan->activity }}</td>
                                <td class="px-4 py-3 text-xs">{{ optional($plan->planning_start)->format('d/m/y') ?? '-' }} &ndash; {{ optional($plan->planning_end)->format('d/m/y') ?? '-' }}</td>
                                @if($showActual)
                                <td class="px-4 py-3 text-xs align-top" x-data="{ upd: false }">
                                    {{-- Ringkasan actual (read-only) --}}
                                    <div>{{ optional($plan->actual_start)->format('d/m/y') ?? '-' }} &ndash; {{ optional($plan->actual_end)->format('d/m/y') ?? '-' }}</div>
                                    @if($plan->actual_days !== null)<div class="text-gray-400">{{ $plan->actual_days }} hari</div>@endif
                                    @if($plan->attachment_path)
                                        <a href="{{ route('projects.implementation.attachment', [$project, $plan]) }}" class="text-red-700 hover:underline">📎 {{ \Illuminate\Support\Str::limit($plan->attachment_name, 18) }}</a>
                                    @endif
                                    @if($plan->remarks)<div class="text-gray-500 max-w-[200px] whitespace-normal break-words">{{ $plan->remarks }}</div>@endif

                                    @if($canTrack)
                                        <button type="button" @click="upd = true" class="mt-1 text-red-700 font-semibold hover:underline">Update</button>
                                        {{-- Dialog: Update Actual Implementation Plan --}}
                                        <div x-show="upd" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="upd = false">
                                            <div class="fixed inset-0 bg-black/40" @click="upd = false"></div>
                                            <div class="{{ $modalCard }} text-left" x-transition.opacity
                                                 x-data="{ s: '{{ optional($plan->actual_start)->format('Y-m-d') }}', e: '{{ optional($plan->actual_end)->format('Y-m-d') }}',
                                                           get days(){ if(!this.s || !this.e) return null; const a = new Date(this.s), b = new Date(this.e); if (b < a) return null; return Math.floor((b - a) / 86400000) + 1; } }">
                                                <h3 class="text-lg font-semibold text-gray-800 mb-1">Update Actual</h3>
                                                <p class="text-sm text-gray-500 mb-4">{{ $plan->activity }}</p>
                                                {{-- replacing: true begitu user memilih berkas baru padahal sudah ada
                                                     berkas lama. Dipakai untuk memunculkan dialog konfirmasi HANYA pada
                                                     kasus penggantian, bukan pada unggahan pertama. --}}
                                                <form method="POST" action="{{ route('projects.implementation.actual', [$project, $plan]) }}" enctype="multipart/form-data" class="space-y-3"
                                                      x-data="{ hasExisting: {{ $plan->attachment_path ? 'true' : 'false' }}, picked: '', get replacing(){ return this.hasExisting && this.picked !== ''; } }">
                                                    @csrf @method('PUT')
                                                    <x-record-version :model="$plan" />
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <label class="text-xs text-gray-500">Actual Timeline Start<input type="date" name="actual_start" x-model="s" :max="e" class="{{ $inp }} w-full"></label>
                                                        <label class="text-xs text-gray-500">Actual Timeline End<input type="date" name="actual_end" x-model="e" :min="s" class="{{ $inp }} w-full"></label>
                                                    </div>
                                                    <div class="text-xs text-gray-500">Actual Timeline Days<div class="{{ $inp }} w-full bg-gray-50 text-gray-700" x-text="days !== null ? days + ' day(s)' : '-'"></div></div>
                                                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Remark</label><textarea name="remarks" rows="2" maxlength="2000" placeholder="Notes (max 2000 characters)" class="{{ $inp }} w-full">{{ $plan->remarks }}</textarea></div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Attachment</label>
                                                        @if($plan->attachment_path)
                                                            <p class="text-xs text-gray-500 mb-1">
                                                                Current file:
                                                                <a href="{{ route('projects.implementation.attachment', [$project, $plan]) }}" class="text-red-700 hover:underline">{{ $plan->attachment_name }}</a>
                                                                — choose a new file to replace it.
                                                            </p>
                                                            @if($plan->attachment_uploaded_at)
                                                                <p class="text-xs text-gray-400 mb-1">
                                                                    {{ $plan->attachment_replace_count > 0 ? 'Last replaced' : 'Uploaded' }}
                                                                    by {{ optional($plan->attachmentUploader)->name ?? 'Unknown' }}
                                                                    on <x-datetime :value="$plan->attachment_uploaded_at" />
                                                                    @if($plan->attachment_replace_count > 0)
                                                                        &middot; replaced {{ $plan->attachment_replace_count }}&times;
                                                                    @endif
                                                                </p>
                                                            @endif
                                                        @endif
                                                        <input type="file" name="attachment" accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png,.pptx"
                                                               @change="picked = $event.target.value" class="text-sm w-full">
                                                        <p class="text-xs text-gray-400 mt-1">Max 7 MB, 1 file — .pdf, .docx, .xlsx, .jpg, .jpeg, .png, .pptx</p>
                                                        <p x-show="replacing" x-cloak class="text-xs text-amber-700 mt-1">
                                                            The current file will be permanently replaced when you save.
                                                        </p>
                                                        @error('attachment')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                                    </div>
                                                    <div class="flex justify-end gap-3 pt-1">
                                                        <button type="button" @click="upd = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                                                        {{-- Atribut data-confirm dipasang HANYA saat mengganti berkas; dialog
                                                             konfirmasi global di layout yang menanganinya. --}}
                                                        <button type="submit"
                                                                :data-confirm="replacing ? 'This will permanently replace the current attachment. The previous file cannot be recovered. Continue?' : null"
                                                                :data-confirm-title="replacing ? 'Replace Attachment?' : null"
                                                                :data-confirm-ok="replacing ? 'Yes, Replace File' : null"
                                                                class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                @endif
                                <td class="px-4 py-3 text-xs">{{ collect($plan->pic_user_ids)->map(fn($id) => optional($usersById[$id] ?? null)->name)->filter()->implode(', ') ?: '-' }}</td>
                                {{-- Status: live (dari actual) HANYA di view Implementation.
                                     Di view Project Proposal = snapshot proposal → selalu "Not Started". --}}
                                <td class="px-4 py-3"><span class="text-xs rounded-full px-2 py-1 bg-gray-100 text-gray-700">{{ $showActual ? $plan->status_label : 'Not Started' }}</span></td>
                                @if($canEdit)
                                <td class="px-4 py-3 text-right" x-data="{ editOpen: false }">
                                    <div class="flex items-center justify-end gap-3">
                                        <button type="button" @click="editOpen = true" class="text-red-700 text-xs hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('projects.implementation.destroy', [$project, $plan]) }}">@csrf @method('DELETE')<button class="text-red-600 text-xs hover:underline" data-confirm="Delete this item?" data-confirm-title="Delete" data-confirm-ok="Yes, Delete">Delete</button></form>
                                    </div>
                                    {{-- Dialog: Edit Activity --}}
                                    <div x-show="editOpen" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="editOpen = false">
                                        <div class="fixed inset-0 bg-black/40" @click="editOpen = false"></div>
                                        <div class="{{ $modalCard }} text-left" x-transition.opacity x-init="$nextTick(() => window.tmsInit && window.tmsInit($el))">
                                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Activity</h3>
                                            <form method="POST" action="{{ route('projects.implementation.update', [$project, $plan]) }}" class="space-y-3">
                                                @csrf @method('PUT')
                                                    <x-record-version :model="$plan" />
                                                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Activity</label><input name="activity" value="{{ $plan->activity }}" required class="{{ $inp }} w-full"></div>
{{-- Start dibatasi <= End dan sebaliknya, jadi tanggal keliru tidak bisa dipilih. --}}
                                                <div class="grid grid-cols-2 gap-2"
                                                     x-data="{ ps: '{{ optional($plan->planning_start)->format('Y-m-d') }}', pe: '{{ optional($plan->planning_end)->format('Y-m-d') }}' }">
                                                    <label class="text-xs text-gray-500">Planned Start Date<input type="date" name="planning_start" x-model="ps" :max="pe" class="{{ $inp }} w-full"></label>
                                                    <label class="text-xs text-gray-500">Planned End Date<input type="date" name="planning_end" x-model="pe" :min="ps" class="{{ $inp }} w-full"></label>
                                                </div>
                                                <div><label class="block text-xs font-semibold text-gray-600 mb-1">PIC</label>
                                                    <select name="pic_user_ids[]" multiple placeholder="Click to select PIC (you can choose more than one)…" class="{{ $inp }} w-full">
                                                        @foreach($teamMembers as $tm)<option value="{{ $tm->id }}" @selected(in_array($tm->id, (array) $plan->pic_user_ids))>{{ $tm->name }}</option>@endforeach
                                                    </select></div>
                                                <div class="flex justify-end gap-3 pt-1">
                                                    <button type="button" @click="editOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                                                    <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ 4 + ($showActual ? 1 : 0) + ($canEdit ? 1 : 0) }}" class="px-4 py-6 text-center text-gray-400">No activities yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @if($canEdit)
                    <div class="p-4 border-t flex justify-end">
                        <button type="button" @click="addOpen = true" class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800">+ Add Activity</button>
                    </div>
                @endif
            </div>

            {{-- Dialog: Add Activity --}}
            @if($canEdit)
                <div x-show="addOpen" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="addOpen = false">
                    <div class="fixed inset-0 bg-black/40" @click="addOpen = false"></div>
                    <div class="{{ $modalCard }}" x-transition.opacity
                         x-data="{ rows: [{ _id: 0, activity:'', planning_start:'', planning_end:'', actual_start:'', actual_end:'' }], next: 1 }"
                         x-init="$nextTick(() => window.tmsInit && window.tmsInit($el))">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Activity</h3>
                        <form method="POST" action="{{ route('projects.implementation.store', $project) }}" class="space-y-3">
                            @csrf
                            <template x-for="(row, i) in rows" :key="row._id">
                                <div class="border rounded-lg p-3 grid grid-cols-2 gap-2 relative">
                                    <button type="button" x-show="rows.length > 1" @click="rows.splice(i, 1)" class="absolute top-1.5 right-2 text-red-600 text-sm">&times;</button>
                                    <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Activity</label><input :name="'rows['+i+'][activity]'" x-model="row.activity" placeholder="Activity" required class="{{ $inp }} w-full"></div>
<label class="text-xs text-gray-500">Planned Start Date<input type="date" :name="'rows['+i+'][planning_start]'" x-model="row.planning_start" :max="row.planning_end" class="{{ $inp }} w-full"></label>
                                    <label class="text-xs text-gray-500">Planned End Date<input type="date" :name="'rows['+i+'][planning_end]'" x-model="row.planning_end" :min="row.planning_start" class="{{ $inp }} w-full"></label>
                                    @if($showActual)
<label class="text-xs text-gray-500">Actual Start<input type="date" :name="'rows['+i+'][actual_start]'" x-model="row.actual_start" :max="row.actual_end" class="{{ $inp }} w-full"></label>
                                    <label class="text-xs text-gray-500">Actual End<input type="date" :name="'rows['+i+'][actual_end]'" x-model="row.actual_end" :min="row.actual_start" class="{{ $inp }} w-full"></label>
                                    @endif
                                    <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">PIC</label>
                                        <select :name="'rows['+i+'][pic_user_ids][]'" multiple placeholder="Click to select PIC (you can choose more than one)…" class="{{ $inp }} w-full">
                                            @foreach($teamMembers as $tm)<option value="{{ $tm->id }}">{{ $tm->name }}</option>@endforeach
                                        </select></div>
                                </div>
                            </template>
                            <button type="button" @click="rows.push({ _id: next++, activity:'', planning_start:'', planning_end:'', actual_start:'', actual_end:'' }); $nextTick(() => window.tmsInit && window.tmsInit($root))"
                                    class="text-sm text-red-700 border border-red-300 rounded-lg px-3 py-1.5 hover:bg-red-50">+ Add row</button>
                            <div class="flex justify-end gap-3 pt-1">
                                <button type="button" @click="addOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                                <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Add Activity</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- 5. Success Indicators --}}
        <div id="section-indicators" class="bg-white rounded-xl shadow overflow-hidden scroll-mt-6" {!! $collapsible('ind', 'addOpen: false') !!}>
            <button type="button" @click="open = ! open" class="{{ $secBtn }}">
                <span class="flex items-center gap-3">Success Indicators
                    <span class="text-xs rounded-full px-3 py-1 {{ abs($totalWeight-100) < 0.01 ? 'bg-green-500/30' : 'bg-yellow-500/40' }}">Total Weight: {{ $totalWeight }}%</span>
                </span>{!! $chevron !!}
            </button>
            <div x-show="open">
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr><th class="px-4 py-3">Indicator</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Baseline</th><th class="px-4 py-3">Achievement Value</th>@if($showActual)<th class="px-4 py-3">Achievement</th>@endif<th class="px-4 py-3">UoM</th><th class="px-4 py-3">Weightage (%)</th><th class="px-4 py-3">Type</th>@if($showActual)<th class="px-4 py-3">% Improve</th>@endif @if($canEdit)<th class="px-4 py-3 text-right">Action</th>@endif</tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($project->indicators as $ind)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $ind->indicator }}</td>
                                <td class="px-4 py-3"><div class="max-w-xs whitespace-normal break-words text-xs text-gray-500">{{ $ind->description }}</div></td>
                                <td class="px-4 py-3">{{ $ind->baseline }}</td>
                                <td class="px-4 py-3">{{ $ind->achievement_value }}</td>
                                @if($showActual)
                                <td class="px-4 py-3 text-xs">
                                    @if($canTrack)
                                        <form method="POST" action="{{ route('projects.indicators.achievement', [$project, $ind]) }}" class="flex items-center gap-1">
                                            @csrf @method('PUT')
                                                    <x-record-version :model="$ind" />
                                            <input type="number" step="any" name="achievement" value="{{ $ind->achievement }}" placeholder="actual" class="border rounded px-1 py-0.5 w-20 text-xs">
                                            <button class="text-red-700 font-semibold">Save</button>
                                        </form>
                                    @else
                                        {{ $ind->achievement ?? '-' }}
                                    @endif
                                </td>
                                @endif
                                <td class="px-4 py-3">{{ $ind->uom }}</td>
                                <td class="px-4 py-3">{{ $ind->weightage }}</td>
                                <td class="px-4 py-3 text-xs">{{ $ind->type }}</td>
                                @if($showActual)<td class="px-4 py-3">{{ $ind->improvement !== null ? $ind->improvement.'%' : '-' }}</td>@endif
                                @if($canEdit)
                                <td class="px-4 py-3 text-right" x-data="{ editOpen: false }">
                                    <div class="flex items-center justify-end gap-3">
                                        <button type="button" @click="editOpen = true" class="text-red-700 text-xs hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('projects.indicators.destroy', [$project, $ind]) }}">@csrf @method('DELETE')<button class="text-red-600 text-xs hover:underline" data-confirm="Delete this item?" data-confirm-title="Delete" data-confirm-ok="Yes, Delete">Delete</button></form>
                                    </div>
                                    {{-- Dialog: Edit Indicator --}}
                                    <div x-show="editOpen" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="editOpen = false">
                                        <div class="fixed inset-0 bg-black/40" @click="editOpen = false"></div>
                                        <div class="{{ $modalCard }} text-left" x-transition.opacity x-init="$nextTick(() => window.tmsInit && window.tmsInit($el))">
                                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Success Indicator</h3>
                                            <form method="POST" action="{{ route('projects.indicators.update', [$project, $ind]) }}" class="grid grid-cols-2 gap-2">
                                                @csrf @method('PUT')
                                                    <x-record-version :model="$ind" />
                                                <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Indicator Name</label><input name="indicator" value="{{ $ind->indicator }}" required class="{{ $inp }} w-full"></div>
                                                <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Indicator Description</label><textarea name="description" rows="2" class="{{ $inp }} w-full">{{ $ind->description }}</textarea></div>
                                                <label class="text-xs text-gray-500">Baseline<input type="number" step="any" name="baseline" value="{{ $ind->baseline }}" class="{{ $inp }} w-full"></label>
                                                <label class="text-xs text-gray-500">Achievement Value <span class="text-gray-400">(target)</span><input type="number" step="any" name="achievement_value" value="{{ $ind->achievement_value }}" class="{{ $inp }} w-full"></label>
                                                @if($showActual)
                                                <label class="text-xs text-gray-500 col-span-2">Achievement <span class="text-gray-400">(aktual)</span><input type="number" step="any" name="achievement" value="{{ $ind->achievement }}" class="{{ $inp }} w-full"></label>
                                                @endif
                                                <label class="text-xs text-gray-500">UoM
                                                    <select name="uom" class="{{ $inp }} w-full">@include('projects._uom-options', ['selected' => $ind->uom])</select></label>
                                                <label class="text-xs text-gray-500">Weightage (%)<input type="number" step="any" name="weightage" value="{{ $ind->weightage }}" class="{{ $inp }} w-full"></label>
                                                <label class="text-xs text-gray-500 col-span-2">Type <span class="text-red-600">*</span>
                                                    <select name="type" required class="{{ $inp }} w-full"><option value="">Select Type…</option>@foreach(\App\Models\ImplementationIndicator::TYPES as $t)<option value="{{ $t }}" @selected($ind->type === $t)>{{ $t }}</option>@endforeach</select></label>
                                                <div class="col-span-2 flex justify-end gap-3 pt-1">
                                                    <button type="button" @click="editOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                                                    <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ 7 + ($showActual ? 2 : 0) + ($canEdit ? 1 : 0) }}" class="px-4 py-6 text-center text-gray-400">No indicators yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @if($canEdit)
                    <div class="p-4 border-t flex items-center justify-between gap-3">
                        <p class="text-xs text-gray-400">% Improvement is calculated automatically. Total Weight must be 100% to submit.</p>
                        <button type="button" @click="addOpen = true" class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800 shrink-0">+ Add Indicator</button>
                    </div>
                @endif
            </div>

            {{-- Dialog: Add Indicator --}}
            @if($canEdit)
                <div x-show="addOpen" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="addOpen = false">
                    <div class="fixed inset-0 bg-black/40" @click="addOpen = false"></div>
                    <div class="{{ $modalCard }}" x-transition.opacity
                         x-data="{
                             used: {{ (float) $project->indicators->sum('weightage') }},
                             rows: [{ _id: 0, indicator:'', description:'', baseline:'', achievement_value:'', achievement:'', weightage:'', type:'' }], next: 1,
                             get adding(){ return this.rows.reduce((t, r) => t + (parseFloat(r.weightage) || 0), 0); },
                             get total(){ return Math.round((this.used + this.adding) * 100) / 100; },
                             get over(){ return this.total > 100; },
                             get remaining(){ return Math.round((100 - this.used) * 100) / 100; }
                         }"
                         x-init="$nextTick(() => window.tmsInit && window.tmsInit($el))">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Success Indicator</h3>
                        <form method="POST" action="{{ route('projects.indicators.store', $project) }}" class="space-y-3">
                            @csrf
                            <template x-for="(row, i) in rows" :key="row._id">
                                <div class="border rounded-lg p-3 grid grid-cols-2 gap-2 relative">
                                    <button type="button" x-show="rows.length > 1" @click="rows.splice(i, 1)" class="absolute top-1.5 right-2 text-red-600 text-sm">&times;</button>
                                    <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Indicator Name</label><input :name="'rows['+i+'][indicator]'" x-model="row.indicator" placeholder="Indicator name" required class="{{ $inp }} w-full"></div>
                                    <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Indicator Description</label><textarea :name="'rows['+i+'][description]'" x-model="row.description" rows="2" placeholder="Indicator description" class="{{ $inp }} w-full"></textarea></div>
                                    <label class="text-xs text-gray-500">Baseline<input type="number" step="any" :name="'rows['+i+'][baseline]'" x-model="row.baseline" class="{{ $inp }} w-full"></label>
                                    <label class="text-xs text-gray-500">Achievement Value <span class="text-gray-400">(target)</span><input type="number" step="any" :name="'rows['+i+'][achievement_value]'" x-model="row.achievement_value" class="{{ $inp }} w-full"></label>
                                    @if($showActual)
                                    <label class="text-xs text-gray-500 col-span-2">Achievement <span class="text-gray-400">(aktual)</span><input type="number" step="any" :name="'rows['+i+'][achievement]'" x-model="row.achievement" class="{{ $inp }} w-full"></label>
                                    @endif
                                    <label class="text-xs text-gray-500">UoM
                                        <select :name="'rows['+i+'][uom]'" class="{{ $inp }} w-full">@include('projects._uom-options')</select></label>
                                    <label class="text-xs text-gray-500">Weightage (%)<input type="number" step="any" :name="'rows['+i+'][weightage]'" x-model="row.weightage" class="{{ $inp }} w-full"></label>
                                    <label class="text-xs text-gray-500 col-span-2">Type <span class="text-red-600">*</span>
                                        <select :name="'rows['+i+'][type]'" x-model="row.type" required class="{{ $inp }} w-full"><option value="">Select Type…</option>@foreach(\App\Models\ImplementationIndicator::TYPES as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select></label>
                                    <p class="col-span-2 text-xs text-gray-400">Percent Improvement is calculated automatically (2 decimals). Total Weightage of all indicators must be 100%.</p>
                                </div>
                            </template>
                            <button type="button" @click="rows.push({ _id: next++, indicator:'', description:'', baseline:'', achievement_value:'', achievement:'', weightage:'', type:'' }); $nextTick(() => window.tmsInit && window.tmsInit($root))"
                                    class="text-sm text-red-700 border border-red-300 rounded-lg px-3 py-1.5 hover:bg-red-50">+ Add row</button>

                            {{-- Ringkasan bobot: sisa kuota, dan peringatan bila melewati 100%. --}}
                            <div class="rounded-lg border px-3 py-2 text-sm"
                                 :class="over ? 'bg-red-50 border-red-200 text-red-700' : 'bg-gray-50 border-gray-200 text-gray-600'">
                                Total weightage: <span class="font-semibold" x-text="total + '%'"></span>
                                <span class="text-xs" x-text="'(already used ' + used + '%, still available ' + remaining + '%)'"></span>
                                <span x-show="over" x-cloak class="block text-xs font-semibold mt-0.5">
                                    Exceeds 100% — reduce the weightage before adding.
                                </span>
                            </div>

                            <div class="flex justify-end gap-3 pt-1">
                                <button type="button" @click="addOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                                <button type="submit" :disabled="over"
                                        :class="over ? 'opacity-50 cursor-not-allowed' : 'hover:bg-red-800'"
                                        class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold">Add Indicator</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- 6. Budget --}}
        <div id="section-budget" class="bg-white rounded-xl shadow overflow-hidden scroll-mt-6" {!! $collapsible('budget', 'addOpen: false') !!}>
            <button type="button" @click="open = ! open" class="{{ $secBtn }}"><span>Budget</span>{!! $chevron !!}</button>
            <div x-show="open">
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr><th class="px-4 py-3 w-12">No</th><th class="px-4 py-3">Item Name</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">UoM</th><th class="px-4 py-3">Price</th><th class="px-4 py-3">Total Price</th>@if($showActual)<th class="px-4 py-3">Actual (Qty × Price = Cost)</th>@endif @if($canEdit)<th class="px-4 py-3 text-right">Action</th>@endif</tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($project->budgets as $b)
                            <tr>
                                <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3">{{ $b->item }}</td><td class="px-4 py-3">{{ $b->qty }}</td><td class="px-4 py-3">{{ $b->uom }}</td>
                                <td class="px-4 py-3">Rp {{ number_format((float) $b->unit_price) }}</td>
                                <td class="px-4 py-3">Rp {{ number_format($b->planned_total) }}</td>
                                @if($showActual)
                                <td class="px-4 py-3 text-xs">
                                    @if($canTrack)
                                        <form method="POST" action="{{ route('projects.budgets.actual', [$project, $b]) }}" class="flex items-center gap-1">
                                            @csrf @method('PUT')
                                                    <x-record-version :model="$b" />
                                            <input type="number" step="any" name="actual_qty" value="{{ $b->actual_qty }}" placeholder="qty" class="border rounded px-1 py-0.5 w-16 text-xs">
                                            <input type="number" step="any" name="actual_price" value="{{ $b->actual_price }}" placeholder="price" class="border rounded px-1 py-0.5 w-20 text-xs">
                                            <button class="text-red-700 font-semibold">Save</button>
                                            <span class="text-gray-400">= {{ $b->actual_cost !== null ? number_format((float) $b->actual_cost) : '-' }}</span>
                                        </form>
                                    @else
                                        {{ $b->actual_cost !== null ? number_format((float) $b->actual_cost) : '-' }}
                                    @endif
                                </td>
                                @endif
                                @if($canEdit)
                                <td class="px-4 py-3 text-right" x-data="{ editOpen: false }">
                                    <div class="flex items-center justify-end gap-3">
                                        <button type="button" @click="editOpen = true" class="text-red-700 text-xs hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('projects.budgets.destroy', [$project, $b]) }}">@csrf @method('DELETE')<button class="text-red-600 text-xs hover:underline" data-confirm="Delete this item?" data-confirm-title="Delete" data-confirm-ok="Yes, Delete">Delete</button></form>
                                    </div>
                                    {{-- Dialog: Edit Budget --}}
                                    <div x-show="editOpen" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="editOpen = false">
                                        <div class="fixed inset-0 bg-black/40" @click="editOpen = false"></div>
                                        <div class="{{ $modalCard }} text-left" x-transition.opacity x-init="$nextTick(() => window.tmsInit && window.tmsInit($el))"
                                             x-data="{ q: {{ (float) $b->qty }}, p: {{ (float) $b->unit_price }} }">
                                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Budget</h3>
                                            <form method="POST" action="{{ route('projects.budgets.update', [$project, $b]) }}" class="grid grid-cols-2 gap-2">
                                                @csrf @method('PUT')
                                                    <x-record-version :model="$b" />
                                                <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Item Name</label><input name="item" value="{{ $b->item }}" maxlength="500" required class="{{ $inp }} w-full"></div>
                                                <label class="text-xs text-gray-500">Qty<input type="number" step="any" min="0" name="qty" x-model="q" value="{{ $b->qty }}" class="{{ $inp }} w-full"></label>
                                                <label class="text-xs text-gray-500">UoM
                                                    <select name="uom" class="{{ $inp }} w-full">@include('projects._uom-options', ['selected' => $b->uom])</select></label>
                                                <label class="text-xs text-gray-500">Price (IDR)<input type="number" step="any" min="0" name="unit_price" x-model="p" value="{{ $b->unit_price }}" class="{{ $inp }} w-full"></label>
                                                <div class="text-xs text-gray-500">Total Price<div class="{{ $inp }} w-full bg-gray-50 text-gray-700" x-text="'Rp ' + ((Number(q)||0) * (Number(p)||0)).toLocaleString('id-ID')"></div></div>
                                                <div class="col-span-2 flex justify-end gap-3 pt-1">
                                                    <button type="button" @click="editOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                                                    <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @empty
                            {{-- Sebelum proposal disubmit budget masih bisa diisi ("No budget yet");
                                 setelah disubmit tanpa budget, keadaannya sudah final
                                 sehingga kalimatnya menjadi "No budget submitted". --}}
                            @php $budgetEmptyText = in_array($project->status, \App\Models\Project::EDITABLE_STATUSES, true) ? 'No budget yet.' : 'No budget submitted.'; @endphp
                            <tr><td colspan="{{ 6 + ($showActual ? 1 : 0) + ($canEdit ? 1 : 0) }}" class="px-4 py-6 text-center text-gray-400">{{ $budgetEmptyText }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @if($canEdit)
                    <div class="p-4 border-t flex justify-end">
                        <button type="button" @click="addOpen = true" class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800">+ Add Budget</button>
                    </div>
                @endif
            </div>

            {{-- Dialog: Add Budget --}}
            @if($canEdit)
                <div x-show="addOpen" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="addOpen = false">
                    <div class="fixed inset-0 bg-black/40" @click="addOpen = false"></div>
                    <div class="{{ $modalCard }}" x-transition.opacity
                         x-data="{ rows: [{ _id: 0, item:'', qty:'', uom:'', unit_price:'' }], next: 1 }"
                         x-init="$nextTick(() => window.tmsInit && window.tmsInit($el))">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Budget</h3>
                        <form method="POST" action="{{ route('projects.budgets.store', $project) }}" class="space-y-3">
                            @csrf
                            <template x-for="(row, i) in rows" :key="row._id">
                                <div class="border rounded-lg p-3 grid grid-cols-2 gap-2 relative">
                                    <button type="button" x-show="rows.length > 1" @click="rows.splice(i, 1)" class="absolute top-1.5 right-2 text-red-600 text-sm">&times;</button>
                                    <div class="col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Item Name</label><input :name="'rows['+i+'][item]'" x-model="row.item" maxlength="500" placeholder="Item name" required class="{{ $inp }} w-full"></div>
                                    <label class="text-xs text-gray-500">Qty<input type="number" step="any" min="0" :name="'rows['+i+'][qty]'" x-model="row.qty" class="{{ $inp }} w-full"></label>
                                    <label class="text-xs text-gray-500">UoM
                                        <select :name="'rows['+i+'][uom]'" class="{{ $inp }} w-full">@include('projects._uom-options')</select></label>
                                    <label class="text-xs text-gray-500">Price (IDR)<input type="number" step="any" min="0" :name="'rows['+i+'][unit_price]'" x-model="row.unit_price" class="{{ $inp }} w-full"></label>
                                    <div class="text-xs text-gray-500">Total Price<div class="{{ $inp }} w-full bg-gray-50 text-gray-700" x-text="'Rp ' + ((Number(row.qty)||0) * (Number(row.unit_price)||0)).toLocaleString('id-ID')"></div></div>
                                </div>
                            </template>
                            <button type="button" @click="rows.push({ _id: next++, item:'', qty:'', uom:'', unit_price:'' }); $nextTick(() => window.tmsInit && window.tmsInit($root))"
                                    class="text-sm text-red-700 border border-red-300 rounded-lg px-3 py-1.5 hover:bg-red-50">+ Add row</button>
                            <div class="flex justify-end gap-3 pt-1">
                                <button type="button" @click="addOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                                <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Add Budget</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- 7. Supporting Documents — tampilan sama seperti Create Idea --}}
        <div id="section-attachments" class="bg-white rounded-xl shadow overflow-hidden scroll-mt-6" {!! $collapsible('att') !!}>
            <button type="button" @click="open = ! open" class="{{ $secBtn }}"><span>Supporting Documents</span>{!! $chevron !!}</button>
            <div x-show="open" class="p-6 space-y-4">
                @php $viewable = ['jpg', 'jpeg', 'png', 'pdf']; @endphp

                {{-- Daftar lampiran: gambar/PDF buka di tab baru, lainnya langsung unduh --}}
                <div>
                    @forelse($project->attachments as $att)
                        @php $ext = strtolower(pathinfo($att->file_name, PATHINFO_EXTENSION)); @endphp
                        <div class="flex items-center justify-between border-b py-2 last:border-0 text-sm">
                            @if(in_array($ext, $viewable))
                                <a href="{{ route('projects.attachments.view', [$project, $att]) }}" target="_blank" rel="noopener" class="text-red-700 hover:underline truncate">{{ $att->file_name }}</a>
                            @else
                                <a href="{{ route('projects.attachments.download', [$project, $att]) }}" class="text-red-700 hover:underline truncate">{{ $att->file_name }}</a>
                            @endif
                            <span class="flex items-center gap-3 shrink-0 ml-3">
                                <a href="{{ route('projects.attachments.download', [$project, $att]) }}" class="text-xs text-red-700 hover:underline">Download</a>
                                <span class="text-gray-400">{{ $att->file_size ? number_format($att->file_size / 1024, 0) . ' KB' : '' }}</span>
                                @if(auth()->id() === $att->uploaded_by || $isLeader || auth()->user()->hasRole('Super Admin'))
                                    <form method="POST" action="{{ route('projects.attachments.destroy', [$project, $att]) }}">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline" data-confirm="Delete this attachment?" data-confirm-title="Delete Attachment" data-confirm-ok="Yes, Delete">Delete</button></form>
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-400 text-sm">No attachments yet.</p>
                    @endforelse
                </div>

                {{-- Upload banyak file sekaligus (pilih/tarik), bisa batalkan sebelum submit --}}
                @if($canUploadAttachment)
                    <form method="POST" action="{{ route('projects.attachments.store', $project) }}" enctype="multipart/form-data">
                        @csrf
                        <div x-data="{
                                files: [], drag: false,
                                add(e){ this.push(e.target.files); },
                                drop(e){ this.drag = false; this.push(e.dataTransfer.files); },
                                push(list){ for (const f of list){ if (!this.files.some(x => x.name === f.name && x.size === f.size)) this.files.push(f); } this.sync(); },
                                remove(i){ this.files.splice(i, 1); this.sync(); },
                                sync(){ const dt = new DataTransfer(); this.files.forEach(f => dt.items.add(f)); this.$refs.input.files = dt.files; },
                                human(b){ return b >= 1048576 ? (b/1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b/1024)) + ' KB'; }
                             }">
                            <input type="file" name="attachments[]" multiple x-ref="input" @change="add($event)"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.zip,.csv,.txt"
                                   class="hidden">

                            {{-- Drop zone / tombol pilih --}}
                            <div @click="$refs.input.click()"
                                 @dragover.prevent="drag = true" @dragleave.prevent="drag = false" @drop.prevent="drop($event)"
                                 :class="drag ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                                 class="cursor-pointer border-2 border-dashed rounded-lg px-4 py-6 text-center text-sm text-gray-500 hover:bg-gray-50 @error('attachments.*') border-red-500 @enderror">
                                <span class="font-semibold text-red-700">Choose files</span> or drag &amp; drop here
                            </div>

                            {{-- Daftar file terpilih + tombol batal per file --}}
                            <ul class="mt-3 space-y-2" x-show="files.length" x-cloak>
                                <template x-for="(f, i) in files" :key="f.name + f.size + i">
                                    <li class="flex items-center justify-between gap-3 border rounded-lg px-3 py-2 text-sm bg-white">
                                        <span class="truncate" x-text="f.name"></span>
                                        <span class="flex items-center gap-3 shrink-0">
                                            <span class="text-gray-400" x-text="human(f.size)"></span>
                                            <button type="button" @click="remove(i)" title="Remove this file" class="w-6 h-6 flex items-center justify-center rounded hover:bg-red-50 text-red-600 text-lg leading-none">&times;</button>
                                        </span>
                                    </li>
                                </template>
                            </ul>

                            <div class="flex items-center justify-between mt-3">
                                <p class="text-xs text-gray-400">Optional. Multiple files can be uploaded. Maks 10 MB/file — .pdf, .docx, .xlsx, .jpg, .jpeg, .png, .pptx</p>
                                <button type="submit" x-show="files.length" x-cloak class="px-6 py-2 bg-red-700 text-white rounded-lg text-sm font-semibold hover:bg-red-800 shrink-0">Upload</button>
                            </div>
                            @error('attachments.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </form>
                @endif
            </div>
        </div>

        {{-- Approval History --}}
        @if($project->approvals->isNotEmpty())
            <div class="bg-white rounded-xl shadow overflow-hidden" {!! $collapsible('approval') !!}>
                <button type="button" @click="open = ! open" class="{{ $secBtn }}"><span>Approval History</span>{!! $chevron !!}</button>
                <div x-show="open" class="p-6">
                    @foreach($project->approvals as $a)
                        <div class="flex items-start gap-3 border-b py-2 last:border-0 text-sm">
                            <span class="text-xs rounded-full px-2 py-1 shrink-0 {{ $a->decision === 'approve' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">Layer {{ $a->layer }} · {{ ucfirst($a->decision) }}</span>
                            <div>
                                <span class="font-semibold">{{ optional($a->user)->name }}</span>
                                <span class="text-gray-400">· <x-datetime :value="$a->created_at" /></span>
                                @if($a->note)<div class="text-gray-600 mt-0.5">{{ $a->note }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Project Progress (log status) — disembunyikan selama masih proposal. --}}
        @if($showActual)
        <div class="bg-white rounded-xl shadow overflow-hidden" {!! $collapsible('progress') !!}>
            <button type="button" @click="open = ! open" class="{{ $secBtn }}"><span>Project Progress</span>{!! $chevron !!}</button>
            <div x-show="open" class="p-6">
                @forelse($project->statusLogs as $log)
                    <div class="flex items-start gap-3 border-b py-2 last:border-0 text-sm">
                        <span class="text-xs rounded-full px-2 py-1 bg-gray-100 text-gray-700">{{ $log->old_status ?? '—' }} &rarr; {{ $log->new_status }}</span>
                        <div><span class="font-semibold">{{ optional($log->changedBy)->name }}</span>
                            <span class="text-gray-400">· <x-datetime :value="$log->created_at" /></span>@if($log->remarks)<div class="text-gray-500">{{ $log->remarks }}</div>@endif</div>
                    </div>
                @empty
                    <p class="text-gray-400 text-sm">No status changes yet.</p>
                @endforelse
            </div>
        </div>
        @endif

        {{-- ================= Aksi bawah: Draft / Submit / keputusan + Cancel Project ================= --}}
        @php $showActions = ($isLeader && $canEdit) || ($isSponsor && $project->status === 'submitted') || $isReviewer; @endphp
        @if($showActions || $canCancel)
            <div class="bg-white rounded-xl shadow p-6 space-y-4" x-data="{ cancelOpen: false }">

                @if($isLeader && $canEdit)
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm text-gray-500">Lengkapi Impl. Plan & Success Indicators (total weight 100%), lalu submit.</div>
                        <div class="flex items-center gap-3">
                            <form method="POST" action="{{ route('projects.draft', $project) }}">
                                @csrf<button class="px-6 py-2 border rounded-lg font-semibold text-gray-700 hover:bg-gray-100">Draft</button>
                            </form>
                            <form method="POST" action="{{ route('projects.submit', $project) }}">
                                @csrf<button
                                    data-confirm="This proposal will be sent to the Sponsor for approval and cannot be edited while awaiting a decision. Continue?"
                                    data-confirm-title="Submit Proposal?"
                                    data-confirm-ok="Yes, Submit Proposal"
                                    class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Submit</button>
                            </form>
                        </div>
                    </div>
                @elseif($isSponsor && $project->status === 'submitted')
                    <h3 class="text-lg font-semibold mb-3">Keputusan Sponsor</h3>
                    <div class="mb-3"><label class="block text-sm font-semibold text-gray-600 mb-1">Note</label>
                        <textarea form="approveForm" id="sponsor-decision-note" name="note" rows="2" class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200" placeholder="Note (required when requesting revision)"></textarea></div>
                    <div class="flex gap-3">
                        <form id="approveForm" method="POST" action="{{ route('projects.sponsor.approve', $project) }}">@csrf<button class="px-6 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">Approve</button></form>
                        <form method="POST" action="{{ route('projects.sponsor.revision', $project) }}" onsubmit="this.note.value=document.getElementById('sponsor-decision-note').value">@csrf<input type="hidden" name="note"><button class="px-6 py-2 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700">Request Revision</button></form>
                    </div>
                @elseif($isReviewer)
                    <h3 class="text-lg font-semibold mb-3">Keputusan Committee {{ $project->status === 'completion_review' ? 'Completion' : 'Proposal' }} (Layer {{ $project->current_layer }})</h3>
                    <div class="mb-3"><label class="block text-sm font-semibold text-gray-600 mb-1">Note</label>
                        <textarea form="pApprove" id="committee-decision-note" name="note" rows="2" class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200" placeholder="Note (optional)"></textarea></div>
                    <div class="flex gap-3">
                        <form id="pApprove" method="POST" action="{{ route('projects.review.approve', $project) }}">@csrf<button class="px-6 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">Approve</button></form>
                        <form method="POST" action="{{ route('projects.review.reject', $project) }}" onsubmit="this.note.value=document.getElementById('committee-decision-note').value">@csrf<input type="hidden" name="note"><button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Reject</button></form>
                    </div>
                @endif

                {{-- Cancel Project — HANYA Project Leader & Project Sponsor. Klik → dialog isi alasan. --}}
                @if($canCancel)
                    <div class="border-t pt-4 flex justify-end">
                        <button type="button" @click="cancelOpen = true" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Cancel Project</button>
                    </div>

                    <div x-show="cancelOpen" x-cloak class="{{ $modalWrap }}" @keydown.escape.window="cancelOpen = false">
                        <div class="fixed inset-0 bg-black/40" @click="cancelOpen = false"></div>
                        <form method="POST" action="{{ route('projects.cancel', $project) }}" class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" x-transition.opacity>
                            @csrf
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 w-10 h-10 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-xl">!</div>
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-800">Cancel Project</h3>
                                    <p class="text-sm text-gray-600 mt-1">Provide a cancellation reason. A cancelled project cannot be reopened.</p>
                                </div>
                            </div>
                            <textarea name="reason" required rows="3" placeholder="Cancellation reason (required)" class="w-full border rounded-lg px-3 py-2 mt-4 text-sm focus:ring focus:ring-red-200"></textarea>
                            <div class="flex justify-end gap-3 mt-4">
                                <button type="button" @click="cancelOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                                <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Yes, Cancel Project</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @endif

    </div>

</x-app-layout>
