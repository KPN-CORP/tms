@php
    $u        = Auth::user();
    $isEdit   = isset($idea);
    $v        = fn ($f) => old($f, $isEdit ? $idea->$f : '');
    $selBu       = old('business_unit', $isEdit ? $idea->business_unit_name : '');
    $selDept     = old('department',    $isEdit ? $idea->department_name : '');
    $selCompany  = old('company',       $isEdit ? $idea->company_name : '');
    $selLocation = old('location',      $isEdit ? $idea->location_name : '');
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
                    <select name="business_unit" id="idea-bu"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('business_unit') border-red-500 @enderror">
                        <option value="">Select Business Unit</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu }}" @selected((string) $selBu === (string) $bu)>{{ $bu }}</option>
                        @endforeach
                    </select>
                    @error('business_unit')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block font-semibold mb-1">Targeted Department <span class="text-red-600">*</span></label>
                    {{-- Diisi via AJAX dari hcis sesuai Business Unit terpilih (data-remote-*) --}}
                    <select name="department" id="idea-dept"
                            data-remote-parent="#idea-bu" data-remote-url="{{ route('org.departments') }}" data-selected="{{ $selDept }}"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('department') border-red-500 @enderror">
                        <option value="">Select Department</option>
                        @if($selDept)<option value="{{ $selDept }}" selected>{{ $selDept }}</option>@endif
                    </select>
                    @error('department')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Company & Location (opsional) — dari hcis, cascade dari Business Unit --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold mb-1">Targeted Company <span class="text-gray-400 text-sm font-normal">(opsional)</span></label>
                    <select name="company" id="idea-company"
                            data-remote-parent="#idea-bu" data-remote-url="{{ route('org.companies') }}" data-selected="{{ $selCompany }}"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('company') border-red-500 @enderror">
                        <option value="">Select Company</option>
                        @if($selCompany)<option value="{{ $selCompany }}" selected>{{ $selCompany }}</option>@endif
                    </select>
                    @error('company')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block font-semibold mb-1">Targeted Location <span class="text-gray-400 text-sm font-normal">(opsional)</span></label>
                    <select name="location" id="idea-location"
                            data-remote-parent="#idea-bu" data-remote-url="{{ route('org.locations') }}" data-selected="{{ $selLocation }}"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('location') border-red-500 @enderror">
                        <option value="">Select Location</option>
                        @if($selLocation)<option value="{{ $selLocation }}" selected>{{ $selLocation }}</option>@endif
                    </select>
                    @error('location')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
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

{{-- Cascade Business Unit → Department (AJAX ke hcis) ditangani global di app layout. --}}
