<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Project Category</h2>
    </x-slot>

    @php
        $inp = 'w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200';
        $val = fn ($f) => old($f, $editing?->$f);
    @endphp

    <div class="p-6 space-y-6 max-w-4xl">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Project Category</h1>
            <p class="text-gray-500">Master data kategori project (kode dipakai di Project ID). Kelola Add / Edit / Archive.</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif

        {{-- List --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Leader Grade</th>
                        <th class="px-4 py-3">Sponsor Grade</th>
                        <th class="px-4 py-3">Max Team</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($categories as $cat)
                        <tr class="hover:bg-gray-50 {{ $cat->is_active ? '' : 'opacity-50' }}">
                            <td class="px-4 py-3 font-mono font-semibold text-red-700">{{ $cat->code }}</td>
                            <td class="px-4 py-3">{{ $cat->name }}</td>
                            <td class="px-4 py-3">{{ $cat->leader_grade_min ?? '-' }} &ndash; {{ $cat->leader_grade_max ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $cat->sponsor_grade_min ?? '-' }} &ndash; {{ $cat->sponsor_grade_max ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $cat->max_team_members ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs rounded-full px-2 py-1 {{ $cat->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">{{ $cat->is_active ? 'Active' : 'Archived' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.project-categories.index', ['edit' => $cat->id]) }}" class="px-3 py-1 text-xs border rounded-lg hover:bg-gray-100">Edit</a>
                                    <form method="POST" action="{{ route('admin.project-categories.toggle', $cat) }}">
                                        @csrf
                                        <button class="px-3 py-1 text-xs border {{ $cat->is_active ? 'border-red-300 text-red-600' : 'border-green-300 text-green-600' }} rounded-lg">{{ $cat->is_active ? 'Archive' : 'Restore' }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Belum ada category.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Add / Edit form --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold mb-4">{{ $editing ? 'Edit Category: '.$editing->code : 'Add Category' }}</h3>

            <form method="POST" action="{{ $editing ? route('admin.project-categories.update', $editing) : route('admin.project-categories.store') }}" class="space-y-4">
                @csrf
                @if($editing) @method('PUT') @endif

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Name <span class="text-red-600">*</span></label>
                        <input name="name" value="{{ $val('name') }}" required class="{{ $inp }} @error('name') border-red-500 @enderror">
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Code <span class="text-red-600">*</span> <span class="text-gray-400 text-xs">(dipakai di Project ID)</span></label>
                        <input name="code" value="{{ $val('code') }}" required class="{{ $inp }} uppercase @error('code') border-red-500 @enderror">
                        @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Description</label>
                    <textarea name="description" rows="2" class="{{ $inp }}">{{ $val('description') }}</textarea>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Leader Grade Min</label><input type="number" name="leader_grade_min" value="{{ $val('leader_grade_min') }}" class="{{ $inp }}"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Leader Grade Max</label><input type="number" name="leader_grade_max" value="{{ $val('leader_grade_max') }}" class="{{ $inp }}"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Max Team Members</label><input type="number" name="max_team_members" value="{{ $val('max_team_members') }}" class="{{ $inp }}"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Sponsor Grade Min</label><input type="number" name="sponsor_grade_min" value="{{ $val('sponsor_grade_min') }}" class="{{ $inp }}"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Sponsor Grade Max</label><input type="number" name="sponsor_grade_max" value="{{ $val('sponsor_grade_max') }}" class="{{ $inp }}"></div>
                </div>

                <div class="flex justify-end gap-3">
                    @if($editing)<a href="{{ route('admin.project-categories.index') }}" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</a>@endif
                    <button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">{{ $editing ? 'Update' : 'Add Category' }}</button>
                </div>
            </form>
            <p class="text-xs text-gray-400 mt-3">Catatan: grade range bersifat informatif — penyaringan eligibility Leader/Sponsor berdasarkan grade menyusul (butuh field grade di user).</p>
        </div>

    </div>

</x-app-layout>
