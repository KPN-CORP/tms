<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Create New Idea</h2>
    </x-slot>

    {{-- Lebar konten mengikuti Project Detail: max-w-7xl + mx-auto, jadi otomatis
         terpusat kembali saat sidebar disembunyikan. --}}
    <div class="p-6 space-y-6 mx-auto w-full max-w-7xl">

        <div>
            <a href="{{ route('ideas.index') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to My Ideas</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Create New Idea</h1>
            <p class="text-gray-500">Submit your improvement idea for review</p>
        </div>

        @include('ideas._form', [
            'action' => route('ideas.store'),
            'method' => 'POST',
        ])

    </div>

</x-app-layout>
