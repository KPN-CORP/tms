@php
    $inp = 'w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200';
    $lbl = 'block text-sm font-semibold text-gray-600 mb-1';
    // $u = user yang sedang diedit (null saat create). $val resolusi old()/model.
    $u = $u ?? null;
    $val = fn ($f) => old($f, $u?->$f);
    $assignedIds = $assignedIds ?? [];
@endphp

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="{{ $lbl }}">Name <span class="text-red-600">*</span></label>
        <input name="name" value="{{ $val('name') }}" required class="{{ $inp }} @error('name') border-red-500 @enderror">
        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $lbl }}">Email <span class="text-red-600">*</span></label>
        <input type="email" name="email" value="{{ $val('email') }}" required class="{{ $inp }} @error('email') border-red-500 @enderror">
        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="{{ $lbl }}">Password @if(!$u)<span class="text-red-600">*</span>@else<span class="text-gray-400 text-xs">(kosongkan bila tidak diganti)</span>@endif</label>
        <input type="password" name="password" class="{{ $inp }} @error('password') border-red-500 @enderror" autocomplete="new-password">
        @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $lbl }}">Confirm Password</label>
        <input type="password" name="password_confirmation" class="{{ $inp }}" autocomplete="new-password">
    </div>
</div>

<div class="grid grid-cols-3 gap-4">
    <div>
        <label class="{{ $lbl }}">Employee ID</label>
        <input name="employee_id" value="{{ $val('employee_id') }}" class="{{ $inp }} @error('employee_id') border-red-500 @enderror">
        @error('employee_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $lbl }}">Job Level (grade)</label>
        <select name="job_level" class="{{ $inp }}">
            <option value="">—</option>
            @foreach($jobLevels as $level => $label)
                <option value="{{ $level }}" @selected((string) $val('job_level') === (string) $level)>{{ $level }} — {{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div></div>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="{{ $lbl }}">Business Unit</label>
        <select name="business_unit_id" id="bu-select" class="{{ $inp }}">
            <option value="">—</option>
            @foreach($businessUnits as $bu)
                <option value="{{ $bu->id }}" @selected((string) $val('business_unit_id') === (string) $bu->id)>{{ $bu->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="{{ $lbl }}">Unit</label>
        <select name="department_id" id="dept-select" data-cascade-parent="#bu-select" class="{{ $inp }}">
            <option value="">—</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" data-bu="{{ $dept->business_unit_id }}" @selected((string) $val('department_id') === (string) $dept->id)>{{ $dept->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<div>
    <label class="{{ $lbl }}">Summary / Notes</label>
    <textarea name="summary" rows="2" class="{{ $inp }}">{{ $val('summary') }}</textarea>
</div>

<div>
    <label class="{{ $lbl }}">Roles</label>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 border rounded-lg p-3">
        @foreach($roles as $role)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                       @checked(in_array($role->id, old('roles', $assignedIds)))
                       class="rounded border-gray-300 text-red-600 focus:ring-red-200">
                {{ $role->name }}
            </label>
        @endforeach
    </div>
    <p class="text-xs text-gray-400 mt-1">Izin efektif = gabungan (union) semua role yang dipilih.</p>
</div>

{{-- Cascade Business Unit → Department ditangani global (data-cascade-parent) di app layout. --}}
