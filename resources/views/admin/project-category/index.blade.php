<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Project Category</h2>
    </x-slot>

    @php
        $inp = 'w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200';
        $val = fn ($f) => old($f, $editing?->$f);

        // Baris Require Role awal (dari old input / data edit) — dipakai repeater & lebar form.
        if (old('roles') !== null) {
            $roleRows = collect(old('roles'))->map(fn ($r, $i) => ['role' => (string) $r, 'total' => (string) (old('totals')[$i] ?? '')])->values()->all();
        } else {
            $roleRows = collect($editing?->required_roles ?? [])->map(fn ($r) => ['role' => (string) ($r['role'] ?? ''), 'total' => (string) ($r['total'] ?? '')])->values()->all();
        }
        if (empty($roleRows)) $roleRows = [['role' => '', 'total' => '']];
    @endphp

    <div class="p-6 space-y-6 max-w-7xl mx-auto w-full"
         x-data="{ open: {{ ($editing || $errors->any()) ? 'true' : 'false' }}, tab: '{{ $editing && ! $editing->is_active ? 'inactive' : 'active' }}', rows: {{ Illuminate\Support\Js::from($roleRows) }} }">

        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Project Category</h1>
                <p class="text-gray-500">Project category master data (the code is used in the Project ID). Manage Add / Edit / Activate.</p>
            </div>
            <button type="button" @click="open = true"
                    class="inline-flex items-center gap-1 px-5 py-2 bg-red-700 text-white rounded-lg text-sm font-semibold hover:bg-red-800 shrink-0">+ Add Category</button>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">{{ session('error') }}</div>
        @endif

        {{-- List — dipisah TAB Active / Inactive --}}
        @php $byStatus = $categories->groupBy(fn ($c) => $c->is_active ? 'active' : 'inactive'); @endphp
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 pt-3">
                @foreach(['active' => 'Active', 'inactive' => 'Inactive'] as $key => $label)
                    <button type="button" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-red-700 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition">
                        {{ $label }}
                        <span :class="tab === '{{ $key }}' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'"
                              class="ml-1 inline-flex items-center justify-center min-w-5 px-1.5 py-0.5 text-xs rounded-full">{{ optional($byStatus->get($key))->count() ?? 0 }}</span>
                    </button>
                @endforeach
            </div>

            @foreach(['active' => 'Active', 'inactive' => 'Inactive'] as $key => $label)
                @php $rows = $byStatus->get($key) ?? collect(); @endphp
                <div x-show="tab === '{{ $key }}'" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                <tr>
                                    <th class="px-4 py-3">Code</th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Leader Grade</th>
                                    <th class="px-4 py-3">Sponsor Grade</th>
                                    <th class="px-4 py-3">Max Team</th>
                                    <th class="px-4 py-3">Require Role</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse($rows as $cat)
                                    @php $muted = $cat->is_active ? '' : 'opacity-50'; @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-mono font-semibold text-red-700 {{ $muted }}">{{ $cat->code }}</td>
                                        <td class="px-4 py-3 {{ $muted }}">{{ $cat->name }}</td>
                                        <td class="px-4 py-3 {{ $muted }}">{{ $cat->leader_grade_min ?? '-' }} &ndash; {{ $cat->leader_grade_max ?? '-' }}</td>
                                        <td class="px-4 py-3 {{ $muted }}">{{ $cat->sponsor_grade_min ?? '-' }} &ndash; {{ $cat->sponsor_grade_max ?? '-' }}</td>
                                        <td class="px-4 py-3 {{ $muted }}">{{ $cat->max_team_members ?? '-' }}</td>
                                        <td class="px-4 py-3 {{ $muted }}">{{ collect($cat->requiredRoles())->map(fn ($r) => $r['role'].' ('.$r['total'].')')->implode(', ') ?: '-' }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('admin.project-categories.index', ['edit' => $cat->id]) }}" class="px-3 py-1 text-xs border rounded-lg hover:bg-gray-100">Edit</a>
                                                <form method="POST" action="{{ route('admin.project-categories.toggle', $cat) }}">
                                                    @csrf
                                                    <button class="px-3 py-1 text-xs border {{ $cat->is_active ? 'border-red-300 text-red-600' : 'border-green-300 text-green-600' }} rounded-lg">{{ $cat->is_active ? 'Deactivate' : 'Activate' }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No {{ strtolower($label) }} categories.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Dialog: Add / Edit Category (popup) --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape.window="open = false">
            <div class="fixed inset-0 bg-black/40" @click="open = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 p-6 max-h-[85vh] overflow-y-auto" x-transition.opacity>
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
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Code <span class="text-red-600">*</span> <span class="text-gray-400 text-xs">(used in Project ID)</span></label>
                        <input name="code" value="{{ $val('code') }}" required class="{{ $inp }} uppercase @error('code') border-red-500 @enderror">
                        @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Leader Grade Min</label><input type="number" name="leader_grade_min" value="{{ $val('leader_grade_min') }}" class="{{ $inp }} @error('leader_grade_min') border-red-500 @enderror"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Leader Grade Max</label><input type="number" name="leader_grade_max" value="{{ $val('leader_grade_max') }}" class="{{ $inp }} @error('leader_grade_max') border-red-500 @enderror"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Max Person in Team <span class="text-red-600">*</span></label><input type="number" name="max_team_members" value="{{ $val('max_team_members') }}" required class="{{ $inp }} @error('max_team_members') border-red-500 @enderror">@error('max_team_members')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Sponsor Grade Min</label><input type="number" name="sponsor_grade_min" value="{{ $val('sponsor_grade_min') }}" class="{{ $inp }} @error('sponsor_grade_min') border-red-500 @enderror"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Sponsor Grade Max</label><input type="number" name="sponsor_grade_max" value="{{ $val('sponsor_grade_max') }}" class="{{ $inp }} @error('sponsor_grade_max') border-red-500 @enderror"></div>
                </div>

                {{-- Require roles (opsional, MULTI) — memprapopulasi "Role in Project" di Team Members --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Require Role <span class="text-gray-400 font-normal">(optional, you can add more than one)</span></label>
                    <div class="space-y-2">
                        @php $inpRow = 'border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200'; @endphp
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="flex items-center gap-2">
                                <input name="roles[]" x-model="row.role" placeholder="Role name (e.g. Co-Leader)" class="{{ $inpRow }} flex-1 min-w-0">
                                <input name="totals[]" x-model="row.total" placeholder="Qty" class="{{ $inpRow }} w-28 shrink-0">
                                {{-- Baris terakhir: tombol tetap ada tapi mengosongkan isi (bukan menghapus baris). --}}
                                <button type="button"
                                        @click="if (rows.length > 1) { rows.splice(i, 1) } else { rows[i].role = ''; rows[i].total = '' }"
                                        class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg border border-red-300 text-red-600 hover:bg-red-50"
                                        :title="rows.length > 1 ? 'Remove role' : 'Clear'">&times;</button>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="rows.push({ role: '', total: '' })"
                            class="mt-2 inline-flex items-center gap-1 px-3 py-1.5 text-sm border border-red-300 text-red-700 rounded-lg hover:bg-red-50">
                        <span class="text-base leading-none">+</span> Add Role
                    </button>
                    @error('roles.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @error('totals.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3">
                    @if($editing)
                        <a href="{{ route('admin.project-categories.index') }}" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</a>
                    @else
                        <button type="button" @click="open = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                    @endif
                    <button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">{{ $editing ? 'Update' : 'Add Category' }}</button>
                </div>
            </form>
            </div>
        </div>

    </div>

</x-app-layout>
