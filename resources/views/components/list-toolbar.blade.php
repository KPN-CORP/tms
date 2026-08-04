@props([
    'action' => null,                 // target GET (default: URL sekarang)
    'placeholder' => 'Search…',
    'search' => '',                   // nilai q saat ini
])

@php
    $action = $action ?: request()->url();
    // Param sort/dir dipertahankan saat search/filter di-submit.
    $preserve = array_filter([
        'sort' => request('sort'),
        'dir'  => request('dir'),
    ], fn ($v) => $v !== null && $v !== '');
    // Reset tampil bila ada query aktif apa pun (search/filter), abaikan sort/dir/page.
    $hasActiveFilter = collect(request()->query())
        ->except(['sort', 'dir', 'page'])
        ->filter(fn ($v) => $v !== null && $v !== '')
        ->isNotEmpty();
@endphp

<form method="GET" action="{{ $action }}"
      class="flex flex-wrap items-end gap-3 bg-white rounded-xl shadow p-4">

    @foreach($preserve as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Search</label>
        <input name="q" value="{{ $search }}" placeholder="{{ $placeholder }}"
               x-data
               x-on:input.debounce.500ms="$el.form.requestSubmit()"
               class="w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200">
    </div>

    {{-- Slot: filter tambahan per-halaman (status, kategori, BU, dll) --}}
    {{ $slot }}

    <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">Apply</button>

    @if($hasActiveFilter)
        <a href="{{ $action }}" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-100">Reset</a>
    @endif
</form>
