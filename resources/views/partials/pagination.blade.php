@if ($paginator->hasPages())
    {{-- Pagination seragam abu-abu. Styling via <style> inline agar tidak bergantung
         pada build Tailwind (class arbitrary bisa belum ter-compile). --}}
    <style>
        .pg-nav{display:flex;align-items:center;gap:.25rem;flex-wrap:wrap}
        .pg-item{display:inline-flex;align-items:center;justify-content:center;min-width:2.25rem;height:2.25rem;padding:0 .55rem;border-radius:.5rem;font-size:.875rem;line-height:1;color:#4b5563;background:#f3f4f6;border:1px solid #e5e7eb;text-decoration:none;transition:background .15s}
        .pg-item:hover{background:#e5e7eb}
        .pg-item--active{background:#6b7280;color:#fff;border-color:#6b7280;font-weight:600}
        .pg-item--disabled{color:#d1d5db;background:#f9fafb;border-color:#f3f4f6;cursor:not-allowed}
        .pg-dots{display:inline-flex;align-items:center;justify-content:center;min-width:2rem;height:2.25rem;color:#9ca3af;font-size:.875rem}
    </style>

    <nav class="pg-nav" role="navigation" aria-label="Pagination">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="pg-item pg-item--disabled" aria-hidden="true">&lsaquo;</span>
        @else
            <a class="pg-item" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">&lsaquo;</a>
        @endif

        {{-- Nomor halaman --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pg-dots">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pg-item pg-item--active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pg-item" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a class="pg-item" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">&rsaquo;</a>
        @else
            <span class="pg-item pg-item--disabled" aria-hidden="true">&rsaquo;</span>
        @endif
    </nav>
@endif
