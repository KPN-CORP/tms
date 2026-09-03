@props([
    // bindName dipakai bila komponen berada di dalam <template x-for>, sehingga
    // nama field harus dirangkai Alpine per baris (mis. 'rows['+i+'][attachments][]').
    'bindName' => null,
    'name'   => 'attachments[]',
    'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.zip,.csv,.txt',
    'hint'   => 'Max 10 MB per file. Multiple files allowed.',
])

{{-- Pemilih berkas drag-and-drop, boleh banyak file, tiap file bisa dibatalkan
     sebelum submit. Pola sama dengan Supporting Documents pada form Idea. --}}
<div x-data="{
        files: [], drag: false,
        add(e){ this.push(e.target.files); },
        drop(e){ this.drag = false; this.push(e.dataTransfer.files); },
        push(list){ for (const f of list){ if (!this.files.some(x => x.name === f.name && x.size === f.size)) this.files.push(f); } this.sync(); },
        remove(i){ this.files.splice(i, 1); this.sync(); },
        sync(){ const dt = new DataTransfer(); this.files.forEach(f => dt.items.add(f)); this.$refs.input.files = dt.files; },
        human(b){ return b >= 1048576 ? (b/1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b/1024)) + ' KB'; }
     }">
    @if($bindName)
        <input type="file" :name="{!! $bindName !!}" multiple x-ref="input" @change="add($event)" accept="{{ $accept }}" class="hidden">
    @else
        <input type="file" name="{{ $name }}" multiple x-ref="input" @change="add($event)" accept="{{ $accept }}" class="hidden">
    @endif

    <div @click="$refs.input.click()"
         @dragover.prevent="drag = true" @dragleave.prevent="drag = false" @drop.prevent="drop($event)"
         :class="drag ? 'border-red-400 bg-red-50' : 'border-gray-300'"
         class="cursor-pointer border-2 border-dashed rounded-lg px-4 py-5 text-center text-sm text-gray-500 hover:bg-gray-50">
        <span class="font-semibold text-red-700">Choose files</span> or drag &amp; drop here
    </div>

    <ul class="mt-2 space-y-1" x-show="files.length" x-cloak>
        <template x-for="(f, i) in files" :key="f.name + f.size + i">
            <li class="flex items-center justify-between gap-3 border rounded-lg px-3 py-1.5 text-sm bg-white">
                <span class="truncate" x-text="f.name"></span>
                <span class="flex items-center gap-3 shrink-0">
                    <span class="text-gray-400" x-text="human(f.size)"></span>
                    <button type="button" @click="remove(i)" title="Remove this file"
                            class="w-6 h-6 flex items-center justify-center rounded hover:bg-red-50 text-red-600 text-lg leading-none">&times;</button>
                </span>
            </li>
        </template>
    </ul>
    <p class="text-xs text-gray-400 mt-1">{{ $hint }}</p>
</div>
