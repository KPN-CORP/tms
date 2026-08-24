<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Committee Assignment</h2>
    </x-slot>

    <div class="p-6 space-y-6 max-w-7xl mx-auto w-full">

        <div>
            <a href="{{ route('admin.committee.index') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to list committe </a>
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
                <select name="business_unit_id" onchange="this.form.submit()"
                        class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">
                    <option value="" @selected(! $selectedBuId)></option>
                    @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}" @selected($selectedBuId == $bu->id)>{{ $bu->name }}</option>
                    @endforeach
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
        <form method="POST" action="{{ route('admin.committee.store') }}" class="bg-white rounded-xl shadow p-6 space-y-4"
              x-data="{ visible: {{ $initialVisible }} }">
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
            @php $isProposal = $type === 'project_proposal'; @endphp
            @if($isProposal)
                <p class="text-xs text-gray-500 -mt-2">Layer 1 is automatically the project's <b>Project Sponsor</b>. Configure the committee from Layer 2.</p>
            @endif

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
                <a href="{{ route('admin.committee.index') }}" class="px-6 py-2 border rounded-lg font-semibold text-gray-700 hover:bg-gray-100">Cancel</a>
                <button type="submit"
                        class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save</button>
            </div>
        </form>
        @else
            <div class="bg-white rounded-xl shadow p-6 text-sm text-gray-400">
                Select <span class="font-semibold text-gray-600">Approval Type</span>, <span class="font-semibold text-gray-600">Business Unit</span>@if($usesRange), and enter <span class="font-semibold text-gray-600">Budget Min &amp; Max</span> (Max ≥ Min)@endif first to configure the committee per layer.
            </div>
        @endif

    </div>

</x-app-layout>
