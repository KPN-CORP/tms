<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Committee Assignment</h2>
    </x-slot>

    <div class="p-6 space-y-6 max-w-7xl mx-auto w-full">

        <div>
            <a href="{{ route('admin.committee.index') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to list committee </a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Add / Edit Committee</h1>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
        @endif

        {{-- Selector: approval type + Business Unit + Unit (+ Budget for project_proposal) --}}
        <form method="GET" action="{{ route('admin.committee.form') }}" class="bg-white rounded-xl shadow p-6 flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block font-semibold mb-2">Approval Type</label>
                <select name="approval_type" onchange="this.form.submit()"
                        class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                    <option value="" @selected(! $type)></option>
                    @foreach($types as $val => $label)
                        <option value="{{ $val }}" @selected($type === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block font-semibold mb-2">Business Unit</label>
                <select name="business_unit_id" data-remote-options="{{ route('org.business-units-local') }}" data-selected="{{ $selectedBuId }}"
                        onchange="this.form.submit()"
                        class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                    <option value=""></option>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block font-semibold mb-2">Unit</label>
                <select name="department" onchange="this.form.submit()"
                        class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                    <option value=""></option>
                    @foreach($unitNames as $dept)
                        <option value="{{ $dept }}" @selected($selectedUnitName === $dept)>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Budget: range Min–Max (project_proposal). Projects whose total budget falls in [min, max] go to this committee. --}}
            @if($usesRange)
                <div class="flex-1 min-w-[240px]">
                    <label class="block font-semibold mb-2">Budget</label>
                    <div class="flex items-center gap-2">
                        <input type="text" inputmode="numeric" name="budget_min"
                               value="{{ $selectedMin !== null ? number_format($selectedMin, 0, ',', '.') : '' }}"
                               onchange="this.form.submit()" placeholder="Min"
                               class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                        <span class="text-gray-400">–</span>
                        <input type="text" inputmode="numeric" name="budget_max"
                               value="{{ $selectedMax !== null ? number_format($selectedMax, 0, ',', '.') : '' }}"
                               onchange="this.form.submit()" placeholder="Max"
                               class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                    </div>
                </div>
            @endif
        </form>

        {{-- Layer form: show 3 first, rest via "Add Layer" (max 10). Filled layers stay visible even beyond 3. --}}
        @php
            $maxAssigned    = collect($assignedByLayer)->filter()->keys()->max() ?? 0;
            $initialVisible = max(3, (int) $maxAssigned);
        @endphp
        @if($type && $selectedBuId && $rangeReady)
        <div x-data="{ visible: {{ $initialVisible }}, importOpen: false, importBusy: false, importError: '', importFilled: [], importNotes: [] }">
        <form method="POST" action="{{ route('admin.committee.store') }}" class="bg-white rounded-xl shadow p-6 space-y-4">
            @csrf
            <input type="hidden" name="approval_type" value="{{ $type }}">
            <input type="hidden" name="business_unit_id" value="{{ $selectedBuId }}">
            <input type="hidden" name="department" value="{{ $selectedUnitName }}">
            @if($usesRange)
                <input type="hidden" name="budget_min" value="{{ $selectedMin }}">
                <input type="hidden" name="budget_max" value="{{ $selectedMax }}">
            @endif
            <p class="text-sm text-gray-500">Approval: <span class="font-semibold">{{ $types[$type] }}</span> — BU: <span class="font-semibold">{{ optional($businessUnits->firstWhere('id', $selectedBuId))->name }}</span> — Unit: <span class="font-semibold">{{ $selectedUnitName ?: 'All Units (BU-wide)' }}</span>@if($usesRange) — Budget: <span class="font-semibold">{{ \App\Models\CommitteeAssignment::budgetRangeLabel($selectedMin, $selectedMax) }}</span>@endif</p>

            <h3 class="text-lg font-semibold">Reviewer per Layer</h3>
            @php $isProposal = \App\Models\CommitteeAssignment::usesSponsorLayer($type); @endphp
            @if($isProposal)
                <p class="text-xs text-gray-500 -mt-2">Layer 1 is automatically the project's <b>Project Sponsor</b>. Configure the committee from Layer 2.</p>
            @endif

            {{-- Hasil import: apa yang terisi, dan baris mana yang dilewati. Ditampilkan
                 sebelum Save supaya salah isi file ketahuan sebelum tersimpan. --}}
            <div x-show="importFilled.length || importNotes.length" x-cloak class="space-y-2">
                <div x-show="importFilled.length" class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    <div class="font-semibold mb-1">Imported from Excel — press <b>Save</b> to apply.</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        <template x-for="t in importFilled"><li x-text="t"></li></template>
                    </ul>
                </div>
                <div x-show="importNotes.length" class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    <div class="font-semibold mb-1">Skipped rows</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        <template x-for="t in importNotes"><li x-text="t"></li></template>
                    </ul>
                </div>
            </div>

            @for($layer = 1; $layer <= $maxLayers; $layer++)
                <div class="flex items-center gap-4" @if($layer > 3) x-show="{{ $layer }} <= visible" x-cloak @endif>
                    <span class="w-20 text-sm font-semibold text-gray-600">Layer {{ $layer }}</span>
                    @if($isProposal && $layer === 1)
                        {{-- Layer 1 project_proposal = Project Sponsor (fixed, not editable/searchable) --}}
                        <input type="text" value="Project Sponsor" disabled aria-readonly="true"
                               class="flex-1 border rounded-lg px-4 py-2 bg-gray-100 text-gray-500 cursor-not-allowed select-none">
                    @else
                        @php $a = $assignedByLayer[$layer] ?? null; @endphp
                        <select id="layer-select-{{ $layer }}" name="layers[{{ $layer }}]" data-no-search data-remote-search="{{ $employeeSearchUrl }}"
                                class="flex-1 border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                            <option value="">— none —</option>
                            @if($a)<option value="{{ $a['email'] }}" selected>{{ $a['label'] }}</option>@endif
                        </select>
                    @endif
                </div>
            @endfor

            {{-- Add next layer field (max 10). Hidden once at 10. --}}
            <div x-show="visible < {{ $maxLayers }}">
                <button type="button" @click="visible = Math.min({{ $maxLayers }}, visible + 1)"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-red-700 text-red-700 rounded-lg text-sm font-semibold hover:bg-red-50">
                    + Add Layer
                </button>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="importOpen = true"
                        class="inline-flex items-center gap-2 px-6 py-2 border border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-100">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
                    </svg>
                    Import Excel
                </button>
                <a href="{{ route('admin.committee.index') }}" class="px-6 py-2 border rounded-lg font-semibold text-gray-700 hover:bg-gray-100">Cancel</a>
                <button type="submit"
                        class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save</button>
            </div>
        </form>

        {{-- Dialog import. Di LUAR <form> di atas: <form> bersarang tidak valid dan
             membuat tombol Save ikut terpicu. --}}
        <div x-show="importOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center"
             @keydown.escape.window="importOpen = false">
            <div class="fixed inset-0 bg-black/40" @click="importOpen = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6" x-transition.opacity>
                <h3 class="text-lg font-semibold text-gray-800">Import Excel</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Columns <b>employee_id</b>, <b>approver_id</b>, and <b>layer</b>.
                    Each row fills one Layer field below with the employee whose ID is in <b>approver_id</b>.
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    Business Unit and Unit come from the form above, so the <b>employee_id</b> column is not used.
                    Nothing is stored until you press <b>Save</b>.
                </p>

                <a href="{{ route('admin.committee.template') }}"
                   class="inline-flex items-center gap-1 text-sm text-red-700 hover:underline mt-3">
                    Download template (.xlsx)
                </a>

                <input type="file" x-ref="importFile" accept=".xlsx,.csv"
                       data-approval-type="{{ $type }}" data-url="{{ route('admin.committee.import') }}"
                       @change="importError = ''"
                       class="mt-4 block w-full text-sm text-gray-700 border rounded-lg px-3 py-2
                              file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0
                              file:bg-gray-100 file:text-gray-700 file:font-semibold hover:file:bg-gray-200">

                <p x-show="importError" x-cloak x-text="importError"
                   class="mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700"></p>

                <div class="flex justify-end gap-3 pt-5">
                    <button type="button" @click="importOpen = false"
                            class="px-6 py-2 border rounded-lg font-semibold text-gray-700 hover:bg-gray-100">Cancel</button>
                    <button type="button" :disabled="importBusy"
                            @click="window.tmsCommitteeImport($refs.importFile, $data)"
                            class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800 disabled:opacity-60">
                        <span x-text="importBusy ? 'Reading...' : 'Import'"></span>
                    </button>
                </div>
            </div>
        </div>
        </div>
        @else
            <div class="bg-white rounded-xl shadow p-6 text-sm text-gray-400">
                Select <span class="font-semibold text-gray-600">Approval Type</span>, <span class="font-semibold text-gray-600">Business Unit</span>@if($usesRange), and enter <span class="font-semibold text-gray-600">Budget Min &amp; Max</span> (Max ≥ Min)@endif first to configure the committee per layer.
            </div>
        @endif

    </div>

    {{-- Import Excel: kirim file, lalu ISI dropdown layer dari jawabannya.
         Tidak menyimpan apa pun — tombol Save tetap yang menentukan. --}}
    <script>
        window.tmsCommitteeImport = function (input, state) {
            state.importError = '';

            var file = input.files && input.files[0];
            if (! file) { state.importError = 'Choose a file first.'; return; }

            var body = new FormData();
            var meta = document.querySelector('meta[name="csrf-token"]');
            body.append('_token', meta ? meta.content : '');
            body.append('approval_type', input.dataset.approvalType);
            body.append('file', file);

            state.importBusy = true;

            fetch(input.dataset.url, {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, data: j }; }); })
            .then(function (res) {
                state.importBusy = false;

                if (! res.ok) {
                    // Pesan validasi Laravel datang sebagai {errors: {field: [pesan]}}.
                    var err = res.data.errors ? Object.values(res.data.errors)[0][0] : null;
                    state.importError = res.data.message || err || 'The file could not be read.';
                    return;
                }

                var terisi = [];
                (res.data.rows || []).forEach(function (row) {
                    var el = document.getElementById('layer-select-' + row.layer);
                    if (! el) { return; }

                    var ts = el.tomselect;
                    if (ts) {
                        // Opsi belum ada di dropdown (hasil AJAX), jadi ditambahkan dulu.
                        ts.addOption({ value: row.email, text: row.label });
                        ts.setValue(row.email, false);
                    } else {
                        var opt = document.createElement('option');
                        opt.value = row.email;
                        opt.textContent = row.label;
                        opt.selected = true;
                        el.appendChild(opt);
                    }

                    // Layer di atas 3 tersembunyi sampai "Add Layer" ditekan — buka sendiri
                    // supaya hasil import tidak tampak hilang.
                    if (row.layer > state.visible) { state.visible = row.layer; }

                    terisi.push('Layer ' + row.layer + ' — ' + row.label);
                });

                state.importFilled = terisi;
                state.importNotes  = res.data.notes || [];
                state.importOpen   = false;
                input.value = '';
            })
            .catch(function () {
                state.importBusy = false;
                state.importError = 'The file could not be sent. Please try again.';
            });
        };
    </script>

</x-app-layout>
