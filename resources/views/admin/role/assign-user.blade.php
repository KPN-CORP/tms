<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Assign Users</h2>
    </x-slot>

    <div class="space-y-6">

        {{-- Header + Breadcrumb --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">Assign Users — {{ $role->name }}</h1>
            <div class="text-sm">
                <a href="{{ route('admin.roles.index') }}" class="text-red-700 font-medium">Roles</a>
                <span class="text-gray-400 mx-1">&rsaquo;</span>
                <span class="text-gray-400">Assign Users</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.roles.assign-user.store', $role) }}"
              class="bg-white rounded-xl shadow p-8">

            @csrf

            <p class="text-gray-500 mb-4">
                Cari & pilih user yang memiliki role <span class="font-semibold">{{ $role->name }}</span>.
            </p>

            <label class="block text-sm font-semibold text-gray-600 mb-1">User</label>
            <select name="users[]" multiple data-no-search
                    data-remote-search="{{ route('org.users') }}" data-remote-value="id"
                    placeholder="Ketik nama atau employee ID untuk mencari user..."
                    class="w-full border rounded-lg">
                @foreach($assignedUsers as $u)
                    <option value="{{ $u->id }}" selected>{{ $u->name }}{{ $u->employee_id ? ' - '.$u->employee_id : '' }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">User yang sudah ter-assign otomatis tampil terpilih. Ketik untuk menambah/mengganti.</p>

            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.roles.index') }}"
                   class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</a>
                <button type="submit"
                        class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save</button>
            </div>

        </form>

    </div>

</x-app-layout>
