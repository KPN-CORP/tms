<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">
            Create
        </h2>
    </x-slot>

    <div class="space-y-6">

        {{-- Page Header + Breadcrumb --}}
        <div class="flex items-center justify-between">

            <h1 class="text-2xl font-bold text-gray-800">
                Create
            </h1>
        {{-- class --}}    

            <div class="text-sm">
                <a href="{{ route('admin.roles.index') }}" class="text-red-700 font-medium">
                    Roles
                </a>
                <span class="text-gray-400 mx-1">&rsaquo;</span>
                <span class="text-gray-400">
                    Create
                </span>
            </div>

        </div>

        {{-- Tabs --}}
        <div class="flex items-center gap-3">

            <a
                href="{{ route('admin.roles.create') }}"
                class="px-6 py-2 rounded-full bg-red-700 text-white font-semibold">
                Create Role
            </a>

            <a
                href="{{ route('admin.roles.index') }}"
                class="px-6 py-2 rounded-full border border-red-700 text-red-700 font-semibold hover:bg-red-50">
                Manage Role
            </a>

            <a
                href="#"
                class="px-6 py-2 rounded-full border border-red-700 text-red-700 font-semibold hover:bg-red-50">
                Assign Users
            </a>

        </div>

            <a
                href="{{ route('admin.roles.index') }}"
                class="px-6 py-2 rounded-full border-red-700 text-red-700 font-semibold hover:bg-red-50">
                Manage Role

            </a>

        {{-- Card / Form --}}
        <form
            method="POST"
            action="{{ route('admin.roles.store') }}"
            class="bg-white rounded-xl shadow p-8">

            @csrf

            {{-- Success Message --}}
            @if(session('success'))
                <div class="mb-6 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Role Name + Submit --}}
            <div class="flex items-start justify-between gap-6">

                <div class="w-1/2">

                    <label class="block font-bold mb-2">
                        Role Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="Enter role name.."
                        class="w-full border rounded-lg px-4 py-2 focus:ring @error('name') border-red-500 focus:ring-red-200 @else focus:ring-red-200 @enderror">

                    @error('name')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                </div>

                <button
                    type="submit"
                    class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">
                    Create Role
                </button>

            </div>

            {{-- Restrict Group Company --}}
            <div class="mt-8">

                <label class="block font-bold mb-2">
                    Restrict Group Company (Keeping blank means no restrictions)
                </label>

                <select
                    id="select-business-units"
                    name="business_units[]"
                    multiple
                    placeholder="Type to search group company..."
                    class="w-full border rounded-lg">

                    @foreach($businessUnits as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->name }}
                        </option>
                    @endforeach

                </select>

            </div>

            {{-- Restrict Company --}}
            <div class="mt-8">

                <label class="block font-bold mb-2">
                    Restrict Company (Keeping blank means no restrictions)
                </label>

                <select
                    id="select-companies"
                    name="companies[]"
                    multiple
                    placeholder="Type to search company..."
                    class="w-full border rounded-lg">

                    @foreach($companies as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->name }}
                        </option>
                    @endforeach

                </select>

            </div>

            {{-- Restrict Location --}}
            <div class="mt-8">

                <label class="block font-bold mb-2">
                    Restrict Location (Keeping blank means no restrictions)
                </label>

                <select
                    id="select-locations"
                    name="locations[]"
                    multiple
                    placeholder="Type to search location..."
                    class="w-full border rounded-lg">

                    @foreach($locations as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->name }}
                        </option>
                    @endforeach

                </select>

            </div>

            {{-- Restrict Employee Name --}}
            <div class="mt-8">

                <label class="block font-bold mb-2">
                    Restrict Employee Name (Keeping blank means no restrictions)
                </label>

                <select
                    id="select-employees"
                    name="employees[]"
                    multiple
                    placeholder="Type to search employee name..."
                    class="w-full border rounded-lg">

                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">
                            {{ $employee->name }}
                        </option>
                    @endforeach

                </select>

            </div>

            {{-- Permission --}}
            <div class="mt-10">

                <h3 class="text-lg font-semibold mb-4">
                    Permissions
                </h3>

                <div class="grid grid-cols-3 gap-4">

                    @foreach($permissions as $permission)

                        <label class="flex items-center gap-3">

                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="{{ $permission->id }}"
                                class="rounded border-gray-300">

                            <span>
                                {{ $permission->name }}
                            </span>

                        </label>

                    @endforeach

                </div>

            </div>

        </form>

    </div>

    {{-- Tom Select: searchable multi-select dropdown --}}
    <link
        href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css"
        rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const selectIds = [
                '#select-business-units',
                '#select-companies',
                '#select-locations',
                '#select-employees',
            ];

            selectIds.forEach(function (id) {
                new TomSelect(id, {
                    plugins: ['remove_button'],
                    create: false,
                    hidePlaceholder: false,
                    maxOptions: null,
                });
            });

        });
    </script>

</x-app-layout>
