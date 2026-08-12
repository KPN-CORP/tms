<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">User Management</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
                <p class="text-gray-500">Kelola akun user, grade/job level, unit, dan role.</p>
            </div>
            <a href="{{ route('admin.users.create') }}" class="px-5 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800 whitespace-nowrap">+ Add User</a>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">{{ session('error') }}</div>
        @endif

        {{-- Filter --}}
        <form method="GET" class="flex flex-wrap items-end gap-3 bg-white rounded-xl shadow p-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Search</label>
                <input name="q" value="{{ $search }}" placeholder="Nama, email, atau employee ID…"
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Role</label>
                <select name="role" class="border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200">
                    <option value="">Semua role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected($roleFilter === $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">Filter</button>
            @if($search !== '' || $roleFilter)
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-100">Reset</a>
            @endif
        </form>

        {{-- List --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Employee ID</th>
                            <th class="px-4 py-3">Unit / Dept</th>
                            <th class="px-4 py-3">Grade</th>
                            <th class="px-4 py-3">Roles</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-800">{{ $user->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $user->employee_id ?? '-' }}</td>
                                <td class="px-4 py-3 text-xs">
                                    {{ $user->businessUnit?->name ?? '-' }}
                                    @if($user->department)<div class="text-gray-400">{{ $user->department->name }}</div>@endif
                                </td>
                                <td class="px-4 py-3">{{ $user->job_level ? $user->job_level.' — '.(\App\Models\User::JOB_LEVELS[$user->job_level] ?? '?') : '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($user->roles as $role)
                                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">{{ $role->name }}</span>
                                        @empty
                                            <span class="text-xs text-gray-400">no role</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="px-3 py-1 text-xs border rounded-lg hover:bg-gray-100">Edit</a>
                                        @if($user->id !== auth()->id())
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button data-confirm="Hapus user {{ $user->name }}?" data-confirm-title="Hapus User" data-confirm-ok="Ya, Hapus" class="px-3 py-1 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada user yang cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $users->links() }}</div>

    </div>

</x-app-layout>
