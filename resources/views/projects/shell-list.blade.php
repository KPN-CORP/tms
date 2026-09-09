<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Project Shell</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Project Shell</h1>
            <p class="text-gray-500">Approved ideas you reviewed at the last committee layer, and the project shell assigned to each.</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @php
            // Angka tab dari $counts (per status shell). Tab "all" = jumlah semua.
            $tabCount = fn ($key) => $key === 'all' ? $total : $counts->get($key, 0);
            $tabUrl   = fn ($key) => request()->url() . '?' . http_build_query(array_merge(request()->query(), ['tab' => $key, 'page' => 1]));

            // onclick untuk baris yang TIDAK boleh dibuatkan/dilanjutkan shell-nya
            // (mis. Super Admin yang bukan committee layer terakhir ide tsb):
            // memunculkan dialog konfirmasi global (layouts.app) alih-alih
            // mengarahkan ke halaman 403. detail.button sengaja tidak diisi
            // sehingga Cancel maupun OK hanya menutup dialog.
            $denyDialog = function (string $action) {
                $detail = json_encode([
                    'title'   => 'Not Allowed',
                    'message' => $action === 'create'
                        ? 'Only the last committee layer of this idea can create its project shell.'
                        : 'Only the last committee layer of this idea can edit this draft project shell.',
                    'ok'      => 'OK',
                ]);

                return "window.dispatchEvent(new CustomEvent('confirm-request', { detail: {$detail} }))";
            };
        @endphp

        {{-- Tab status + jumlah --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-gray-200">
            @foreach($tabDefs as $key => $label)
                @php $active = $tab === $key; @endphp
                <a href="{{ $tabUrl($key) }}"
                   class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition
                          {{ $active ? 'border-red-700 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                    {{ $label }}
                    <span class="ml-1 inline-flex items-center justify-center min-w-5 px-1.5 py-0.5 text-xs rounded-full
                                 {{ $active ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $tabCount($key) }}
                    </span>
                </a>
            @endforeach
        </div>

        <form method="GET" class="bg-white rounded-xl shadow overflow-hidden">

            {{-- Pertahankan tab aktif saat search/filter di-submit --}}
            <input type="hidden" name="tab" value="{{ $tab }}">

            {{-- Filter: Search + Business Unit + Unit (dari hcis, cascade). Tanpa placeholder. --}}
            <div class="flex flex-wrap items-start gap-3 p-4 border-b border-gray-100">
                @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif

                <div class="w-60">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Search</label>
                    <input name="q" value="{{ request('q') }}" placeholder=""
                           x-data x-on:input.debounce.500ms="$el.form.requestSubmit()"
                           class="w-full h-[38px] border border-gray-300 rounded-lg px-3 text-sm focus:ring focus:ring-red-200">
                </div>

                <div class="w-60">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Business Unit</label>
                    {{-- Daftar BU di-fetch dari endpoint (org.business-units), bukan server-side --}}
                    <select name="bu" id="filter-bu" data-remote-options="{{ route('org.business-units') }}"
                            onchange="var u=document.getElementById('filter-unit'); if(u.tomselect){u.tomselect.clear(true);}else{u.value='';} this.form.requestSubmit()"
                            class="w-full h-[38px] appearance-none border border-gray-300 rounded-lg px-3 text-sm bg-white">
                        <option value=""></option>
                        @if(request('bu'))<option value="{{ request('bu') }}" selected>{{ request('bu') }}</option>@endif
                    </select>
                </div>

                <div class="w-60">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Unit</label>
                    {{-- Cascade via AJAX dari hcis (departments.department_name) sesuai BU terpilih --}}
                    <select name="unit" id="filter-unit"
                            data-remote-parent="#filter-bu" data-remote-url="{{ route('org.unit-names') }}" data-selected="{{ request('unit') }}"
                            onchange="this.form.requestSubmit()"
                            class="w-full h-[38px] appearance-none border border-gray-300 rounded-lg px-3 text-sm bg-white">
                        <option value=""></option>
                        @if(request('unit'))<option value="{{ request('unit') }}" selected>{{ request('unit') }}</option>@endif
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-transparent mb-1 select-none">.</label>
                    <button class="h-[38px] px-5 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Apply</button>
                </div>

                @if(request('q') || request('bu') || request('unit'))
                    <div>
                        <label class="block text-xs font-semibold text-transparent mb-1 select-none">.</label>
                        <a href="{{ route('projects.shell', ['tab' => $tab]) }}"
                           class="inline-flex items-center h-[38px] px-4 border rounded-lg text-sm hover:bg-gray-100">Reset</a>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 w-12 text-center">No</th>
                            <x-sortable-th column="idea_id" :sort="$sort" :dir="$dir">Idea ID</x-sortable-th>
                            <x-sortable-th column="project_id" :sort="$sort" :dir="$dir">Project ID</x-sortable-th>
                            <x-sortable-th column="project_name" :sort="$sort" :dir="$dir">Project Name</x-sortable-th>
                            <th class="px-6 py-3">Category</th>
                            <th class="px-6 py-3">Leader</th>
                            <th class="px-6 py-3">Sponsor</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($rows as $row)
                            @php
                                // Satu baris = satu (ide, shell). shell null → ide belum dibuatkan shell.
                                $shell       = $row->shell_pk ? $shells->get($row->shell_pk) : null;
                                $shellStatus = \App\Models\Project::shellStatusFor($shell);
                                [$stLabel, $stCls] = \App\Models\Project::shellStatusBadge($shellStatus);
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-center text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="px-6 py-4 font-mono text-sm text-red-700 whitespace-nowrap">{{ $row->idea_id }}</td>
                                <td class="px-6 py-4 font-mono text-sm whitespace-nowrap {{ optional($shell)->project_id ? 'text-red-700' : 'text-gray-300' }}">
                                    {{ optional($shell)->project_id ?: '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    {{-- Belum ada shell (atau draft tanpa nama): pakai nama ide. --}}
                                    @php($name = optional($shell)->project_name ?: $row->idea_name)
                                    <div class="font-semibold" title="{{ $name }}">{{ \Illuminate\Support\Str::words($name, 3, '...') }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm">{{ optional($shell)->project_category ?: '—' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <div>{{ optional(optional($shell)->leader)->name ?: '—' }}</div>
                                    @if(optional(optional($shell)->leader)->employee_id)<div class="text-xs text-gray-400">{{ $shell->leader->employee_id }}</div>@endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div>{{ optional(optional($shell)->sponsor)->name ?: '—' }}</div>
                                    @if(optional(optional($shell)->sponsor)->employee_id)<div class="text-xs text-gray-400">{{ $shell->sponsor->employee_id }}</div>@endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full font-medium {{ $stCls }}">{{ $stLabel }}</span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($shellStatus === 'not_assigned')
                                            @if($row->can_create_shell)
                                                <a href="{{ route('projects.create', ['idea_id' => $row->idea_id]) }}"
                                                   class="px-4 py-1 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">+ Create Project</a>
                                            @else
                                                {{-- Admin yang bukan committee layer terakhir: tombol tetap ada
                                                     tapi menjelaskan lewat dialog, bukan halaman 403. --}}
                                                <button type="button" onclick="{{ $denyDialog('create') }}"
                                                        class="px-4 py-1 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">+ Create Project</button>
                                            @endif
                                        @elseif($shellStatus === 'draft')
                                            @if($row->can_create_shell)
                                                <a href="{{ route('projects.create', ['idea_id' => $row->idea_id]) }}"
                                                   class="px-3 py-1 text-sm border rounded-lg hover:bg-gray-100">Edit</a>
                                            @else
                                                <button type="button" onclick="{{ $denyDialog('continue') }}"
                                                        class="px-3 py-1 text-sm border rounded-lg hover:bg-gray-100">Edit</button>
                                            @endif
                                            {{-- Form-nya ada DI LUAR form GET halaman ini (lihat bawah);
                                                 tombol menunjuknya lewat atribut form="..." karena tag
                                                 form bersarang diabaikan browser. --}}
                                            <button type="submit" form="shell-draft-delete-{{ $shell->id }}"
                                                    data-confirm="Delete this draft project shell?" data-confirm-title="Delete Draft" data-confirm-ok="Yes, Delete"
                                                    class="px-3 py-1 text-sm border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                        @else
                                            <a href="{{ route('projects.shell.progress', $shell) }}" title="View progress"
                                               class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-100">
                                                <span class="sr-only">View progress</span>
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-6 py-8 text-center text-gray-400">No approved ideas yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-list-footer :paginator="$rows" :per-page="$perPage" />

        </form>

        {{-- Form hapus draft — WAJIB di luar form GET di atas: browser mengabaikan
             tag <form> bersarang, sehingga tombolnya akan ikut men-submit filter. --}}
        @foreach($rows as $row)
            @if($row->shell_is_draft)
                <form id="shell-draft-delete-{{ $row->shell_pk }}" method="POST"
                      action="{{ route('projects.shell.draft.destroy', $row->shell_pk) }}" class="hidden">
                    @csrf @method('DELETE')
                </form>
            @endif
        @endforeach

    </div>

</x-app-layout>
