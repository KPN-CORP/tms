<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Role Management</h2>
    </x-slot>

    <div class="space-y-6">

        {{-- Header + Breadcrumb --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">Manage Role</h1>
            <div class="text-sm">
                <a href="{{ route('admin.roles.index') }}" class="text-red-700 font-medium">Roles</a>
                <span class="text-gray-400 mx-1">&rsaquo;</span>
                <span class="text-gray-400">Manage</span>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.create') }}"
               class="px-6 py-2 rounded-full border border-red-700 text-red-700 font-semibold hover:bg-red-50">Create Role</a>
            <a href="{{ route('admin.roles.index') }}"
               class="px-6 py-2 rounded-full bg-red-700 text-white font-semibold">Manage Role</a>
        </div>

        {{-- Success --}}
        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-600 text-sm">
                    <tr>
                        <th class="px-6 py-3">Role</th>
                        <th class="px-6 py-3">Permissions</th>
                        <th class="px-6 py-3">Restrict (BU / Co / Loc / Emp)</th>
                        <th class="px-6 py-3">Users</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($roles as $role)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-semibold">{{ $role->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $role->permissions->count() }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $role->business_units_count }} /
                                {{ $role->companies_count }} /
                                {{ $role->locations_count }} /
                                {{ $role->employees_count }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $role->users_count }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.roles.assign-user', $role) }}"
                                       class="px-3 py-1 text-sm border border-red-700 text-red-700 rounded-lg hover:bg-red-50">Assign Users</a>
                                    <a href="{{ route('admin.roles.edit', $role) }}"
                                       class="px-3 py-1 text-sm border rounded-lg hover:bg-gray-100">Edit</a>
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                                          onsubmit="return confirm('Delete role {{ $role->name }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1 text-sm border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-400">No roles yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

</x-app-layout>
