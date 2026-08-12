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
        @if(in_array($ext, $viewable))
            {{-- PDF & gambar → klik nama buka di tab baru (inline) --}}
            <a href="{{ route('ideas.attachments.view', [$idea, $att]) }}" target="_blank" rel="noopener"
               class="text-red-700 hover:underline truncate">{{ $att->file_name }}</a>
        @else
            {{-- xlsx/pptx/dll → langsung download --}}
            <a href="{{ route('ideas.attachments.download', [$idea, $att]) }}"
               class="text-red-700 hover:underline truncate">{{ $att->file_name }}</a>
        @endif
        <span class="flex items-center gap-3 shrink-0 ml-3">
            <span class="text-gray-400">{{ $att->file_size ? number_format($att->file_size / 1024, 0) . ' KB' : '' }}</span>
            @if(in_array($ext, $viewable))
                {{-- Selain view di tab baru, sediakan juga tombol Download --}}
                <a href="{{ route('ideas.attachments.download', [$idea, $att]) }}"
                   class="text-xs text-gray-500 hover:text-red-700 hover:underline">Download</a>
            @endif
        </span>
    </div>
@empty
    <div class="{{ $box ?? 'border rounded-lg px-4 py-3' }} text-gray-400">Tidak ada lampiran.</div>
@endforelse
