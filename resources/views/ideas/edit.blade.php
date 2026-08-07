<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Edit Draft Idea</h2>
    </x-slot>

    <div class="p-6 space-y-6 max-w-4xl">

        <div>
            <a href="{{ route('ideas.index') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to My Ideas</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Edit Draft Idea</h1>
            <p class="text-gray-500">Edit your draft before submitting</p>
        </div>

        @include('ideas._form', [
            'action' => route('ideas.update', $idea),
            'method' => 'PUT',
        ])

    </div>

</x-app-layout>
