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
            @php $emp = $employeeInfo ?? null; @endphp
            <div class="border rounded-lg px-4 py-3">
                <div class="text-xs text-gray-400">BUSINESS UNIT</div>
                <div class="font-semibold">{{ ($emp?->businessUnitName()) ?? optional($u->businessUnit)->name ?? '-' }}</div>
            </div>
            <div class="border rounded-lg px-4 py-3">
                <div class="text-xs text-gray-400">DEPARTMENT</div>
                <div class="font-semibold">{{ ($emp?->departmentName()) ?? optional($u->department)->name ?? '-' }}</div>
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
                       placeholder="Title of the submitted idea"
                       class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('idea_name') border-red-500 @enderror">
                @error('idea_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block font-semibold mb-1">Problem / Root Cause <span class="text-red-600">*</span></label>
                <textarea name="problem" rows="3" placeholder="Background of the trouble or problem that occur in the targeted improvement area"
                          class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('problem') border-red-500 @enderror">{{ $v('problem') }}</textarea>
                @error('problem')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block font-semibold mb-1">Detailed Description <span class="text-red-600">*</span></label>
                <textarea name="description" rows="4" placeholder="Proposed improvement or solution with practical actionable items to implement that idea"
                          class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('description') border-red-500 @enderror">{{ $v('description') }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block font-semibold mb-1">Expected Outcome <span class="text-red-600">*</span></label>
                <textarea name="expected_outcome" rows="3" placeholder="Quantitative changes that can be seen/feel after the idea is implemented, such as cost reduction, time efficiency, quality improvement, etc"
                          class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('expected_outcome') border-red-500 @enderror">{{ $v('expected_outcome') }}</textarea>
                @error('expected_outcome')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    <label class="block font-semibold mb-1">Targeted Unit <span class="text-red-600">*</span></label>
                    {{-- Diisi via AJAX dari hcis sesuai Business Unit terpilih (data-remote-*) --}}
                    <select name="department" id="idea-dept"
                            data-remote-parent="#idea-bu" data-remote-url="{{ route('org.departments') }}" data-selected="{{ $selDept }}"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('department') border-red-500 @enderror">
                        <option value="">Select Unit</option>
                        @if($selDept)<option value="{{ $selDept }}" selected>{{ $selDept }}</option>@endif
                    </select>
                    @error('department')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Company & Location (opsional) — dari hcis, cascade dari Business Unit --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold mb-1">Targeted Company <span class="text-gray-400 text-sm font-normal">(optional)</span></label>
                    <select name="company" id="idea-company"
                            data-remote-parent="#idea-bu" data-remote-url="{{ route('org.companies') }}" data-selected="{{ $selCompany }}"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('company') border-red-500 @enderror">
                        <option value="">Select Company</option>
                        @if($selCompany)<option value="{{ $selCompany }}" selected>{{ $selCompany }}</option>@endif
                    </select>
                    @error('company')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block font-semibold mb-1">Targeted Location <span class="text-gray-400 text-sm font-normal">(optional)</span></label>
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
                                <button type="submit" form="del-att-{{ $att->id }}" class="text-red-600 text-xs hover:underline"
                                        data-confirm="Delete this attachment?" data-confirm-title="Delete Attachment" data-confirm-ok="Yes, Delete">Delete</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                {{-- Multi-file: pilih/tarik banyak file, bisa batalkan salah satu sebelum submit --}}
                <div x-data="{
                        files: [], drag: false,
                        add(e){ this.push(e.target.files); },
                        drop(e){ this.drag = false; this.push(e.dataTransfer.files); },
                        push(list){ for (const f of list){ if (!this.files.some(x => x.name === f.name && x.size === f.size)) this.files.push(f); } this.sync(); },
                        remove(i){ this.files.splice(i, 1); this.sync(); },
                        sync(){ const dt = new DataTransfer(); this.files.forEach(f => dt.items.add(f)); this.$refs.input.files = dt.files; },
                        human(b){ return b >= 1048576 ? (b/1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b/1024)) + ' KB'; }
                     }">
                    <input type="file" name="attachments[]" multiple x-ref="input" @change="add($event)"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.zip,.csv,.txt"
                           class="hidden">

                    {{-- Drop zone / tombol pilih --}}
                    <div @click="$refs.input.click()"
                         @dragover.prevent="drag = true" @dragleave.prevent="drag = false" @drop.prevent="drop($event)"
                         :class="drag ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                         class="cursor-pointer border-2 border-dashed rounded-lg px-4 py-6 text-center text-sm text-gray-500 hover:bg-gray-50 @error('attachments.*') border-red-500 @enderror">
                        <span class="font-semibold text-red-700">Choose files</span> or drag &amp; drop here
                    </div>

                    {{-- Daftar file terpilih + tombol batal per file --}}
                    <ul class="mt-3 space-y-2" x-show="files.length" x-cloak>
                        <template x-for="(f, i) in files" :key="f.name + f.size + i">
                            <li class="flex items-center justify-between gap-3 border rounded-lg px-3 py-2 text-sm bg-white">
                                <span class="truncate" x-text="f.name"></span>
                                <span class="flex items-center gap-3 shrink-0">
                                    <span class="text-gray-400" x-text="human(f.size)"></span>
                                    <button type="button" @click="remove(i)" title="Remove this file"
                                            class="w-6 h-6 flex items-center justify-center rounded hover:bg-red-50 text-red-600 text-lg leading-none">&times;</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
                <p class="text-xs text-gray-400 mt-1">Opsional. Bisa banyak file. Maks 10 MB/file — .pdf, .docx, .xlsx, .jpg, .jpeg, .png, .pptx</p>
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
                data-confirm="Once submitted, this idea enters the committee review queue and can no longer be edited. Continue?"
                data-confirm-title="Submit Idea?"
                data-confirm-ok="Yes, Submit Idea"
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
