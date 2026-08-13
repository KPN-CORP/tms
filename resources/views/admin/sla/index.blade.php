<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">SLA Setting</h2>
    </x-slot>

    @php
        $inp = 'w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200';
    @endphp

    <div class="p-6 space-y-6 max-w-4xl">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">SLA Setting</h1>
            <p class="text-gray-500">Batas waktu (hari) review untuk tiap jenis approval, beserta status yang menjadi basis perhitungan. Dipakai menghitung status On Time / Due Soon / Overdue di halaman review (info-only, tanpa notifikasi).</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.sla.update') }}" class="bg-white rounded-xl shadow overflow-hidden">
            @csrf
            @method('PUT')

            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3">Jenis Approval</th>
                        <th class="px-4 py-3">Batas Review (Hari)</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aktif</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($settings as $s)
                        @php $muted = $s->is_active ? '' : 'opacity-50'; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800 {{ $muted }}">{{ $labels[$s->approval_type] ?? $s->approval_type }}</div>
                                <div class="font-mono text-xs text-red-700 {{ $muted }}">{{ strtoupper($s->approval_type) }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" min="1" max="365"
                                       name="days[{{ $s->approval_type }}]"
                                       value="{{ old('days.'.$s->approval_type, $s->days) }}"
                                       class="w-28 border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200">
                                @error('days.'.$s->approval_type)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </td>
                            <td class="px-4 py-3">
                                @php $selStatus = old('status.'.$s->approval_type, $s->status); @endphp
                                <select name="status[{{ $s->approval_type }}]" data-no-search
                                        class="{{ $inp }} max-w-[220px]">
                                    <option value="">— pilih status —</option>
                                    @foreach($statusOptions as $val => $label)
                                        <option value="{{ $val }}" @selected($selStatus === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status.'.$s->approval_type)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </td>
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" name="active[{{ $s->approval_type }}]" value="1"
                                           @checked($s->is_active)
                                           class="rounded border-gray-300 text-red-600 focus:ring-red-200">
                                    <span class="text-gray-600">SLA berlaku</span>
                                </label>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Save SLA</button>
            </div>
        </form>

        <p class="text-xs text-gray-400">Catatan: hitungan mulai dari saat item masuk ke layer review saat ini (keputusan approval terakhir, atau waktu submit untuk layer pertama). "Due Soon" = sisa ≤ 1 hari. Kolom <b>Status</b> menandai status yang menjadi basis perhitungan SLA (mis. Idea 3 hari saat "On Review").</p>

    </div>

</x-app-layout>
