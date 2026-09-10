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

        {{-- Peringatan coverage committee: layer approval yang belum di-assign.
             Dihitung dari data setiap halaman dibuka, jadi hilang sendiri begitu
             assignment-nya dibuat — tidak perlu di-dismiss. --}}
        @if($coverage['total'] > 0)
            @php
                $assignUrl = fn ($row) => route('admin.committee.form', array_filter([
                    'approval_type'    => $row['type'],
                    'business_unit_id' => $row['bu_id'] ?? null,
                ]));
                $rowCls = 'flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white/70 border px-3 py-2';
            @endphp

            {{-- Posisi buka/tutup disimpan di localStorage (window.tmsSec, didefinisikan di
                 layout) sehingga kondisi terakhir dipertahankan saat halaman dibuka lagi.
                 Default terbuka bila user belum pernah menutupnya. --}}
            <div class="rounded-xl border border-amber-300 bg-amber-50 overflow-hidden"
                 x-data="{ open: window.tmsSec.get('admin.committee.coverage', true) }"
                 x-init="$watch('open', v => window.tmsSec.set('admin.committee.coverage', v))">
                <button type="button" @click="open = ! open"
                        class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left">
                    <span class="flex items-center gap-2 min-w-0">
                        <span class="shrink-0 w-6 h-6 rounded-full bg-amber-400 text-white flex items-center justify-center text-sm font-bold">!</span>
                        <span class="font-semibold text-amber-900">
                            Approval layers need attention
                            <span class="ml-1 font-normal text-amber-800">— {{ $coverage['total'] }} issue(s) found</span>
                        </span>
                    </span>
                    <svg class="w-4 h-4 shrink-0 text-amber-800 transition-transform" :class="open ? '' : 'rotate-180'"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 15l-6-6-6 6"/>
                    </svg>
                </button>

                <div x-show="open" x-cloak class="px-4 pb-4 space-y-4">

                    {{-- 1. Sedang tertahan: tidak ada penanggung jawab di layer aktif. --}}
                    @if($coverage['blocked']->isNotEmpty())
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-amber-900">
                                Waiting with no assignee
                                <span class="font-normal text-amber-800">— submissions stuck because nobody is assigned to the active layer</span>
                            </p>
                            @foreach($coverage['blocked'] as $row)
                                <div class="{{ $rowCls }} border-amber-200">
                                    <div class="min-w-0 text-sm">
                                        <span class="font-semibold text-gray-800">{{ $row['bu'] }}</span>
                                        <span class="text-gray-400">/</span>
                                        <span class="text-gray-700">{{ $row['unit'] }}</span>
                                        <span class="mx-1 text-gray-300">·</span>
                                        <span class="text-gray-600">{{ $row['type_label'] }}</span>
                                        <span class="mx-1 text-gray-300">·</span>
                                        <span class="font-semibold text-red-700">Layer {{ $row['layer'] }} unassigned</span>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            {{ $row['count'] }} item(s) waiting: {{ implode(', ', $row['labels']) }}{{ $row['count'] > count($row['labels']) ? ', …' : '' }}
                                            @if($row['oldest'])
                                                <span class="text-gray-400">· oldest {{ \Carbon\Carbon::parse($row['oldest'])->diffForHumans() }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ $assignUrl($row) }}"
                                       class="shrink-0 px-4 py-1.5 text-sm bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Assign</a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- 2. Chain berlubang: layer tengah kosong → sisa layer akan terlewat. --}}
                    @if($coverage['gaps']->isNotEmpty())
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-amber-900">
                                Gaps in the layer chain
                                <span class="font-normal text-amber-800">— approval jumps to the next layer number only, so a missing layer is skipped silently</span>
                            </p>
                            @foreach($coverage['gaps'] as $row)
                                <div class="{{ $rowCls }} border-amber-200">
                                    <div class="min-w-0 text-sm">
                                        <span class="font-semibold text-gray-800">{{ $row['bu'] }}</span>
                                        <span class="text-gray-400">/</span>
                                        <span class="text-gray-700">{{ $row['unit'] }}</span>
                                        <span class="mx-1 text-gray-300">·</span>
                                        <span class="text-gray-600">{{ $row['type_label'] }}</span>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            Chain runs from layer {{ $row['lowest'] }} to {{ $row['highest'] }}, but layer {{ implode(', ', $row['missing']) }} {{ count($row['missing']) > 1 ? 'are' : 'is' }} empty.
                                        </div>
                                    </div>
                                    <a href="{{ $assignUrl($row) }}"
                                       class="shrink-0 px-4 py-1.5 text-sm border border-red-700 text-red-700 rounded-lg font-semibold hover:bg-red-50">Fix chain</a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- 3. Belum tertahan, tapi completion akan lolos tanpa review. --}}
                    @if($coverage['risks']->isNotEmpty())
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-amber-900">
                                Completion would skip review
                                <span class="font-normal text-amber-800">— these units have running projects but no Project Completion committee, so completion is approved automatically</span>
                            </p>
                            @foreach($coverage['risks'] as $row)
                                <div class="{{ $rowCls }} border-amber-200">
                                    <div class="min-w-0 text-sm">
                                        <span class="font-semibold text-gray-800">{{ $row['bu'] }}</span>
                                        <span class="text-gray-400">/</span>
                                        <span class="text-gray-700">{{ $row['unit'] }}</span>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            {{ $row['count'] }} running project(s): {{ implode(', ', $row['labels']) }}{{ $row['count'] > count($row['labels']) ? ', …' : '' }}
                                        </div>
                                    </div>
                                    <a href="{{ $assignUrl($row) }}"
                                       class="shrink-0 px-4 py-1.5 text-sm border border-red-700 text-red-700 rounded-lg font-semibold hover:bg-red-50">Assign</a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>
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
                                        @if(\App\Models\CommitteeAssignment::usesBudgetRange($val))<th class="px-4 py-3">Budget</th>@endif
                                        <th class="px-4 py-3">Layer</th>
                                        <th class="px-4 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($sets as $set)
                                        <tr class="align-top hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium text-gray-700">{{ $set['bu_name'] }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $set['dept_name'] }}</td>
                                            @if(\App\Models\CommitteeAssignment::usesBudgetRange($val))<td class="px-4 py-3"><span class="inline-block bg-amber-50 text-amber-700 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap">{{ $set['budget_label'] ?? '—' }}</span></td>@endif
                                            <td class="px-4 py-3">
                                                {{-- Ringkas: badge L1..L3 + "+N"; hover → daftar lengkap (teleport agar tak terpotong) --}}
                                                <div x-data="{ open:false, x:0, y:0 }"
                                                     @mouseenter="const r=$el.getBoundingClientRect(); x=Math.round(r.left); y=Math.round(r.bottom+6); open=true"
                                                     @mouseleave="open=false"
                                                     class="inline-flex items-center gap-1 cursor-help">
                                                    @foreach($set['layers']->take(3) as $l)
                                                        {{-- Layer bawaan (Project Sponsor) dibedakan hanya lewat WARNA badge,
                                                             tanpa teks tambahan. --}}
                                                        <span class="inline-flex items-center justify-center rounded text-xs font-semibold px-1.5 py-0.5 {{ ($l['reserved'] ?? false) ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}"
                                                              title="{{ $l['name'] }}">L{{ $l['layer'] }}</span>
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
                                                                    <span class="inline-flex items-center justify-center rounded font-semibold {{ ($l['reserved'] ?? false) ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}" style="min-width:1.75rem;padding:.05rem .3rem;">L{{ $l['layer'] }}</span>
                                                                    <span class="text-gray-700">{{ $l['name'] ?: '—' }}</span>
                                                                </div>
                                                            @empty
                                                                <div class="text-gray-400">None</div>
                                                            @endforelse
                                                            <div class=" "
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
