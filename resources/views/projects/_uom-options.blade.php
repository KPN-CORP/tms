{{-- Opsi dropdown UoM (dikelompokkan per kategori). Opsional: $selected = nilai terpilih. --}}
<option value="">Select UoM…</option>
@foreach(\App\Models\ImplementationIndicator::UOM_GROUPS as $group => $opts)
    <optgroup label="{{ $group }}">
        @foreach($opts as $u)
            <option value="{{ $u }}" @selected(($selected ?? null) === $u)>{{ $u }}</option>
        @endforeach
    </optgroup>
@endforeach
