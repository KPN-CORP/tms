@props([
    'businessUnits' => null,   // Collection BU  → render dropdown Business Unit
    'departments'   => null,   // Collection Dept → render dropdown Department (cascade BU)
    'statuses'      => null,   // array [value => label] → render dropdown Status
])

@php $sel = 'border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200'; @endphp

@if($businessUnits !== null)
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Business Unit</label>
        <select name="business_unit_id" id="filter-bu" data-remote-options="{{ route('org.business-units-local') }}" data-selected="{{ request('business_unit_id') }}" onchange="this.form.requestSubmit()" class="{{ $sel }}">
            <option value="">All BU</option>
        </select>
    </div>
@endif

@if($departments !== null)
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Unit</label>
        <select name="department_id" id="filter-dept"
                @if($businessUnits !== null) data-cascade-parent="#filter-bu" @endif
                onchange="this.form.requestSubmit()" class="{{ $sel }}">
            <option value="">All Units</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" data-bu="{{ $dept->business_unit_id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>
            @endforeach
        </select>
    </div>
@endif

@if($statuses !== null)
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
        <select name="status" onchange="this.form.requestSubmit()" class="{{ $sel }}">
            <option value="">All statuses</option>
            @foreach($statuses as $val => $label)
                <option value="{{ $val }}" @selected((string) request('status') === (string) $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
@endif

{{-- Cascade Business Unit → Department ditangani global (data-cascade-parent) di app layout. --}}
