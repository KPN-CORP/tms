@props([
    'businessUnits' => null,   // Collection → render dropdown Business Unit
    'departments'   => null,   // Collection → render dropdown Department (cascade BU)
    'statuses'      => null,   // array [value => label] → render dropdown Status
    'resetRoute'    => null,   // URL untuk tombol Reset
    'placeholder'   => 'Cari…',
])

@php
    $fieldCls  = 'w-full h-[38px] appearance-none border border-gray-300 rounded-lg px-3 text-sm bg-white';
    $hasActive = request('q') || request('business_unit_id') || request('department_id') || request('status');
    $spacer    = 'block text-xs font-semibold text-transparent mb-1 select-none';
@endphp

<div class="flex flex-wrap items-start gap-3 p-4 border-b border-gray-100">

    {{-- Pertahankan sort saat filter/search di-submit --}}
    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
    @if(request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif

    <div class="w-60">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Search</label>
        <input name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}"
               x-data x-on:input.debounce.500ms="$el.form.requestSubmit()"
               class="w-full h-[38px] border border-gray-300 rounded-lg px-3 text-sm focus:ring focus:ring-red-200">
    </div>

    @if($businessUnits !== null)
        <div class="w-60">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Business Unit</label>
            <select name="business_unit_id" id="filter-bu" onchange="this.form.requestSubmit()" class="{{ $fieldCls }}">
                <option value="">Business Unit</option>
                @foreach($businessUnits as $bu)
                    <option value="{{ $bu->id }}" @selected(request('business_unit_id') == $bu->id)>{{ $bu->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if($departments !== null)
        <div class="w-60">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Department</label>
            <select name="department_id" id="filter-dept" data-cascade-parent="#filter-bu" onchange="this.form.requestSubmit()" class="{{ $fieldCls }}">
                <option value="">Department</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" data-bu="{{ $dept->business_unit_id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if($statuses !== null)
        <div class="w-60">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
            <select name="status" onchange="this.form.requestSubmit()" class="{{ $fieldCls }}">
                <option value="">Status</option>
                @foreach($statuses as $val => $label)
                    <option value="{{ $val }}" @selected((string) request('status') === (string) $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label class="{{ $spacer }}">.</label>
        <button class="h-[38px] px-5 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Apply</button>
    </div>

    @if($hasActive && $resetRoute)
        <div>
            <label class="{{ $spacer }}">.</label>
            <a href="{{ $resetRoute }}" class="inline-flex items-center h-[38px] px-4 border rounded-lg text-sm hover:bg-gray-100">Reset</a>
        </div>
    @endif
</div>
