<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">SLA Setting</h2>
    </x-slot>

    @php
        $inp = 'w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200';
        $val = fn ($f) => old($f, $editing?->$f);
    @endphp

    <div class="p-6 space-y-6 max-w-7xl mx-auto w-full"
         x-data="{ slaOpen: {{ ($editing || $errors->any()) ? 'true' : 'false' }} }">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-gray-800">SLA Setting</h1>
            <button type="button" @click="slaOpen = true"
                    class="inline-flex items-center gap-1 px-5 py-2 bg-red-700 text-white rounded-lg text-sm font-semibold hover:bg-red-800">+ Add SLA</button>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">{{ session('error') }}</div>
        @endif

        {{-- List — tab Active / Inactive dengan jumlah --}}
        @php
            $activeCount   = $settings->where('is_active', true)->count();
            $inactiveCount = $settings->where('is_active', false)->count();
            $totalCount    = $settings->count();
            $tabDefs = ['all' => ['All', $totalCount], 'active' => ['Active', $activeCount], 'inactive' => ['Inactive', $inactiveCount]];
        @endphp
        <div class="bg-white rounded-xl shadow overflow-hidden" x-data="{ tab: 'all' }">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 pt-3">
                @foreach($tabDefs as $key => $def)
                    <button type="button" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-red-700 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition">
                        {{ $def[0] }}
                        <span :class="tab === '{{ $key }}' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'"
                              class="ml-1 inline-flex items-center justify-center min-w-5 px-1.5 py-0.5 text-xs rounded-full">{{ $def[1] }}</span>
                    </button>
                @endforeach
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3">Approval Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Review Limit (Days)</th>
                        <th class="px-4 py-3">Active</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($settings as $s)
                        @php $muted = $s->is_active ? '' : 'opacity-50'; $state = $s->is_active ? 'active' : 'inactive'; @endphp
                        <tr class="hover:bg-gray-50" x-show="tab === 'all' || tab === '{{ $state }}'">
                            <td class="px-4 py-3 {{ $muted }}">
                                <div class="font-semibold text-gray-800">{{ $labels[$s->approval_type] ?? $s->approval_type }}</div>
                            </td>
                            <td class="px-4 py-3 {{ $muted }}">{{ $s->statusLabel() }}</td>
                            <td class="px-4 py-3 {{ $muted }}">{{ $s->days }} days</td>
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
                                        <button data-confirm="Delete this SLA? This action is permanent." data-confirm-title="Delete SLA" data-confirm-ok="Yes, Delete"
                                                class="px-3 py-1 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No SLA yet. Add one below.</td></tr>
                    @endforelse
                    {{-- Empty state per tab (saat tab tertentu tak punya baris) --}}
                    @if($totalCount > 0)
                        <tr x-show="tab === 'active' && {{ $activeCount }} === 0" x-cloak><td colspan="5" class="px-4 py-8 text-center text-gray-400">No active SLA.</td></tr>
                        <tr x-show="tab === 'inactive' && {{ $inactiveCount }} === 0" x-cloak><td colspan="5" class="px-4 py-8 text-center text-gray-400">No inactive SLA.</td></tr>
                    @endif
                </tbody>
            </table>
            </div>
        </div>

        {{-- Dialog: Add / Edit SLA (popup) --}}
        <div x-show="slaOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape.window="slaOpen = false">
            <div class="fixed inset-0 bg-black/40" @click="slaOpen = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 p-6 max-h-[85vh] overflow-y-auto" x-transition.opacity>
                <h3 class="text-lg font-semibold mb-4">{{ $editing ? 'Edit SLA: '.($labels[$editing->approval_type] ?? $editing->approval_type) : 'Add SLA' }}</h3>

                <form method="POST" action="{{ $editing ? route('admin.sla.update', $editing) : route('admin.sla.store') }}" class="space-y-4">
                    @csrf
                    @if($editing) @method('PUT') @endif

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-1">Approval Type <span class="text-red-600">*</span></label>
                            <select name="approval_type" data-no-search class="{{ $inp }} @error('approval_type') border-red-500 @enderror">
                                <option value="">Select type…</option>
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($val('approval_type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('approval_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-1">Status <span class="text-red-600">*</span></label>
                            <select name="status" data-no-search required class="{{ $inp }} @error('status') border-red-500 @enderror">
                                <option value="">Select status…</option>
                                @foreach($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($val('status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-1">Review Limit (Days) <span class="text-red-600">*</span></label>
                            <input type="number" name="days" min="1" max="365" value="{{ $val('days') }}" required class="{{ $inp }} @error('days') border-red-500 @enderror">
                            @error('days')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-1">
                        @if($editing)
                            <a href="{{ route('admin.sla.index') }}" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</a>
                        @else
                            <button type="button" @click="slaOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                        @endif
                        <button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">{{ $editing ? 'Update' : 'Add SLA' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-app-layout>
