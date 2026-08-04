<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Approved Ideas</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Approved Ideas</h1>
            <p class="text-gray-500">Ide yang sudah disetujui seluruh layer. Buat Project Shell (1 ide bisa banyak project).</p>
        </div>

        <x-list-toolbar :search="request('q')" placeholder="Cari ID Idea, nama, atau problem…">
            <x-list-filters :business-units="$businessUnits" :departments="$departments" />
        </x-list-toolbar>

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <x-sortable-th column="idea_id" :sort="$sort" :dir="$dir">Idea ID</x-sortable-th>
                        <x-sortable-th column="idea_name" :sort="$sort" :dir="$dir">Idea Name</x-sortable-th>
                        <th class="px-6 py-3">Submitter</th>
                        <th class="px-6 py-3">Target BU</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($ideas as $idea)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-mono text-sm text-red-700">{{ $idea->idea_id }}</td>
                            <td class="px-6 py-4 font-semibold">{{ $idea->idea_name }}</td>
                            <td class="px-6 py-4 text-sm">{{ optional($idea->user)->name }}</td>
                            <td class="px-6 py-4 text-sm">{{ optional($idea->businessUnit)->name }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('projects.create', ['idea_id' => $idea->idea_id]) }}"
                                   class="px-4 py-1 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">+ Create Project</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">Belum ada ide approved di area Anda.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div>{{ $ideas->links() }}</div>

    </div>

</x-app-layout>
