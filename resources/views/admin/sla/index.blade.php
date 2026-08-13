<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">SLA Setting</h2>
    </x-slot>

    @php
        $inp = 'w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200';
        $val = fn ($f) => old($f, $editing?->$f);
    @endphp

    <div class="p-6 space-y-6 max-w-4xl">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">SLA Setting</h1>
            <p class="text-gray-500">Batas waktu (hari) review per jenis approval &amp; status. Boleh lebih dari satu per jenis (mis. 3 hari saat "On Review", 30 hari saat "Submitted"). Info-only untuk status On Time / Due Soon / Overdue.</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">{{ session('error') }}</div>
        @endif

        {{-- List --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3">Jenis Approval</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Batas Review (Hari)</th>
                        <th class="px-4 py-3">Aktif</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($settings as $s)
                        @php $muted = $s->is_active ? '' : 'opacity-50'; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 {{ $muted }}">
                                <div class="font-semibold text-gray-800">{{ $labels[$s->approval_type] ?? $s->approval_type }}</div>
                            </td>
                            <td class="px-4 py-3 {{ $muted }}">{{ $s->statusLabel() }}</td>
                            <td class="px-4 py-3 {{ $muted }}">{{ $s->days }} hari</td>
                            <td class="px-4 py-3">
                                <span class="text-xs rounded-full px-2 py-1 {{ $s->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">{{ $s->is_active ? 'Active' : 'Archived' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.sla.index', ['edit' => $s->id]) }}" class="px-3 py-1 text-xs border rounded-lg hover:bg-gray-100">Edit</a>
                                    <form method="POST" action="{{ route('admin.sla.toggle', $s) }}">
                                        @csrf
                                        <button class="px-3 py-1 text-xs border {{ $s->is_active ? 'border-red-300 text-red-600' : 'border-green-300 text-green-600' }} rounded-lg">{{ $s->is_active ? 'Archive' : 'Restore' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.sla.destroy', $s) }}">
                                        @csrf @method('DELETE')
                                        <button data-confirm="Hapus SLA ini? Tindakan ini permanen." data-confirm-title="Hapus SLA" data-confirm-ok="Ya, Hapus"
                                                class="px-3 py-1 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada SLA. Tambahkan di bawah.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Add / Edit form --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold mb-4">{{ $editing ? 'Edit SLA: '.($labels[$editing->approval_type] ?? $editing->approval_type) : 'Add SLA' }}</h3>

            <form method="POST" action="{{ $editing ? route('admin.sla.update', $editing) : route('admin.sla.store') }}" class="space-y-4">
                @csrf
                @if($editing) @method('PUT') @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Jenis Approval <span class="text-red-600">*</span></label>
                        <select name="approval_type" data-no-search class="{{ $inp }} @error('approval_type') border-red-500 @enderror">
                            <option value="">Pilih jenis…</option>
                            @foreach($typeOptions as $key => $label)
                                <option value="{{ $key }}" @selected($val('approval_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('approval_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Status <span class="text-gray-400 font-normal">(optional)</span></label>
                        <select name="status" data-no-search class="{{ $inp }} @error('status') border-red-500 @enderror">
                            <option value="">— tanpa status —</option>
                            @foreach($statusOptions as $key => $label)
                                <option value="{{ $key }}" @selected($val('status') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Batas Review (Hari) <span class="text-red-600">*</span></label>
                        <input type="number" name="days" min="1" max="365" value="{{ $val('days') }}" required class="{{ $inp }} @error('days') border-red-500 @enderror">
                        @error('days')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    @if($editing)<a href="{{ route('admin.sla.index') }}" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</a>@endif
                    <button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">{{ $editing ? 'Update' : 'Add SLA' }}</button>
                </div>
            </form>
        </div>

        <p class="text-xs text-gray-400">Catatan: hitungan mulai saat item masuk layer review. "Due Soon" = sisa ≤ 1 hari. SLA yang di-<b>Archive</b> tidak dipakai perhitungan (bisa di-Restore).</p>

    </div>

</x-app-layout>
