@props([
    'paginator',        // LengthAwarePaginator
    'perPage' => 10,
])

<div class="flex flex-wrap items-center justify-between gap-3 p-4 border-t border-gray-100">
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <span>Show</span>
        <select name="per_page" onchange="this.form.requestSubmit()" data-no-search
                class="border border-gray-300 rounded-lg pl-3 pr-9 py-1 text-sm focus:ring focus:ring-red-200">
            @foreach([10, 25, 50, 100] as $n)
                <option value="{{ $n }}" @selected($perPage == $n)>{{ $n }}</option>
            @endforeach
        </select>
        <span>entries</span>
        @if($paginator->total())
            <span class="ml-1 text-gray-400">({{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }})</span>
        @endif
    </div>
    <div>{{ $paginator->links('partials.pagination') }}</div>
</div>
