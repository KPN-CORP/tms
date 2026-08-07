<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Task Box</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Task Box</h1>
            <p class="text-gray-500">Ide yang perlu Anda review, sudah disetujui, atau ditolak — dalam satu tempat.</p>
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
                'rejected'  => 'Reject',
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

            {{-- Filter: search + BU + Department (TANPA dropdown status; status pakai tab) --}}
            <x-list-filter-bar :business-units="$businessUnits" :departments="$departments"
                :reset-route="route('ideas.taskbox', ['tab' => $tab])" placeholder="Cari ID Idea, nama, atau problem…" />

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 w-12 text-center">No</th>
                            <x-sortable-th column="idea_id" :sort="$sort" :dir="$dir">Idea ID</x-sortable-th>
                            <x-sortable-th column="idea_name" :sort="$sort" :dir="$dir">Idea Name</x-sortable-th>
                            <th class="px-6 py-3">Submitter</th>
                            <th class="px-6 py-3">Target BU</th>
                            <x-sortable-th column="current_layer" :sort="$sort" :dir="$dir">Layer</x-sortable-th>
                            <x-sortable-th column="status" :sort="$sort" :dir="$dir">Status</x-sortable-th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($ideas as $idea)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-center text-gray-500">{{ $ideas->firstItem() + $loop->index }}</td>
                                <td class="px-6 py-4 font-mono text-sm text-red-700">{{ $idea->idea_id }}</td>
                                <td class="px-6 py-4 font-semibold" title="{{ $idea->idea_name }}">{{ \Illuminate\Support\Str::words($idea->idea_name, 3, '…') }}</td>
                                <td class="px-6 py-4 text-sm">{{ optional($idea->user)->name }}</td>
                                <td class="px-6 py-4 text-sm">{{ optional($idea->businessUnit)->name }}</td>
                                <td class="px-6 py-4 text-sm">Layer {{ $idea->current_layer }}</td>
                                <td class="px-6 py-4">@include('ideas._status', ['status' => $idea->status])</td>
                                <td class="px-6 py-4 text-right">
                                    @if($idea->can_review)
                                        <a href="{{ route('ideas.review.show', $idea) }}"
                                           class="px-4 py-1 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">Review</a>
                                    @elseif($idea->can_create_shell)
                                        <a href="{{ route('projects.create', ['idea_id' => $idea->idea_id]) }}"
                                           class="px-4 py-1 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">+ Create Project</a>
                                    @else
                                        <a href="{{ route('ideas.review.show', $idea) }}"
                                           class="px-4 py-1 text-sm border border-red-700 text-red-700 rounded-lg hover:bg-red-50">Detail</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-400">Tidak ada ide pada tab ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-list-footer :paginator="$ideas" :per-page="$perPage" />

        </form>

    </div>

</x-app-layout>
