<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">{{ $pageTitle }}</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $pageTitle }}</h1>
            <p class="text-gray-500">{{ $pageSubtitle }}</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @php
            // Angka tab dari $counts (status DB via $statusMap). $tabDefs = [key => label].
            $tabCount = fn ($key) => $key === 'all' ? $counts->sum() : $counts->get($statusMap[$key] ?? '', 0);
            $tabUrl   = fn ($key) => request()->url() . '?' . http_build_query(array_merge(request()->query(), ['tab' => $key, 'page' => 1]));
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
                        <a href="{{ route($routeName, ['tab' => $tab]) }}"
                           class="inline-flex items-center h-[38px] px-4 border rounded-lg text-sm hover:bg-gray-100">Reset</a>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 w-12 text-center">No</th>
                            <x-sortable-th column="project_id" :sort="$sort" :dir="$dir">Project ID</x-sortable-th>
                            <x-sortable-th column="project_name" :sort="$sort" :dir="$dir">Project Name</x-sortable-th>
                            <x-sortable-th column="project_category" :sort="$sort" :dir="$dir">Category</x-sortable-th>
                            <th class="px-6 py-3">Leader</th>
                            <th class="px-6 py-3">Sponsor</th>
                            <x-sortable-th column="status" :sort="$sort" :dir="$dir">Status</x-sortable-th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($projects as $project)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-center text-gray-500">{{ $projects->firstItem() + $loop->index }}</td>
                                <td class="px-6 py-4 font-mono text-sm text-red-700 whitespace-nowrap">{{ $project->project_id }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold" title="{{ $project->project_name }}">{{ \Illuminate\Support\Str::words($project->project_name, 3, '...') }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm">{{ $project->project_category }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <div>{{ optional($project->leader)->name }}</div>
                                    @if(optional($project->leader)->employee_id)<div class="text-xs text-gray-400">{{ $project->leader->employee_id }}</div>@endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div>{{ optional($project->sponsor)->name }}</div>
                                    @if(optional($project->sponsor)->employee_id)<div class="text-xs text-gray-400">{{ $project->sponsor->employee_id }}</div>@endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @php([$stLabel, $stCls] = $project->statusBadge())
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full font-medium {{ $stCls }}">{{ $stLabel }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    {{-- Aksi: pensil = edit (hanya bila user berwenang mengubah pada
                                         fase ini), mata = lihat saja. Bisa muncul salah satu atau keduanya.
                                         Tujuan link bisa diganti per halaman (mis. Project Shell yang read-only,
                                         sehingga pensil tidak pernah muncul di sana).

                                         CATATAN: assignment di bawah sengaja memakai bentuk inline. Menuliskan
                                         penutup blok PHP Blade di file ini — termasuk di dalam komentar seperti
                                         ini — akan dipasangkan dengan pembuka inline pada kolom Status dan
                                         membuat isi di antaranya ikut tertelan saat dikompilasi. --}}
                                    @php($route = $detailRoute ?? 'projects.show')
                                    @php($params = ($detailPhase ?? null) ? ['project' => $project, 'phase' => $detailPhase] : ['project' => $project])
                                    @php($canEditRow = $route === 'projects.show' && $project->isEditableInPhase(auth()->user(), $detailPhase ?? null))
                                    <div class="flex items-center justify-end gap-2">
                                        @if($canEditRow)
                                            <a href="{{ route($route, $params) }}" title="Edit"
                                               class="inline-flex items-center justify-center w-8 h-8 border border-red-700 text-red-700 rounded-lg hover:bg-red-50">
                                                <span class="sr-only">Edit</span>
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                        @endif
                                        <a href="{{ route($route, $params + ['view' => 1]) }}" title="View detail"
                                           class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-100">
                                            <span class="sr-only">View detail</span>
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-400">No projects yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-list-footer :paginator="$projects" :per-page="$perPage" />

        </form>

    </div>

</x-app-layout>
