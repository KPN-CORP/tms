@php
    $isEdit  = isset($role);
    $vName   = old('name', $isEdit ? $role->name : '');
    $selBU   = collect(old('business_units', $isEdit ? $role->businessUnits->pluck('id')->all() : []));
    $selCo   = collect(old('companies',      $isEdit ? $role->companies->pluck('id')->all() : []));
    $selLoc  = collect(old('locations',      $isEdit ? $role->locations->pluck('id')->all() : []));
    $selEmp  = collect(old('employees',      $isEdit ? $role->employees->pluck('id')->all() : []));
    $selPerm = collect(old('permissions',    $isEdit ? $role->permissions->pluck('id')->all() : []));
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
            @foreach($businessUnits as $item)
                <option value="{{ $item->id }}" @selected($selBU->contains($item->id))>{{ $item->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Restrict Company --}}
    <div class="mt-8">
        <label class="block font-bold mb-2">
            Restrict Company (Keeping blank means no restriction)
        </label>
        <select id="select-companies" name="companies[]" multiple
                placeholder="Type to search company..." class="w-full border rounded-lg">
            @foreach($companies as $item)
                <option value="{{ $item->id }}" @selected($selCo->contains($item->id))>{{ $item->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Restrict Location --}}
    <div class="mt-8">
        <label class="block font-bold mb-2">
            Restrict Location (Keeping blank means no restriction)
        </label>
        <select id="select-locations" name="locations[]" multiple
                placeholder="Type to search location..." class="w-full border rounded-lg">
            @foreach($locations as $item)
                <option value="{{ $item->id }}" @selected($selLoc->contains($item->id))>{{ $item->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Restrict Employee Name --}}
    <div class="mt-8">
        <label class="block font-bold mb-2">
            Restrict Employee Name (Keeping blank means no restriction)
        </label>
        <select id="select-employees" name="employees[]" multiple
                placeholder="Type to search employee name..." class="w-full border rounded-lg">
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" @selected($selEmp->contains($employee->id))>{{ $employee->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Permissions --}}
    <div class="mt-10">
        <h3 class="text-lg font-semibold mb-4">Permissions</h3>
        <div class="grid grid-cols-3 gap-4">
            @foreach($permissions as $permission)
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                           class="rounded border-gray-300" @checked($selPerm->contains($permission->id))>
                    <span>{{ $permission->name }}</span>
                </label>
            @endforeach
        </div>
    </div>

</form>

{{-- Tom Select diterapkan global via layouts/app.blade.php (searchable multi-select). --}}
