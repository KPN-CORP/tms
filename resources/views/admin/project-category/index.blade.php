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
        @if(session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">{{ session('error') }}</div>
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
                        <th class="px-4 py-3">Require Role</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($categories as $cat)
                        @php $muted = $cat->is_active ? '' : 'opacity-50'; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono font-semibold text-red-700 {{ $muted }}">{{ $cat->code }}</td>
                            <td class="px-4 py-3 {{ $muted }}">{{ $cat->name }}</td>
                            <td class="px-4 py-3 {{ $muted }}">{{ $cat->leader_grade_min ?? '-' }} &ndash; {{ $cat->leader_grade_max ?? '-' }}</td>
                            <td class="px-4 py-3 {{ $muted }}">{{ $cat->sponsor_grade_min ?? '-' }} &ndash; {{ $cat->sponsor_grade_max ?? '-' }}</td>
                            <td class="px-4 py-3 {{ $muted }}">{{ $cat->max_team_members ?? '-' }}</td>
                            <td class="px-4 py-3 {{ $muted }}">{{ collect($cat->requiredRoles())->map(fn ($r) => $r['role'].' ('.$r['total'].')')->implode(', ') ?: '-' }}</td>
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
                                    <form method="POST" action="{{ route('admin.project-categories.destroy', $cat) }}">
                                        @csrf @method('DELETE')
                                        <button data-confirm="Hapus category {{ $cat->code }}? Tindakan ini permanen." data-confirm-title="Hapus Category" data-confirm-ok="Ya, Hapus"
                                                class="px-3 py-1 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Belum ada category.</td></tr>
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

                <div class="grid grid-cols-3 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Leader Grade Min <span class="text-red-600">*</span></label><input type="number" name="leader_grade_min" value="{{ $val('leader_grade_min') }}" required class="{{ $inp }} @error('leader_grade_min') border-red-500 @enderror"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Leader Grade Max <span class="text-red-600">*</span></label><input type="number" name="leader_grade_max" value="{{ $val('leader_grade_max') }}" required class="{{ $inp }} @error('leader_grade_max') border-red-500 @enderror"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Max Team Members <span class="text-red-600">*</span></label><input type="number" name="max_team_members" value="{{ $val('max_team_members') }}" required class="{{ $inp }} @error('max_team_members') border-red-500 @enderror"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Sponsor Grade Min <span class="text-red-600">*</span></label><input type="number" name="sponsor_grade_min" value="{{ $val('sponsor_grade_min') }}" required class="{{ $inp }} @error('sponsor_grade_min') border-red-500 @enderror"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Sponsor Grade Max <span class="text-red-600">*</span></label><input type="number" name="sponsor_grade_max" value="{{ $val('sponsor_grade_max') }}" required class="{{ $inp }} @error('sponsor_grade_max') border-red-500 @enderror"></div>
                </div>

                {{-- Require roles (opsional, MULTI) — memprapopulasi "Role in Project" di Team Members --}}
                @php
                    if (old('roles') !== null) {
                        $roleRows = collect(old('roles'))->map(fn ($r, $i) => ['role' => (string) $r, 'total' => (string) (old('totals')[$i] ?? '')])->values()->all();
                    } else {
                        $roleRows = collect($editing?->required_roles ?? [])->map(fn ($r) => ['role' => (string) ($r['role'] ?? ''), 'total' => (string) ($r['total'] ?? '')])->values()->all();
                    }
                    if (empty($roleRows)) $roleRows = [['role' => '', 'total' => '']];
                @endphp
                <div x-data="{ rows: {{ Illuminate\Support\Js::from($roleRows) }} }">
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Require Role <span class="text-gray-400 font-normal">(optional, bisa lebih dari satu)</span></label>
                    <p class="text-xs text-gray-400 mb-2">Mis. <b>Satpam</b> × <b>2</b> → di Team Members muncul 2 baris "Satpam", tinggal pilih nama. Tambahkan baris untuk role lain.</p>

                    <div class="space-y-2">
                        @php $inpRow = 'border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200'; @endphp
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="flex items-center gap-2">
                                <input name="roles[]" x-model="row.role" placeholder="Nama role (mis. Co - Leader)" class="{{ $inpRow }} flex-1 min-w-0">
                                <input name="totals[]" x-model="row.total" placeholder="Jumlah" class="{{ $inpRow }} w-28 shrink-0">
                                <button type="button" @click="rows.splice(i, 1)"
                                        class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg border border-red-300 text-red-600 hover:bg-red-50"
                                        title="Hapus role" x-show="rows.length > 1">&times;</button>
                                <span class="shrink-0 w-9 h-9" x-show="rows.length <= 1"></span>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="rows.push({ role: '', total: '' })"
                            class="mt-2 inline-flex items-center gap-1 px-3 py-1.5 text-sm border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <span class="text-base leading-none">+</span> Tambah Role
                    </button>
                    @error('roles.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @error('totals.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3">
                    @if($editing)<a href="{{ route('admin.project-categories.index') }}" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</a>@endif
                    <button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">{{ $editing ? 'Update' : 'Add Category' }}</button>
                </div>
            </form>
            <!--<p class="text-xs text-gray-400 mt-3">Catatan: grade range bersifat informatif — penyaringan eligibility Leader/Sponsor berdasarkan grade menyusul (butuh field grade di user).</p>-->
        </div>

    </div>

</x-app-layout>
