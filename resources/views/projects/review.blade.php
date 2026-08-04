<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Review Project Proposals</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Review Project Proposals</h1>
            <p class="text-gray-500">Proposal project yang menunggu review Anda (sesuai layer committee proposal).</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif

        <x-list-toolbar :search="request('q')" placeholder="Cari Project ID atau nama…">
            <x-list-filters :business-units="$businessUnits" :departments="$departments"
                :statuses="['committee_review' => 'Proposal Review', 'completion_review' => 'Completion Review']" />
        </x-list-toolbar>

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <x-sortable-th column="project_id" :sort="$sort" :dir="$dir">Project ID</x-sortable-th>
                        <x-sortable-th column="project_name" :sort="$sort" :dir="$dir">Project Name</x-sortable-th>
                        <th class="px-6 py-3">Leader</th>
                        <th class="px-6 py-3">Target BU</th>
                        <th class="px-6 py-3">Review</th>
                        <x-sortable-th column="current_layer" :sort="$sort" :dir="$dir">Layer</x-sortable-th>
                        <th class="px-6 py-3">SLA</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($projects as $project)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-mono text-sm text-red-700">{{ $project->project_id }}</td>
                            <td class="px-6 py-4 font-semibold">{{ $project->project_name }}</td>
                            <td class="px-6 py-4 text-sm">{{ optional($project->leader)->name }}</td>
                            <td class="px-6 py-4 text-sm">{{ optional(optional($project->idea)->businessUnit)->name }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex px-2 py-1 text-xs rounded-full {{ $project->status === 'completion_review' ? 'bg-teal-100 text-teal-700' : 'bg-purple-100 text-purple-700' }}">
                                    {{ $project->status === 'completion_review' ? 'Completion' : 'Proposal' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">Layer {{ $project->current_layer }}</td>
                            <td class="px-6 py-4">
                                <x-sla-badge :sla="app(\App\Services\SlaService::class)->evaluate($project->slaApprovalType(), $project->reviewSince())" />
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('projects.show', $project) }}"
                                   class="px-4 py-1 text-sm bg-red-700 text-white rounded-lg hover:bg-red-800">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-8 text-center text-gray-400">Tidak ada proposal yang menunggu review Anda.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div>{{ $projects->links() }}</div>

    </div>

</x-app-layout>
