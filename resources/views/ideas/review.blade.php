<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Review Ideas</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Review Ideas</h1>
            <p class="text-gray-500">Ide yang menunggu review Anda (sesuai layer committee Anda).</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET" class="bg-white rounded-xl shadow overflow-hidden">

            <x-list-filter-bar :business-units="$businessUnits" :departments="$departments"
                :statuses="['submitted' => 'Submitted', 'review' => 'On Review']"
                :reset-route="route('ideas.review')" placeholder="Cari ID Idea, nama, atau problem…" />

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
                            <th class="px-6 py-3">SLA</th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($ideas as $idea)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-center text-gray-500">{{ $ideas->firstItem() + $loop->index }}</td>
                                <td class="px-6 py-4 font-mono text-sm text-red-700">{{ $idea->idea_id }}</td>
                                <td class="px-6 py-4 font-semibold">{{ $idea->idea_name }}</td>
                                <td class="px-6 py-4 text-sm">{{ optional($idea->user)->name }}</td>
                                <td class="px-6 py-4 text-sm">{{ optional($idea->businessUnit)->name }}</td>
                                <td class="px-6 py-4 text-sm">Layer {{ $idea->current_layer }}</td>
                                <td class="px-6 py-4">@include('ideas._status', ['status' => $idea->status])</td>
                                <td class="px-6 py-4">
                                    <x-sla-badge :sla="app(\App\Services\SlaService::class)->evaluate($idea->slaApprovalType(), $idea->reviewSince())" />
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('ideas.review.show', $idea) }}"
                                       class="px-4 py-1 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-6 py-8 text-center text-gray-400">Tidak ada ide yang menunggu review Anda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-list-footer :paginator="$ideas" :per-page="$perPage" />

        </form>

    </div>

</x-app-layout>
