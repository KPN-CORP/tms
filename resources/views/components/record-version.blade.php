@props(['model' => null])

{{-- Sidik jari versi record yang sedang dibaca pengguna. Dikirim bersama form
     edit; bila isi record sudah berubah saat submit, server menolak agar
     perubahan orang lain tidak tertimpa diam-diam. --}}
@php $v = \App\Support\RecordVersion::of($model); @endphp

@if($v)
    <input type="hidden" name="{{ \App\Support\RecordVersion::FIELD }}" value="{{ $v }}">
@endif
