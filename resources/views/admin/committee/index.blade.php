<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Committee Assignment</h2>
    </x-slot>

    <div class="p-6 space-y-6 max-w-5xl">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Committee Assignment</h1>
            <p class="text-gray-500">
                Tentukan committee reviewer per Business Unit. Urutan Layer 1 &rarr; 10 = urutan approval (waterfall).
                Kosongkan layer yang tidak dipakai.
            </p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        {{-- Daftar committee yang sudah diatur --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold flex items-center justify-between">
                <span>Committee yang Sudah Diatur</span>
                <span class="text-xs font-normal bg-white/20 rounded-full px-3 py-1">{{ $configured->count() }} set</span>
            </div>
            @if($configured->isEmpty())
                <div class="p-6 text-gray-400 text-sm">Belum ada committee yang diatur. Gunakan form di bawah untuk menambahkan.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                            <tr>
                                <th class="px-4 py-3">Approval Type</th>
                                <th class="px-4 py-3">Business Unit</th>
                                <th class="px-4 py-3">Unit</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($configured as $set)
                                <tr class="align-top hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <span class="inline-block bg-red-50 text-red-700 rounded-full px-3 py-1 text-xs font-semibold">{{ $set['type_label'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-700">{{ $set['bu_name'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $set['dept_name'] }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.committee.index', array_filter(['approval_type' => $set['approval_type'], 'business_unit_id' => $set['business_unit_id'], 'department' => $set['dept_name_raw']])) }}#editor"
                                               class="px-3 py-1.5 border border-red-700 text-red-700 rounded-lg text-xs font-semibold hover:bg-red-50">Edit</a>
                                            <form method="POST" action="{{ route('admin.committee.destroy') }}"
                                                  onsubmit="return confirm('Hapus committee {{ $set['type_label'] }} — {{ $set['bu_name'] }} / {{ $set['dept_name'] }}?')">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="approval_type" value="{{ $set['approval_type'] }}">
                                                <input type="hidden" name="business_unit_id" value="{{ $set['business_unit_id'] }}">
                                                <input type="hidden" name="department_id" value="{{ $set['department_id'] }}">
                                                <button class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <h2 id="editor" class="text-lg font-bold text-gray-800 pt-2">Tambah / Ubah Committee</h2>

        {{-- Pilih jenis approval + Business Unit (+ Unit/Department untuk idea) --}}
        <form method="GET" action="{{ route('admin.committee.index') }}" class="bg-white rounded-xl shadow p-6 grid grid-cols-3 gap-4">
            <div>
                <label class="block font-semibold mb-2">Approval Type</label>
                <select name="approval_type" onchange="this.form.submit()"
                        class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                    @foreach($types as $val => $label)
                        <option value="{{ $val }}" @selected($type === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-semibold mb-2">Business Unit</label>
                <select name="business_unit_id" onchange="this.form.submit()"
                        class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                    @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}" @selected($selectedBuId == $bu->id)>{{ $bu->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-semibold mb-2">Unit / Department</label>
                <select name="department" onchange="this.form.submit()"
                        class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                    <option value="">— Semua Department (BU-wide) —</option>
                    @foreach($unitNames as $dept)
                        <option value="{{ $dept }}" @selected($selectedUnitName === $dept)>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        {{-- 10 Layer --}}
        <form method="POST" action="{{ route('admin.committee.store') }}" class="bg-white rounded-xl shadow p-6 space-y-4">
            @csrf
            <input type="hidden" name="approval_type" value="{{ $type }}">
            <input type="hidden" name="business_unit_id" value="{{ $selectedBuId }}">
            <input type="hidden" name="department" value="{{ $selectedUnitName }}">
            <p class="text-sm text-gray-500">Approval: <span class="font-semibold">{{ $types[$type] }}</span> — BU: <span class="font-semibold">{{ optional($businessUnits->firstWhere('id', $selectedBuId))->name }}</span> — Unit: <span class="font-semibold">{{ $selectedUnitName ?: 'Semua Department (BU-wide)' }}</span></p>

            <h3 class="text-lg font-semibold">Reviewer per Layer</h3>

            @for($layer = 1; $layer <= $maxLayers; $layer++)
                <div class="flex items-center gap-4">
                    <span class="w-20 text-sm font-semibold text-gray-600">Layer {{ $layer }}</span>
                    @php $a = $assignedByLayer[$layer] ?? null; @endphp
                    <select name="layers[{{ $layer }}]" data-no-search data-remote-search="{{ $employeeSearchUrl }}"
                            class="flex-1 border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                        <option value="">— none —</option>
                        @if($a)<option value="{{ $a['email'] }}" selected>{{ $a['label'] }}</option>@endif
                    </select>
                </div>
            @endfor

            <div class="flex justify-end pt-2">
                <button type="submit"
                        class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save</button>
            </div>
        </form>

    </div>

</x-app-layout>
