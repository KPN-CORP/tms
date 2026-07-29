<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Draft Ideas</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div class="flex items-start justify-between">
            <div>
                <a href="{{ route('ideas.index') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to My Ideas</a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1">Draft Ideas</h1>
            </div>
            <a href="{{ route('ideas.create') }}"
               class="px-5 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">+ Create New Idea</a>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <th class="px-6 py-3">Idea Name</th>
                        <th class="px-6 py-3">Targeted BU</th>
                        <th class="px-6 py-3">Targeted Department</th>
                        <th class="px-6 py-3">Last Updated</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($ideas as $idea)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-semibold">{{ $idea->idea_name }}</td>
                            <td class="px-6 py-4 text-sm">{{ optional($idea->businessUnit)->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm">{{ optional($idea->department)->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $idea->modified_at?->format('d M Y') }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('ideas.edit', $idea) }}"
                                       class="px-3 py-1 text-sm border rounded-lg hover:bg-gray-100">Edit</a>
                                    <form method="POST" action="{{ route('ideas.destroy', $idea) }}"
                                          onsubmit="return confirm('Delete this draft?');">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1 text-sm border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">Belum ada draft.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

</x-app-layout>
