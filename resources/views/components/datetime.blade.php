@props([
    'value'    => null,
    'mode'     => 'datetime', // datetime | date | time
    'fallback' => '-',
])

@php
    // Server merender UTC sebagai teks cadangan; window.tmsTime menulis ulang
    // sesuai timezone browser (WIB/WITA/WIT atau GMT±offset) setelah halaman siap.
    $dt = $value instanceof \Carbon\CarbonInterface
        ? $value
        : ($value ? \Illuminate\Support\Carbon::parse($value) : null);

    $utc = $dt?->copy()->utc();

    $text = match ($mode) {
        'date' => $utc?->format('d M Y'),
        'time' => $utc?->format('H:i') . ' UTC',
        default => $utc?->format('d M Y H:i') . ' UTC',
    };
@endphp

@if($dt)
    <time datetime="{{ $utc->toIso8601ZuluString() }}" data-tms-time="{{ $mode }}" {{ $attributes }}>{{ $text }}</time>
@else
    <span {{ $attributes }}>{{ $fallback }}</span>
@endif
