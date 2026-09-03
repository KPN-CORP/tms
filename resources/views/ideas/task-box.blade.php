<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Task Box</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Task Box</h1>
            <p class="text-gray-500">Ideas that need your review, or that are approved or rejected — all in one place.</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @php
            $tabDefs = [
                'all'       => 'All',
                'submitted' => 'Submitted',
                'approved'  => 'Approved',
                'review'    => 'On Review',
                'rejected'  => 'Rejected',
            ];
            // URL tab: pertahankan filter/sort yang aktif, reset ke halaman 1.
            $tabUrl = fn ($key) => request()->url() . '?' . http_build_query(array_merge(request()->query(), ['tab' => $key, 'page' => 1]));
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
                        {{ $counts[$key] ?? 0 }}
                    </span>
                </a>
            @endforeach
        </div>

        <form method="GET" class="bg-white rounded-xl shadow overflow-hidden">

            {{-- Pertahankan tab aktif saat search/filter di-submit --}}
            <input type="hidden" name="tab" value="{{ $tab }}">

            {{-- Filter: Search + Business Unit + Unit (dari hcis, cascade).
                 Markup & perilakunya dibuat sama persis dgn halaman My Ideas. --}}
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
                    {{-- Ganti BU → reset Unit lalu submit (agar filter Unit lama tidak ikut) --}}
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
                        <a href="{{ route('ideas.taskbox', ['tab' => $tab]) }}"
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
                            <x-sortable-th column="idea_name" :sort="$sort" :dir="$dir">Idea Name</x-sortable-th>
                            <th class="px-6 py-3">Submitter</th>
                            <th class="px-6 py-3">Target BU</th>
                            <th class="px-6 py-3">Target Unit</th>
                            <x-sortable-th column="current_layer" :sort="$sort" :dir="$dir">Layer</x-sortable-th>
                            <x-sortable-th column="status" :sort="$sort" :dir="$dir">Status</x-sortable-th>
                            <th class="px-6 py-3">SLA</th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($ideas as $idea)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-center text-gray-500">{{ $ideas->firstItem() + $loop->index }}</td>
                                <td class="px-6 py-4 font-mono text-sm text-red-700 whitespace-nowrap">{{ $idea->idea_id }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold" title="{{ $idea->idea_name }}">{{ \Illuminate\Support\Str::words($idea->idea_name, 3, '…') }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm">{{ optional($idea->user)->name }}</td>
                                <td class="px-6 py-4 text-sm">{{ $idea->business_unit_name ?: optional($idea->businessUnit)->name }}</td>
                                <td class="px-6 py-4 text-sm">{{ $idea->department_name ?: optional($idea->department)->name }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span x-data="{ open:false, x:0, y:0 }"
                                          @mouseenter="const r=$el.getBoundingClientRect(); x=Math.round(r.left); y=Math.round(r.bottom+6); open=true"
                                          @mouseleave="open=false"
                                          class="cursor-help inline-flex items-center"><span class="inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 text-xs font-semibold px-1.5 py-0.5">L{{ $idea->current_layer }}</span>
                                        <template x-teleport="body">
                                            <div x-show="open" x-cloak :style="'position:fixed;left:'+x+'px;top:'+y+'px;z-index:60;'"
                                                 class="rounded-lg shadow-lg border border-gray-200 bg-white py-2 px-3 text-xs">
                                                <div class="font-semibold text-gray-500 mb-1">Committee Layers</div>
                                                @forelse($idea->layer_chain as $c)
                                                    <div class="flex items-center gap-2 py-0.5" style="white-space:nowrap;">
                                                        <span class="inline-flex items-center justify-center rounded font-semibold {{ $c['layer'] == $idea->current_layer ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}" style="min-width:1.75rem;padding:.05rem .3rem;">L{{ $c['layer'] }}</span>
                                                        <span class="{{ $c['layer'] == $idea->current_layer ? 'font-semibold text-red-700' : 'text-gray-700' }}">{{ $c['name'] }}</span>
                                                    </div>
                                                @empty
                                                    <div class="text-gray-400">No committee</div>
                                                @endforelse
                                            </div>
                                        </template>
                                    </span>
                                </td>
                                <td class="px-6 py-4">@include('ideas._status', ['status' => $idea->status])</td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    @php $sla = app(\App\Services\SlaService::class)->evaluate($idea->slaApprovalType(), $idea->reviewSince()); @endphp
                                    <x-datetime :value="$sla['due'] ?? null" mode="date" />
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    @if($idea->can_review)
                                        <a href="{{ route('ideas.review.show', $idea) }}"
                                           class="inline-block whitespace-nowrap px-4 py-1 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">Review</a>
                                    @elseif($idea->can_create_shell)
                                        <a href="{{ route('projects.create', ['idea_id' => $idea->idea_id]) }}"
                                           class="inline-flex flex-col items-center leading-tight px-4 py-1.5 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">
                                            <span>+ Create</span>
                                            <span>Project</span>
                                        </a>
                                    @else
                                        <a href="{{ route('ideas.review.show', $idea) }}"
                                           class="inline-block whitespace-nowrap px-4 py-1 text-sm border border-red-700 text-red-700 rounded-lg hover:bg-red-50">Detail</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-6 py-8 text-center text-gray-400">No ideas in this tab.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-list-footer :paginator="$ideas" :per-page="$perPage" />

        </form>

    </div>

</x-app-layout>
