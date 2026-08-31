<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">My Ideas</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">My Ideas</h1>
                <p class="text-gray-500">Track and manage your improvement ideas</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('ideas.create') }}"
                   class="px-5 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">+ Create Idea</a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @php
            $tabDefs = [
                'all'       => 'All',
                'draft'     => 'Draft',
                'submitted' => 'Submitted',
                'review'    => 'On Review',
                'approved'  => 'Approved',
                'rejected'  => 'Rejected',
            ];
            $tabCount = fn ($key) => $key === 'all' ? $counts->sum() : $counts->get($key, 0);
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

        {{-- Satu card: toolbar + tabel + footer (tanpa gap) --}}
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
                        <a href="{{ route('ideas.index', ['tab' => $tab]) }}"
                           class="inline-flex items-center h-[38px] px-4 border rounded-lg text-sm hover:bg-gray-100">Reset</a>
                    </div>
                @endif
            </div>

            {{-- Tabel --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 w-12 text-center">No</th>
                            <x-sortable-th column="idea_id" :sort="$sort" :dir="$dir">Idea ID</x-sortable-th>
                            <x-sortable-th column="idea_name" :sort="$sort" :dir="$dir">Idea Name</x-sortable-th>
                            <th class="px-6 py-3">Target BU</th>
                            <th class="px-6 py-3">Target Unit</th>
                            <x-sortable-th column="status" :sort="$sort" :dir="$dir">Status</x-sortable-th>
                            <x-sortable-th column="modified_at" :sort="$sort" :dir="$dir">Last Update</x-sortable-th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($ideas as $idea)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-center text-gray-500">{{ $ideas->firstItem() + $loop->index }}</td>
                                <td class="px-6 py-4 font-mono text-sm text-red-700 whitespace-nowrap">{{ $idea->idea_id }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold" title="{{ $idea->idea_name }}">{{ \Illuminate\Support\Str::words($idea->idea_name, 2, '...') }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm">{{ optional($idea->businessUnit)->name }}</td>
                                <td class="px-6 py-4 text-sm">{{ optional($idea->department)->name }}</td>
                                <td class="px-6 py-4">@include('ideas._status', ['status' => $idea->status])</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $idea->modified_at?->format('d M Y') }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if($idea->status === 'draft')
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('ideas.edit', $idea) }}"
                                               class="px-3 py-1 text-sm border rounded-lg hover:bg-gray-100">Edit</a>
                                            <form method="POST" action="{{ route('ideas.destroy', $idea) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        data-confirm="Delete this draft?" data-confirm-title="Delete Draft" data-confirm-ok="Yes, Delete"
                                                        class="px-3 py-1 text-sm border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                            </form>
                                        </div>
                                    @else
                                        <a href="{{ route('ideas.show', $idea) }}"
                                           class="px-4 py-1 text-sm border border-red-700 text-red-700 rounded-lg hover:bg-red-50">Detail</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-400">No matching ideas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-list-footer :paginator="$ideas" :per-page="$perPage" />

        </form>

    </div>

</x-app-layout>
