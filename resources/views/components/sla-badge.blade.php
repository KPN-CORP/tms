@props(['sla'])

@php
    // $sla = hasil SlaService::evaluate() → ['state','days','since','due','left']
    [$label, $cls] = match ($sla['state'] ?? 'none') {
        'on_time'  => ['On Time', 'bg-green-100 text-green-700'],
        'due_soon' => ['Due Soon', 'bg-amber-100 text-amber-700'],
        'overdue'  => ['Overdue', 'bg-red-100 text-red-700'],
        default    => ['No SLA', 'bg-gray-100 text-gray-500'],
    };
    $left = $sla['left'] ?? null;
    $detail = null;
    if (($sla['state'] ?? 'none') !== 'none' && $left !== null) {
        $detail = $left < 0
            ? abs($left) . 'h lewat'
            : ($left === 0 ? 'jatuh tempo hari ini' : $left . 'h lagi');
    }
@endphp

<span class="inline-flex flex-col">
    <span class="inline-flex px-2 py-1 text-xs rounded-full font-medium {{ $cls }}">{{ $label }}</span>
    @if($detail)
        <span class="text-[11px] text-gray-400 mt-0.5">
            {{ $detail }}@if(!empty($sla['due'])) · {{ $sla['due']->format('d M') }}@endif
        </span>
    @endif
</span>
