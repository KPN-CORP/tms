<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Guideline</h2>
    </x-slot>

    <div class="p-6 space-y-6 max-w-7xl mx-auto w-full"
         x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, tab: 'active' }">

        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Guideline</h1>
            </div>
            @if($canManage)
                <button type="button" @click="open = true"
                        class="inline-flex items-center gap-1 px-5 py-2 bg-red-700 text-white rounded-lg text-sm font-semibold hover:bg-red-800 shrink-0">+ Add Guideline</button>
            @endif
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif

        {{-- List guideline — dipisah TAB Active / Inactive --}}
        @php
            $byStatus = $guidelines->groupBy(fn ($g) => $g->is_active ? 'active' : 'inactive');
            // Non-pengelola hanya menerima guideline aktif — tab Inactive tidak relevan baginya.
            $tabs = $canManage ? ['active' => 'Active', 'inactive' => 'Inactive'] : ['active' => 'Active'];
        @endphp

        <div class="flex flex-wrap items-center gap-2 border-b border-gray-200" @class(['hidden' => ! $canManage])>
            @foreach($tabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-red-700 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                        class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition">
                    {{ $label }}
                    <span :class="tab === '{{ $key }}' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'"
                          class="ml-1 inline-flex items-center justify-center min-w-5 px-1.5 py-0.5 text-xs rounded-full">{{ optional($byStatus->get($key))->count() ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        @foreach($tabs as $key => $label)
            @php $rows = $byStatus->get($key) ?? collect(); @endphp
            <div x-show="tab === '{{ $key }}'" x-cloak class="space-y-3">
                @forelse($rows as $g)
                        @php
                            $canView     = $canManage || $g->viewableBy($isCommittee);
                            $canDownload = $canManage || $g->downloadableBy($isCommittee);
                        @endphp
                        <div class="bg-white rounded-xl shadow p-5 {{ $g->is_active ? '' : 'opacity-60' }}">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-gray-800 truncate">{{ $g->title }}</h4>
                                    @if($g->description)<p class="text-sm text-gray-500 mt-1">{{ $g->description }}</p>@endif
                                    <p class="text-xs text-gray-400 mt-2">
                                        {{ $g->file_name }} · {{ $g->readable_size }}
                                        @if($g->uploader) · by {{ $g->uploader->name }}@endif
                                        · <x-datetime :value="$g->created_at" mode="date" />
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($canView && $g->isInlineViewable())
                                        <a href="{{ route('guidelines.view', $g) }}" target="_blank" rel="noopener"
                                           class="px-3 py-1 text-xs bg-red-700 text-white rounded-lg hover:bg-red-800">View</a>
                                    @endif
                                    @if($canDownload)
                                        <a href="{{ route('guidelines.download', $g) }}" class="px-3 py-1 text-xs border rounded-lg hover:bg-gray-100">Download</a>
                                    @endif
                                    @if($canManage)
                                        <form method="POST" action="{{ route('guidelines.toggle', $g) }}">
                                            @csrf
                                            <button class="px-3 py-1 text-xs border {{ $g->is_active ? 'border-amber-300 text-amber-600' : 'border-green-300 text-green-600' }} rounded-lg">{{ $g->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            {{-- Ringkasan hak akses (read-only). Diatur saat upload. --}}
                            @if($canManage)
                                <div class="mt-3 pt-3 border-t flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                                    <span class="font-semibold text-gray-500">Access:</span>
                                    <span><span class="font-semibold text-gray-600">Employee</span> — {{ collect(['View' => $g->employee_can_view, 'Download' => $g->employee_can_download])->filter()->keys()->implode(', ') ?: 'None' }}</span>
                                    <span><span class="font-semibold text-gray-600">Committee</span> — {{ collect(['View' => $g->committee_can_view, 'Download' => $g->committee_can_download])->filter()->keys()->implode(', ') ?: 'None' }}</span>
                                </div>
                            @endif
                        </div>
                @empty
                    <div class="bg-white rounded-xl shadow p-8 text-center text-gray-400">{{ $canManage ? 'No '.strtolower($label).' guidelines.' : 'No guidelines yet.' }}</div>
                @endforelse
            </div>
        @endforeach

        {{-- Dialog: Upload Guideline (popup) --}}
        @if($canManage)
            <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape.window="open = false">
                <div class="fixed inset-0 bg-black/40" @click="open = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 p-6 max-h-[85vh] overflow-y-auto" x-transition.opacity>
                    <h3 class="text-lg font-semibold mb-4">Upload Guideline</h3>
                    <form method="POST" action="{{ route('guidelines.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-1">Title <span class="text-red-600">*</span></label>
                            <input name="title" value="{{ old('title') }}" required
                                   class="w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200 @error('title') border-red-500 @enderror">
                            @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-1">Description</label>
                            <textarea name="description" rows="2"
                                      class="w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200">{{ old('description') }}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-1">File <span class="text-red-600">*</span></label>
                            {{-- Drag & drop / click to choose (single file) --}}
                            <div x-data="{
                                    fileName: '', drag: false,
                                    pick(list){ if (list && list.length) { this.$refs.input.files = list; this.fileName = list[0].name; } },
                                    drop(e){ this.drag = false; this.pick(e.dataTransfer.files); }
                                 }">
                                <input type="file" name="file" required x-ref="input" @change="fileName = ($refs.input.files[0]?.name) || ''"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.zip,.csv,.txt" class="hidden">
                                <div @click="$refs.input.click()"
                                     @dragover.prevent="drag = true" @dragleave.prevent="drag = false" @drop.prevent="drop($event)"
                                     :class="drag ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                                     class="cursor-pointer border-2 border-dashed rounded-lg px-4 py-6 text-center text-sm text-gray-500 hover:bg-gray-50 @error('file') border-red-500 @enderror">
                                    <span x-show="! fileName"><span class="font-semibold text-red-700">Choose file</span> or drag &amp; drop here</span>
                                    <span x-show="fileName" x-text="fileName" class="font-medium text-gray-700 break-all"></span>
                                </div>
                            </div>
                            @error('file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            <p class="text-xs text-gray-400 mt-1">Max 10 MB (pdf, doc, xls, ppt, image, zip, csv, txt).</p>
                        </div>

                        {{-- Access Rights — di-set bersamaan saat upload --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-2">Access Rights</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="border rounded-lg p-3">
                                    <div class="font-semibold text-gray-700 text-sm mb-2">Employee</div>
                                    <div class="flex items-center gap-6 text-sm text-gray-600">
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="employee_can_view" value="1" @checked(old('employee_can_view', true))> View</label>
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="employee_can_download" value="1" @checked(old('employee_can_download'))> Download</label>
                                    </div>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <div class="font-semibold text-gray-700 text-sm mb-2">Committee</div>
                                    <div class="flex items-center gap-6 text-sm text-gray-600">
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="committee_can_view" value="1" @checked(old('committee_can_view'))> View</label>
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="committee_can_download" value="1" @checked(old('committee_can_download'))> Download</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-1">
                            <button type="button" @click="open = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                            <button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </div>

</x-app-layout>
