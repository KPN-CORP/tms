@php
    $isEdit  = isset($role);
    $vName   = old('name', $isEdit ? $role->name : '');
    // Restrict Group Company: opsi dari hcis (master_bisnisunits), value = NAMA.
    // Terpilih dicocokkan dengan nama BU lokal yang tersimpan di pivot role_business_units.
    $selBU   = collect(old('business_units', $isEdit ? $role->businessUnits->pluck('name')->all() : []));
    // Restrict Company: cascade dari Restrict Group Company. Value = contribution_level (NAMA),
    // dicocokkan dengan nama Company lokal yang tersimpan di pivot role_companies.
    $selCo   = collect(old('companies',      $isEdit ? $role->companies->pluck('name')->all() : []));
    // Restrict Location: cascade dari Restrict Group Company (BU). Value = area (NAMA),
    // dicocokkan dengan nama Location lokal yang tersimpan di pivot role_locations.
    $selLoc  = collect(old('locations',      $isEdit ? $role->locations->pluck('name')->all() : []));
    $selPerm = collect(old('permissions',    $isEdit ? $role->permissions->pluck('id')->all() : []));
    // Restrict Employee: opsi terpilih dari $assignedEmployees (dibaca via role_employees mysql),
    // sisanya dicari via AJAX (org.users) — tidak memuat semua user hcis.
    $assignedEmployees = $assignedEmployees ?? collect();

    // Permissions dikelompokkan per kategori (dari prefix sebelum titik) ke tab yang rapih.
    // Prefix yang belum dipetakan otomatis masuk grup "Lainnya" (aman untuk permission baru).
    $permGroupMap = [
        'idea' => 'Idea',
        'proposal' => 'Proposal',
        'project' => 'Project', 'project-category' => 'Project', 'implementation' => 'Project',
        'completion' => 'Project', 'success-indicator' => 'Project',
        'committee' => 'Committee', 'backup-approver' => 'Committee', 'override' => 'Committee',
        'budget' => 'Budget',
        'guideline' => 'Guideline',
        'audit' => 'Monitoring', 'dashboard' => 'Monitoring', 'history' => 'Monitoring',
        'role' => 'Administration', 'user' => 'Administration', 'team' => 'Administration',
        'job-level' => 'Administration', 'sla' => 'Administration', 'reminder' => 'Administration',
        'visibility' => 'Administration',
    ];
    $groupOrder = ['Idea', 'Proposal', 'Project', 'Committee', 'Budget', 'Guideline', 'Monitoring', 'Administration', 'Lainnya'];

    $grouped = $permissions->groupBy(fn ($p) => $permGroupMap[explode('.', $p->name)[0]] ?? 'Lainnya');
    $permissionGroups = collect($groupOrder)
        ->filter(fn ($g) => $grouped->has($g))
        ->mapWithKeys(fn ($g) => [$g => $grouped[$g]]);
    foreach ($grouped as $g => $perms) {
        if (! $permissionGroups->has($g)) $permissionGroups[$g] = $perms;
    }
@endphp

<form method="POST" action="{{ $action }}" class="bg-white rounded-xl shadow p-8">

    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    {{-- Role Name + Submit --}}
    <div class="flex items-start justify-between gap-6">

        <div class="w-1/2">

            <label class="block font-bold mb-2">Role Name</label>

            <input
                type="text"
                name="name"
                value="{{ $vName }}"
                placeholder="Enter role name.."
                class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('name') border-red-500 @enderror">

            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror

        </div>

        <button type="submit"
                class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">
            {{ $submitLabel }}
        </button>

    </div>

    {{-- Restrict Group Company --}}
    <div class="mt-8">
        <label class="block font-bold mb-2">
            Restrict Group Company (Keeping blank means no restriction)
        </label>
        <select id="select-business-units" name="business_units[]" multiple
                placeholder="Type to search group company..." class="w-full border rounded-lg">
            @foreach($businessUnits as $name)
                <option value="{{ $name }}" @selected($selBU->contains($name))>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Restrict Company --}}
    <div class="mt-8">
        <label class="block font-bold mb-2">
            Restrict Company (Keeping blank means no restriction)
        </label>
        <select id="select-companies" name="companies[]" multiple
                data-remote-url="{{ route('org.companies') }}" data-remote-parent="#select-business-units"
                placeholder="Pilih group company dulu, lalu pilih company..." class="w-full border rounded-lg">
            {{-- Opsi terpilih (edit/old) di-render agar tetap tampil; opsi lain dimuat via AJAX sesuai BU. --}}
            @foreach($selCo as $name)
                <option value="{{ $name }}" selected>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Restrict Location --}}
    <div class="mt-8">
        <label class="block font-bold mb-2">
            Restrict Location (Keeping blank means no restriction)
        </label>
        <select id="select-locations" name="locations[]" multiple
                data-remote-url="{{ route('org.locations') }}" data-remote-parent="#select-business-units"
                placeholder="Pilih group company dulu, lalu pilih location..." class="w-full border rounded-lg">
            {{-- Opsi terpilih (edit/old) di-render agar tetap tampil; opsi lain dimuat via AJAX sesuai BU. --}}
            @foreach($selLoc as $name)
                <option value="{{ $name }}" selected>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Restrict Employee Name --}}
    <div class="mt-8">
        <label class="block font-bold mb-2">
            Restrict Employee Name (Keeping blank means no restriction)
        </label>
        <select id="select-employees" name="employees[]" multiple data-no-search
                data-remote-search="{{ route('org.users') }}" data-remote-value="id"
                placeholder="Ketik nama atau employee ID untuk mencari user..." class="w-full border rounded-lg">
            @foreach($assignedEmployees as $employee)
                <option value="{{ $employee->id }}" selected>{{ $employee->name }}{{ $employee->employee_id ? ' - '.$employee->employee_id : '' }}</option>
            @endforeach
        </select>
    </div>

    {{-- Permissions — tab per kategori + check all (Alpine) --}}
    <div class="mt-10"
         x-data="permissionTabs({
            groups: {{ Illuminate\Support\Js::from($permissionGroups->map(fn ($p) => $p->pluck('id')->values())) }},
            selected: {{ Illuminate\Support\Js::from($permissions->mapWithKeys(fn ($p) => [$p->id => $selPerm->contains($p->id)])) }},
            active: '{{ $permissionGroups->keys()->first() }}'
         })">

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h3 class="text-lg font-semibold">Permissions</h3>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-gray-500"><span x-text="totalChecked"></span> / {{ $permissions->count() }} dipilih</span>
                <label class="flex items-center gap-2 cursor-pointer select-none px-3 py-1.5 border rounded-lg hover:bg-gray-100">
                    <input type="checkbox" class="rounded border-gray-300"
                           :checked="allChecked" @change="toggleAll($event.target.checked)">
                    <span class="font-medium">Check All</span>
                </label>
            </div>
        </div>

        {{-- Layout: tab vertikal di kiri (scrollable) + panel isi di kanan --}}
        <div class="flex gap-6">

            {{-- Tab list vertikal — bisa di-scroll ke bawah bila kategori banyak --}}
            <div class="w-56 shrink-0 border-r pr-2 max-h-80 overflow-y-auto">
                <div class="flex flex-col gap-1">
                    @foreach($permissionGroups as $group => $perms)
                        <button type="button" @click="active = @js($group)"
                                class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-left font-medium transition-colors"
                                :class="active === @js($group)
                                    ? 'bg-red-50 text-red-700'
                                    : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'">
                            <span class="truncate">{{ $group }}</span>
                            <span class="shrink-0 text-xs rounded-full px-1.5 py-0.5"
                                  :class="groupCount(@js($group)) > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400'"
                                  x-text="groupCount(@js($group)) + '/' + {{ $perms->count() }}"></span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Panel isi kategori aktif --}}
            <div class="flex-1 min-w-0">
                @foreach($permissionGroups as $group => $perms)
                    <div x-show="active === @js($group)" x-cloak>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-semibold text-gray-700">{{ $group }}</span>
                            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                                <input type="checkbox" class="rounded border-gray-300"
                                       :checked="groupAllChecked(@js($group))"
                                       @change="toggleGroup(@js($group), $event.target.checked)">
                                Pilih semua di {{ $group }}
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach($perms as $permission)
                                <label class="flex items-center gap-3">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                           class="rounded border-gray-300" x-model="selected[{{ $permission->id }}]">
                                    <span>{{ $permission->displayLabel() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>

</form>

{{-- Tom Select diterapkan global via layouts/app.blade.php (searchable multi-select). --}}

<script>
    // Komponen tab Permissions + check all. `selected` (map id→bool) reaktif via x-model,
    // jadi hitungan, "Check All", dan "Pilih semua per grup" otomatis sinkron dua arah.
    function permissionTabs(config) {
        return {
            active: config.active,
            groups: config.groups,     // { "Grup": [permId, ...] }
            selected: config.selected, // { permId: bool }
            get totalChecked() {
                return Object.values(this.selected).filter(Boolean).length;
            },
            get allChecked() {
                var ids = Object.keys(this.selected), self = this;
                return ids.length > 0 && ids.every(function (id) { return self.selected[id]; });
            },
            groupCount: function (g) {
                var self = this;
                return (this.groups[g] || []).filter(function (id) { return self.selected[id]; }).length;
            },
            groupAllChecked: function (g) {
                var ids = this.groups[g] || [], self = this;
                return ids.length > 0 && ids.every(function (id) { return self.selected[id]; });
            },
            toggleAll: function (val) {
                var self = this;
                Object.keys(this.selected).forEach(function (id) { self.selected[id] = val; });
            },
            toggleGroup: function (g, val) {
                var self = this;
                (this.groups[g] || []).forEach(function (id) { self.selected[id] = val; });
            },
        };
    }
</script>
