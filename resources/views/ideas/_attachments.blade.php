{{--
    Daftar Supporting Documents.
    - jpg/jpeg/png/pdf  → klik membuka file di TAB BARU (inline), tanpa modal.
    - selain itu (xlsx/pptx/dll) → klik langsung mengunduh.
    Butuh: $idea. Opsional: $box (kelas kotak untuk state kosong).
--}}
@php $viewable = ['jpg', 'jpeg', 'png', 'pdf']; @endphp

<label class="block text-sm font-semibold text-gray-600 mb-1">Supporting Documents</label>

@forelse($idea->attachments as $att)
    @php $ext = strtolower(pathinfo($att->file_name, PATHINFO_EXTENSION)); @endphp
    <div class="flex items-center justify-between border-b py-2 last:border-0 text-sm">
        {{-- KIRI: nama file --}}
        @if(in_array($ext, $viewable))
            {{-- PDF & gambar → klik nama buka di tab baru (inline) --}}
            <a href="{{ route('ideas.attachments.view', [$idea, $att]) }}" target="_blank" rel="noopener"
               class="text-red-700 hover:underline truncate">{{ $att->file_name }}</a>
        @else
            {{-- xlsx/pptx/dll → klik nama langsung download --}}
            <a href="{{ route('ideas.attachments.download', [$idea, $att]) }}"
               class="text-red-700 hover:underline truncate">{{ $att->file_name }}</a>
        @endif
        {{-- KANAN: tombol Download, lalu ukuran file paling kanan --}}
        <span class="flex items-center gap-3 shrink-0 ml-3">
            <a href="{{ route('ideas.attachments.download', [$idea, $att]) }}"
               class="text-xs text-red-700 hover:text-red-700 hover:underline">Download</a>
            <span class="text-gray-400">{{ $att->file_size ? number_format($att->file_size / 1024, 0) . ' KB' : '' }}</span>
        </span>
    </div>
@empty
    <div class="{{ $box ?? 'border rounded-lg px-4 py-3' }} text-gray-400">No attachments.</div>
@endforelse
