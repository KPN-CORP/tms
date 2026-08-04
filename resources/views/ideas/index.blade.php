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
                <a href="{{ route('ideas.drafts') }}"
                   class="px-4 py-2 border rounded-lg hover:bg-gray-100">Drafts</a>
                <a href="{{ route('ideas.create') }}"
                   class="px-5 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">+ Create Idea</a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        <x-list-toolbar :search="request('q')" placeholder="Cari ID Idea, nama, atau problem…">
            <x-list-filters :business-units="$businessUnits" :departments="$departments"
                :statuses="['submitted' => 'Submitted', 'review' => 'On Review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'project_created' => 'Project Created']" />
        </x-list-toolbar>

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <x-sortable-th column="idea_id" :sort="$sort" :dir="$dir">Idea ID</x-sortable-th>
                            <x-sortable-th column="idea_name" :sort="$sort" :dir="$dir">Idea Name</x-sortable-th>
                            <th class="px-6 py-3">Target BU</th>
                            <th class="px-6 py-3">Target Dept</th>
                            <x-sortable-th column="status" :sort="$sort" :dir="$dir">Status</x-sortable-th>
                            <x-sortable-th column="modified_at" :sort="$sort" :dir="$dir">Last Update</x-sortable-th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($ideas as $idea)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-mono text-sm text-red-700">{{ $idea->idea_id }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold">{{ $idea->idea_name }}</div>
                                    <div class="text-sm text-gray-400 truncate max-w-xs">{{ $idea->problem }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm">{{ optional($idea->businessUnit)->name }}</td>
                                <td class="px-6 py-4 text-sm">{{ optional($idea->department)->name }}</td>
                                <td class="px-6 py-4">@include('ideas._status', ['status' => $idea->status])</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $idea->modified_at?->format('d M Y') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('ideas.show', $idea) }}"
                                       class="px-4 py-1 text-sm border border-red-700 text-red-700 rounded-lg hover:bg-red-50">Manage</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-400">Belum ada ide yang cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $ideas->links() }}</div>

    </div>

</x-app-layout>
