@props([
    'column',            // key sort publik (harus ada di whitelist controller)
    'sort' => null,      // key sort yang sedang aktif
    'dir' => 'desc',     // arah aktif: asc|desc
    'align' => 'left',   // left|right|center
])

@php
    $isActive = $sort === $column;
    // Klik: aktifkan kolom ini; bila sudah aktif → toggle arah, jika belum → asc.
    $nextDir = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
    $params  = array_merge(request()->query(), ['sort' => $column, 'dir' => $nextDir, 'page' => 1]);
    $url     = request()->url() . '?' . http_build_query($params);
    $justify = ['right' => 'justify-end', 'center' => 'justify-center'][$align] ?? '';
    $thAlign = ['right' => 'text-right', 'center' => 'text-center'][$align] ?? '';
@endphp

<th {{ $attributes->merge(['class' => "px-6 py-3 $thAlign"]) }}>
    <a href="{{ $url }}" class="inline-flex items-center gap-1 {{ $justify }} hover:text-gray-800 group select-none">
        <span>{{ $slot }}</span>
        @if($isActive)
            <span class="text-red-600">{{ $dir === 'asc' ? '▲' : '▼' }}</span>
        @else
            <span class="text-gray-300 group-hover:text-gray-500">↕</span>
        @endif
    </a>
</th>
