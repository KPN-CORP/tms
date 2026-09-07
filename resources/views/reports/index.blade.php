<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Report</h2>
    </x-slot>

    @php
        // Badge status: Idea memakai palet sendiri, Project memakai Project::STATUS_BADGES.
        $ideaBadge = [
            'draft'     => 'bg-gray-100 text-gray-700',
            'submitted' => 'bg-blue-100 text-blue-700',
            'review'    => 'bg-amber-100 text-amber-800',
            'approved'  => 'bg-green-100 text-green-700',
            'rejected'  => 'bg-red-100 text-red-700',
        ];
        $badgeClass = function ($status) use ($isIdea, $ideaBadge) {
            if ($isIdea) return $ideaBadge[$status] ?? 'bg-gray-100 text-gray-600';
            return \App\Models\Project::STATUS_BADGES[$status][1] ?? 'bg-gray-100 text-gray-600';
        };

        // Tab status dibangun dari status yang BENAR-BENAR ada pada hasil saat ini,
        // supaya tab kosong tidak memenuhi layar untuk laporan yang sempit.
        $tabDefs = ['all' => 'All'] + collect($statusMap)
            ->filter(fn ($label, $key) => $counts->has($key))
            ->all();

        $withQuery = fn (array $extra) => request()->url() . '?' . http_build_query(
            array_merge(request()->query(), $extra + ['page' => 1])
        );
    @endphp

    <div class="p-6 space-y-4 max-w-full mx-auto w-full">

        <form method="GET" class="space-y-4">
            {{-- Tab dipertahankan saat filter/search di-submit --}}
            <input type="hidden" name="tab" value="{{ $tab }}">

            {{-- ===== Baris atas: Select Report + Download ===== --}}
            <div class="bg-white rounded-xl shadow p-4">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="w-72">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Select Report:</label>
                        {{-- Ganti jenis laporan = muat ulang: kolom & tab ikut berubah,
                             jadi tab/halaman lama direset agar tidak menyisakan state asing. --}}
                        <select name="type" data-no-search
                                onchange="this.form.tab.value='all'; this.form.requestSubmit()"
                                class="w-full h-[38px] border border-gray-300 rounded-lg px-3 text-sm bg-white">
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <a href="{{ route('reports.download', request()->query()) }}"
                       class="inline-flex items-center gap-2 h-[38px] px-5 bg-red-700 text-white rounded-lg text-sm font-semibold hover:bg-red-800">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                        Download
                    </a>
                </div>

                {{-- ===== Filter: Search + Business Unit + Unit (sama seperti My Ideas) ===== --}}
                <div class="flex flex-wrap items-start gap-3 mt-4 pt-4 border-t border-gray-100">
                    <div class="w-64">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Search</label>
                        <input name="q" value="{{ request('q') }}"
                               placeholder="{{ $isIdea ? 'Idea ID, name, or problem…' : 'Project ID or name…' }}"
                               x-data x-on:input.debounce.500ms="$el.form.requestSubmit()"
                               class="w-full h-[38px] border border-gray-300 rounded-lg px-3 text-sm focus:ring focus:ring-red-200">
                    </div>

                    <div class="w-60">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Business Unit</label>
                        {{-- Ganti BU → reset Unit lalu submit (agar filter Unit lama tidak ikut) --}}
                        <select name="bu" id="filter-bu" data-remote-options="{{ route('org.business-units') }}"
                                onchange="var u=document.getElementById('filter-unit'); if(u.tomselect){u.tomselect.clear(true);}else{u.value='';} this.form.requestSubmit()"
                                class="w-full h-[38px] appearance-none border border-gray-300 rounded-lg px-3 text-sm bg-white">
                            <option value=""></option>
                            @if(request('bu'))<option value="{{ request('bu') }}" selected>{{ request('bu') }}</option>@endif
                        </select>
                    </div>

                    <div class="w-60">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Unit</label>
                        {{-- Cascade via AJAX dari hcis sesuai BU terpilih --}}
                        <select name="unit" id="filter-unit"
                                data-remote-parent="#filter-bu" data-remote-url="{{ route('org.unit-names') }}"
                                data-selected="{{ request('unit') }}" onchange="this.form.requestSubmit()"
                                class="w-full h-[38px] appearance-none border border-gray-300 rounded-lg px-3 text-sm bg-white">
                            <option value=""></option>
                            @if(request('unit'))<option value="{{ request('unit') }}" selected>{{ request('unit') }}</option>@endif
                        </select>
                    </div>

                    {{-- Tanpa tombol Apply/Reset: setiap filter langsung menyaring
                         sendiri — Search saat mengetik (debounce), BU & Unit saat
                         pilihannya berubah. --}}
                </div>
            </div>

            {{-- ===== Hasil ===== --}}
            <div class="bg-white rounded-xl shadow overflow-hidden">

                {{-- Tab status --}}
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 pt-3">
                    @foreach($tabDefs as $key => $label)
                        @php $aktif = $tab === $key; @endphp
                        <a href="{{ $withQuery(['tab' => $key]) }}"
                           class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition
                                  {{ $aktif ? 'border-red-700 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                            {{ $label }}
                            <span class="ml-1 inline-flex items-center justify-center min-w-5 px-1.5 py-0.5 text-xs rounded-full
                                         {{ $aktif ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $key === 'all' ? $counts->sum() : $counts->get($key, 0) }}
                            </span>
                        </a>
                    @endforeach
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                            <tr>
                                @foreach($columns as $col)
                                    @if(! empty($col['sort']))
                                        <x-sortable-th :column="$col['sort']" :sort="$sort" :dir="$dir" class="px-4 py-3">{{ $col['label'] }}</x-sortable-th>
                                    @else
                                        <th class="px-4 py-3">{{ $col['label'] }}</th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($rows as $row)
                                <tr class="hover:bg-gray-50">
                                    @foreach($columns as $col)
                                        @php $v = ($col['value'])($row); @endphp
                                        <td class="px-4 py-3 align-top">
                                            @if($col['label'] === 'Status')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $badgeClass($v) }}">
                                                    {{ $statusMap[$v] ?? $v }}
                                                </span>
                                            @elseif($v instanceof \DateTimeInterface)
                                                <x-datetime :value="$v" />
                                            @elseif(in_array($col['label'], ['Total Budget', 'Total Cost'], true))
                                                <span class="tabular-nums">{{ number_format((float) $v, 0, ',', '.') }}</span>
                                            @elseif($col['label'] === 'Idea ID' || $col['label'] === 'Project ID')
                                                <span class="font-mono text-red-700">{{ $v }}</span>
                                            @else
                                                {{ ($v === null || $v === '') ? '—' : $v }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columns) }}" class="px-4 py-10 text-center text-gray-400">
                                        No data matches the current filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-list-footer :paginator="$rows" :per-page="$perPage" />
            </div>
        </form>
    </div>

</x-app-layout>
