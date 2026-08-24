<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Committee Assignment</h2>
    </x-slot>

    <div class="p-6 space-y-6 max-w-7xl mx-auto w-full">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-gray-800">Committee Assignment</h1>
            <a href="{{ route('admin.committee.form') }}"
               class="inline-flex items-center gap-1 px-5 py-2 bg-red-700 text-white rounded-lg text-sm font-semibold hover:bg-red-800">+ Add Committee</a>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        {{-- Daftar committee yang sudah diatur — dipisah TAB per Approval Type --}}
        @php $byType = $configured->groupBy('approval_type'); @endphp
        <div class="bg-white rounded-xl shadow overflow-hidden" x-data="{ tab: '{{ $type ?: array_key_first($types) }}' }">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold flex items-center justify-between">
                <span>Configured Committees</span>
                <span class="text-xs font-normal bg-white/20 rounded-full px-3 py-1">{{ $configured->count() }} set</span>
            </div>
            {{-- Tab SEMUA approval type (badge 0 bila belum ada) --}}
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 pt-3">
                @foreach($types as $val => $label)
                    @php $cnt = optional($byType->get($val))->count() ?? 0; @endphp
                    <button type="button" @click="tab = '{{ $val }}'"
                            :class="tab === '{{ $val }}' ? 'border-red-700 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition">
                        {{ $label }}
                        <span :class="tab === '{{ $val }}' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'"
                              class="ml-1 inline-flex items-center justify-center min-w-5 px-1.5 py-0.5 text-xs rounded-full">{{ $cnt }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Panel per approval type (kosong → pesan) --}}
            @foreach($types as $val => $label)
                @php $sets = $byType->get($val) ?? collect(); @endphp
                <div x-show="tab === '{{ $val }}'" x-cloak>
                    @if($sets->isEmpty())
                        <div class="p-6 text-gray-400 text-sm">No committee for <b>{{ $label }}</b>. Use the form below to add one.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                    <tr>
                                        <th class="px-4 py-3">Business Unit</th>
                                        <th class="px-4 py-3">Unit</th>
                                        @if($val === 'project_proposal')<th class="px-4 py-3">Budget</th>@endif
                                        <th class="px-4 py-3">Layer</th>
                                        <th class="px-4 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($sets as $set)
                                        <tr class="align-top hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium text-gray-700">{{ $set['bu_name'] }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $set['dept_name'] }}</td>
                                            @if($val === 'project_proposal')<td class="px-4 py-3"><span class="inline-block bg-amber-50 text-amber-700 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap">{{ $set['budget_label'] ?? '—' }}</span></td>@endif
                                            <td class="px-4 py-3">
                                                {{-- Ringkas: badge L1..L3 + "+N"; hover → daftar lengkap (teleport agar tak terpotong) --}}
                                                <div x-data="{ open:false, x:0, y:0 }"
                                                     @mouseenter="const r=$el.getBoundingClientRect(); x=Math.round(r.left); y=Math.round(r.bottom+6); open=true"
                                                     @mouseleave="open=false"
                                                     class="inline-flex items-center gap-1 cursor-help">
                                                    @foreach($set['layers']->take(3) as $l)
                                                        <span class="inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 text-xs font-semibold px-1.5 py-0.5" title="{{ $l['name'] }}">L{{ $l['layer'] }}</span>
                                                    @endforeach
                                                    @if($set['layers']->count() > 3)
                                                        <span class="text-xs text-gray-400">+{{ $set['layers']->count() - 3 }}</span>
                                                    @endif
                                                    @if($set['layers']->isEmpty())
                                                        <span class="text-xs text-gray-400">—</span>
                                                    @endif

                                                    <template x-teleport="body">
                                                        <div x-show="open" x-cloak :style="'position:fixed;left:'+x+'px;top:'+y+'px;z-index:60;'"
                                                             class="rounded-lg shadow-lg border border-gray-200 bg-white py-2 px-3 text-xs">
                                                            <div class="font-semibold text-gray-500 mb-1">Committee per Layer</div>
                                                            @forelse($set['layers'] as $l)
                                                                <div class="flex items-center gap-2 py-0.5" style="white-space:nowrap;">
                                                                    <span class="inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 font-semibold" style="min-width:1.75rem;padding:.05rem .3rem;">L{{ $l['layer'] }}</span>
                                                                    <span class="text-gray-700">{{ $l['name'] ?: '—' }}</span>
                                                                </div>
                                                            @empty
                                                                <div class="text-gray-400">None</div>
                                                            @endforelse
                                                        </div>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                @php
                                                    // budget_min bisa 0 (falsy) → jangan pakai array_filter untuk min/max.
                                                    $editParams = array_filter(['approval_type' => $set['approval_type'], 'business_unit_id' => $set['business_unit_id'], 'department' => $set['dept_name_raw']]);
                                                    if ($set['budget_min'] !== null) { $editParams['budget_min'] = $set['budget_min']; }
                                                    if ($set['budget_max'] !== null) { $editParams['budget_max'] = $set['budget_max']; }
                                                @endphp
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="{{ route('admin.committee.form', $editParams) }}"
                                                       class="px-3 py-1.5 border border-red-700 text-red-700 rounded-lg text-xs font-semibold hover:bg-red-50">Edit</a>
                                                    <form method="POST" action="{{ route('admin.committee.destroy') }}">
                                                        @csrf @method('DELETE')
                                                        <input type="hidden" name="approval_type" value="{{ $set['approval_type'] }}">
                                                        <input type="hidden" name="business_unit_id" value="{{ $set['business_unit_id'] }}">
                                                        <input type="hidden" name="department_id" value="{{ $set['department_id'] }}">
                                                        @if($set['budget_min'] !== null)<input type="hidden" name="budget_min" value="{{ $set['budget_min'] }}">@endif
                                                        @if($set['budget_max'] !== null)<input type="hidden" name="budget_max" value="{{ $set['budget_max'] }}">@endif
                                                        <button data-confirm="Delete committee {{ $set['type_label'] }} — {{ $set['bu_name'] }} / {{ $set['dept_name'] }}?" data-confirm-title="Delete Committee" data-confirm-ok="Yes, Delete" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @endforeach
        </div>

    </div>

</x-app-layout>
