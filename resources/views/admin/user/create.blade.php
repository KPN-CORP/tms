<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Add User</h2>
    </x-slot>

    <div class="p-6 max-w-3xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Add User</h1>
            <p class="text-gray-500">Buat akun user baru, set grade/unit, dan assign role.</p>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" class="bg-white rounded-xl shadow p-6 space-y-4">
            @csrf
            @include('admin.user._form', ['u' => null])

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.users.index') }}" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</a>
                <button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Create User</button>
            </div>
        </form>
    </div>

</x-app-layout>
