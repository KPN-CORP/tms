<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Task Box</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Task Box</h1>
            <p class="text-gray-500">Project proposals awaiting your review (per your proposal committee layer).</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif

        <form method="GET" class="bg-white rounded-xl shadow overflow-hidden">

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
                        <a href="{{ route('projects.review') }}"
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
                            <th class="px-6 py-3">Leader</th>
                            <th class="px-6 py-3">Target BU</th>
                            <th class="px-6 py-3">Review</th>
                            <x-sortable-th column="current_layer" :sort="$sort" :dir="$dir">Layer</x-sortable-th>
                            <th class="px-6 py-3">SLA</th>
                            <th class="px-6 py-3">Status</th>
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
                                <td class="px-6 py-4 text-sm">{{ optional($project->leader)->name }}</td>
                                <td class="px-6 py-4 text-sm">{{ optional(optional($project->idea)->businessUnit)->name }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full {{ $project->status === 'completion_review' ? 'bg-teal-100 text-teal-700' : 'bg-purple-100 text-purple-700' }}">
                                        {{ $project->status === 'completion_review' ? 'Completion' : 'Proposal' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 text-xs font-semibold px-1.5 py-0.5">L{{ $project->current_layer }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <x-sla-badge :sla="app(\App\Services\SlaService::class)->evaluate($project->slaApprovalType(), $project->reviewSince())" />
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @php([$stLabel, $stCls] = $project->statusBadge())
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full font-medium {{ $stCls }}">{{ $stLabel }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('projects.show', $project) }}"
                                       class="px-4 py-1 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-6 py-8 text-center text-gray-400">No proposals awaiting your review.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-list-footer :paginator="$projects" :per-page="$perPage" />

        </form>

    </div>

</x-app-layout>
