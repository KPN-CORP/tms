<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Create Role</h2>
    </x-slot>

    <div class="space-y-6">

        {{-- Header + Breadcrumb --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">Create</h1>
            <div class="text-sm">
                <a href="{{ route('admin.roles.index') }}" class="text-red-700 font-medium">Roles</a>
                <span class="text-gray-400 mx-1">&rsaquo;</span>
                <span class="text-gray-400">Create</span>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.create') }}"
               class="px-6 py-2 rounded-full bg-red-700 text-white font-semibold">Create Role</a>
            <a href="{{ route('admin.roles.index') }}"
               class="px-6 py-2 rounded-full border border-red-700 text-red-700 font-semibold hover:bg-red-50">Manage Role</a>
        </div>

        @include('admin.role._form', [
            'action'      => route('admin.roles.store'),
            'method'      => 'POST',
            'submitLabel' => 'Create Role',
        ])

    </div>

</x-app-layout>
