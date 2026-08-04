@php
    $u      = Auth::user();
    $isEdit = isset($idea);
    $v      = fn ($f) => old($f, $isEdit ? $idea->$f : '');
    $selBu  = old('business_unit_id', $isEdit ? $idea->business_unit_id : '');
    $selDep = old('department_id',    $isEdit ? $idea->department_id : '');
    $selCo  = old('company_id',       $isEdit ? $idea->company_id : '');
    $selLoc = old('location_id',      $isEdit ? $idea->location_id : '');
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    {{-- Employee Information (read-only, dari user login) --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="bg-red-800 text-white px-6 py-3 font-semibold">Employee Information</div>
        <div class="p-6 grid grid-cols-2 gap-4">
            <div class="border rounded-lg px-4 py-3">
                <div class="text-xs text-gray-400">EMPLOYEE ID</div>
                <div class="font-semibold">{{ $u->employee_id ?? '-' }}</div>
            </div>
            <div class="border rounded-lg px-4 py-3">
                <div class="text-xs text-gray-400">FULL NAME</div>
                <div class="font-semibold">{{ $u->name }}</div>
            </div>
            <div class="border rounded-lg px-4 py-3">
                <div class="text-xs text-gray-400">BUSINESS UNIT</div>
                <div class="font-semibold">{{ optional($u->businessUnit)->name ?? '-' }}</div>
            </div>
            <div class="border rounded-lg px-4 py-3">
                <div class="text-xs text-gray-400">DEPARTMENT</div>
                <div class="font-semibold">{{ optional($u->department)->name ?? '-' }}</div>
            </div>
        </div>
    </div>

    {{-- Idea Detail --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="bg-red-800 text-white px-6 py-3 font-semibold">Idea Detail</div>
        <div class="p-6 space-y-5">

            <div>
                <label class="block font-semibold mb-1">Idea Name <span class="text-red-600">*</span></label>
                <input type="text" name="idea_name" value="{{ $v('idea_name') }}"
                       placeholder="Enter a clear, descriptive name for your idea"
                       class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('idea_name') border-red-500 @enderror">
                @error('idea_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block font-semibold mb-1">Problem / Root Cause <span class="text-red-600">*</span></label>
                <textarea name="problem" rows="3" placeholder="Describe the problem or root cause that your idea addresses"
                          class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('problem') border-red-500 @enderror">{{ $v('problem') }}</textarea>
                @error('problem')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block font-semibold mb-1">Detailed Description <span class="text-red-600">*</span></label>
                <textarea name="description" rows="4" placeholder="Provide a detailed description of your improvement idea"
                          class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('description') border-red-500 @enderror">{{ $v('description') }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block font-semibold mb-1">Expected Outcome <span class="text-red-600">*</span></label>
                <textarea name="expected_outcome" rows="3" placeholder="What results do you expect from implementing this idea?"
                          class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('expected_outcome') border-red-500 @enderror">{{ $v('expected_outcome') }}</textarea>
                @error('expected_outcome')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold mb-1">Targeted Business Unit <span class="text-red-600">*</span></label>
                    <select name="business_unit_id"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('business_unit_id') border-red-500 @enderror">
                        <option value="">Select Business Unit</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu->id }}" @selected((string) $selBu === (string) $bu->id)>{{ $bu->name }}</option>
                        @endforeach
                    </select>
                    @error('business_unit_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block font-semibold mb-1">Targeted Department <span class="text-red-600">*</span></label>
                    <select name="department_id"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('department_id') border-red-500 @enderror">
                        <option value="">Select Department</option>
                        @foreach($departments as $dep)
                            <option value="{{ $dep->id }}" @selected((string) $selDep === (string) $dep->id)>
                                {{ $dep->name }} ({{ optional($dep->businessUnit)->name }})
                            </option>
                        @endforeach
                    </select>
                    @error('department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Targeted Company & Location (opsional) — untuk restrict scope --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold mb-1">Targeted Company <span class="text-gray-400 text-sm font-normal">(opsional)</span></label>
                    <select name="company_id" id="idea-company"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('company_id') border-red-500 @enderror">
                        <option value="">— Semua Company (level BU) —</option>
                        @foreach($companies as $co)
                            <option value="{{ $co->id }}" data-bu="{{ $co->business_unit_id }}" @selected((string) $selCo === (string) $co->id)>{{ $co->name }}</option>
                        @endforeach
                    </select>
                    @error('company_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block font-semibold mb-1">Targeted Location <span class="text-gray-400 text-sm font-normal">(opsional)</span></label>
                    <select name="location_id" id="idea-location"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('location_id') border-red-500 @enderror">
                        <option value="">— Semua Location (level Company) —</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" data-company="{{ $loc->company_id }}" @selected((string) $selLoc === (string) $loc->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                    @error('location_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Supporting Documents (T-17) --}}
            <div>
                <label class="block font-semibold mb-1">Supporting Documents</label>
                @if($isEdit && $idea->attachments->isNotEmpty())
                    <ul class="mb-3 space-y-1">
                        @foreach($idea->attachments as $att)
                            <li class="flex items-center justify-between text-sm border rounded px-3 py-1.5">
                                <a href="{{ route('ideas.attachments.download', [$idea, $att]) }}" class="text-red-700 hover:underline">{{ $att->file_name }}</a>
                                <button type="button" form="del-att-{{ $att->id }}" class="text-red-600 text-xs hover:underline"
                                        onclick="if(confirm('Hapus lampiran?')) document.getElementById('del-att-{{ $att->id }}').submit()">Hapus</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <input type="file" name="attachments[]" multiple
                       class="w-full text-sm border rounded-lg px-3 py-2 @error('attachments.*') border-red-500 @enderror">
                <p class="text-xs text-gray-400 mt-1">Opsional. Maks 10 MB/file — .pdf, .docx, .xlsx, .jpg, .jpeg, .png, .pptx</p>
                @error('attachments.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

        </div>
    </div>

    {{-- Buttons --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('ideas.index') }}" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</a>
        <button type="submit" name="action" value="draft"
                class="px-5 py-2 border border-red-700 text-red-700 rounded-lg hover:bg-red-50">Save as Draft</button>
        <button type="submit" name="action" value="submit"
                data-confirm="Setelah di-submit, ide akan masuk antrean review committee dan tidak bisa diedit lagi. Lanjutkan submit?"
                data-confirm-title="Submit Idea?"
                data-confirm-ok="Ya, Submit Idea"
                class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Submit Idea</button>
    </div>

</form>

{{-- Form hapus lampiran (terpisah agar tidak nested; dipicu tombol via atribut form=) --}}
@if($isEdit)
    @foreach($idea->attachments as $att)
        <form id="del-att-{{ $att->id }}" method="POST" action="{{ route('ideas.attachments.destroy', [$idea, $att]) }}" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endforeach
@endif

{{-- Cascading BU -> Company -> Location --}}
<script>
(function () {
    const bu       = document.querySelector('[name="business_unit_id"]');
    const company  = document.getElementById('idea-company');
    const location = document.getElementById('idea-location');
    if (!bu || !company || !location) return;

    function filterOptions(select, attr, matchValue) {
        Array.from(select.options).forEach(opt => {
            if (opt.value === '') return; // opsi "semua" selalu tampil
            const show = String(opt.dataset[attr]) === String(matchValue);
            opt.hidden = !show;
            if (!show && opt.selected) select.value = '';
        });
    }

    function syncCompany() { filterOptions(company, 'bu', bu.value); }
    function syncLocation() { filterOptions(location, 'company', company.value); }

    bu.addEventListener('change', () => { syncCompany(); syncLocation(); });
    company.addEventListener('change', syncLocation);

    // Jalankan saat load (hormati nilai lama / edit).
    syncCompany();
    syncLocation();
})();
</script>
