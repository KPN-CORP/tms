<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Guideline</h2>
    </x-slot>

    <div class="p-6 space-y-6 max-w-4xl">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Guideline</h1>
            <p class="text-gray-500">Pustaka dokumen panduan & referensi untuk seluruh user.</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif

        {{-- Upload (hanya pengelola) --}}
        @if($canManage)
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Upload Guideline</h3>
                <form method="POST" action="{{ route('guidelines.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-1">Title <span class="text-red-600">*</span></label>
                            <input name="title" value="{{ old('title') }}" required
                                   class="w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200 @error('title') border-red-500 @enderror">
                            @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-1">File <span class="text-red-600">*</span></label>
                            <input type="file" name="file" required
                                   class="w-full border rounded-lg px-3 py-2 text-sm @error('file') border-red-500 @enderror">
                            @error('file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            <p class="text-xs text-gray-400 mt-1">Maks 10 MB (pdf, doc, xls, ppt, gambar, zip, csv, txt).</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Description</label>
                        <textarea name="description" rows="2"
                                  class="w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200">{{ old('description') }}</textarea>
                    </div>
                    <div class="flex justify-end">
                        <button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Upload</button>
                    </div>
                </form>
            </div>
        @endif

        {{-- List --}}
        <div class="space-y-3">
            @forelse($guidelines as $g)
                <div class="bg-white rounded-xl shadow p-4 flex items-start justify-between gap-4 {{ $g->is_active ? '' : 'opacity-60' }}">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h4 class="font-semibold text-gray-800 truncate">{{ $g->title }}</h4>
                            @unless($g->is_active)
                                <span class="text-xs rounded-full px-2 py-0.5 bg-gray-200 text-gray-600">Archived</span>
                            @endunless
                        </div>
                        @if($g->description)<p class="text-sm text-gray-500 mt-1">{{ $g->description }}</p>@endif
                        <p class="text-xs text-gray-400 mt-2">
                            {{ $g->file_name }} · {{ $g->readable_size }}
                            @if($g->uploader) · oleh {{ $g->uploader->name }}@endif
                            · {{ $g->created_at?->format('d M Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('guidelines.download', $g) }}" class="px-3 py-1 text-xs border rounded-lg hover:bg-gray-100">Download</a>
                        @if($canManage)
                            <form method="POST" action="{{ route('guidelines.toggle', $g) }}">
                                @csrf
                                <button class="px-3 py-1 text-xs border {{ $g->is_active ? 'border-amber-300 text-amber-600' : 'border-green-300 text-green-600' }} rounded-lg">{{ $g->is_active ? 'Archive' : 'Restore' }}</button>
                            </form>
                            <form method="POST" action="{{ route('guidelines.destroy', $g) }}">
                                @csrf
                                @method('DELETE')
                                <button data-confirm="Hapus guideline ini?" data-confirm-title="Hapus Guideline" data-confirm-ok="Ya, Hapus" class="px-3 py-1 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow p-8 text-center text-gray-400">Belum ada guideline.</div>
            @endforelse
        </div>

    </div>

</x-app-layout>
