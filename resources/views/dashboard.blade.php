<x-app-layout>

    <x-slot name="header">
        Dashboard
    </x-slot>

    @php
        $user = Auth::user();
        $card = 'bg-white rounded-xl shadow p-4';
        $num  = 'text-3xl font-bold text-gray-800';
        $lbl  = 'text-sm text-gray-500 leading-snug mt-1';
        $fld  = 'w-full h-[38px] border border-gray-300 rounded-lg px-3 text-sm focus:ring focus:ring-red-200';
        // Filter aktif dipertahankan saat berpindah "Act as".
        $keep = array_filter([
            'bu'   => request('bu'),
            'unit' => request('unit'),
            'from' => request('from'),
            'to'   => request('to'),
        ]);
    @endphp

    <div class="p-6 space-y-5">

        {{-- Greeting + pemilih cakupan data --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold">Welcome, {{ $user->name }}</h2>
            </div>

            {{-- Act as — muncul bagi role yang diberi permission 'dashboard.act-as'
                 melalui Role Management. Tanpa izin itu cakupan selalu "Myself"
                 (dikunci juga di controller, bukan hanya disembunyikan di sini). --}}
            @if($canActAs)
                <div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative shrink-0">
                    <span class="block text-xs font-semibold text-gray-500 uppercase mb-1">Act as</span>
                    <button type="button" @click="open = ! open" @click.outside="open = false"
                            class="inline-flex items-center justify-between gap-3 min-w-[10rem] bg-white border rounded-lg px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">
                        <span>{{ $views[$as] }}</span>
                        <svg class="w-4 h-4 shrink-0 text-gray-500 transition-transform" :class="open ? 'rotate-180' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute right-0 z-20 mt-1 w-full min-w-[10rem] bg-white border rounded-lg shadow-lg overflow-hidden">
                        @foreach($views as $key => $label)
                            <a href="{{ route('dashboard', $keep + ['as' => $key]) }}"
                               class="block px-4 py-2 text-sm {{ $as === $key ? 'bg-red-50 text-red-700 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Filter (Dashboard Matrix): Business Unit & Unit dari Darwinbox (hcis),
             Period From/To dari TMS. Business Unit → Unit cascade via AJAX. --}}
        <form method="GET" class="bg-white rounded-xl shadow px-4 py-3">
            <input type="hidden" name="as" value="{{ $as }}">

            <div class="flex flex-wrap items-end gap-2.5">
                <div class="w-52">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Business Unit</label>
                    {{-- Ganti BU hanya mengosongkan pilihan Unit (agar tidak tertinggal
                         kombinasi yang tidak nyambung). Tidak auto-submit: filter baru
                         dijalankan ketika tombol Apply ditekan. --}}
                    <select name="bu" id="dash-bu" data-remote-options="{{ route('org.business-units') }}"
                            onchange="var u=document.getElementById('dash-unit'); if(u.tomselect){u.tomselect.clear(true);}else{u.value='';}"
                            class="{{ $fld }} appearance-none bg-white">
                        <option value=""></option>
                        @if(request('bu'))<option value="{{ request('bu') }}" selected>{{ request('bu') }}</option>@endif
                    </select>
                </div>

                <div class="w-52">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Unit</label>
                    {{-- Cascade via AJAX dari hcis sesuai Business Unit terpilih --}}
                    <select name="unit" id="dash-unit"
                            data-remote-parent="#dash-bu" data-remote-url="{{ route('org.unit-names') }}" data-selected="{{ request('unit') }}"
                            class="{{ $fld }} appearance-none bg-white">
                        <option value=""></option>
                        @if(request('unit'))<option value="{{ request('unit') }}" selected>{{ request('unit') }}</option>@endif
                    </select>
                </div>

                <div class="w-40">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Period From</label>
                    <input type="date" name="from" value="{{ request('from') }}" max="{{ request('to') }}" class="{{ $fld }}">
                </div>

                <div class="w-40">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Period To</label>
                    <input type="date" name="to" value="{{ request('to') }}" min="{{ request('from') }}" class="{{ $fld }}">
                </div>

                <button class="h-[38px] px-5 bg-red-700 text-white rounded-lg text-sm font-semibold hover:bg-red-800">Apply</button>

                @if($hasFilter)
                    <a href="{{ route('dashboard', ['as' => $as]) }}"
                       class="inline-flex items-center h-[38px] px-4 border rounded-lg text-sm hover:bg-gray-100">Reset</a>
                @endif
            </div>

            @error('from')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('to')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </form>

        {{-- Idea Dashboard (T-10) --}}
        <div>
            <h3 class="text-base font-semibold text-gray-700 mb-2">Idea Monitoring</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="{{ $card }}"><div class="{{ $num }}">{{ $idea['draft'] }}</div><div class="{{ $lbl }}">Total Idea Draft</div></div>
                <div class="{{ $card }}"><div class="{{ $num }}">{{ $idea['cumulative_submit'] }}</div><div class="{{ $lbl }}">Cumulative Total Submitted Ideas</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-blue-700">{{ $idea['in_submitted'] }}</div><div class="{{ $lbl }}">Total Idea in Submitted Status</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-green-700">{{ $idea['approved'] }}</div><div class="{{ $lbl }}">Total Idea Approved for Implementation</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-red-700">{{ $idea['projects_generated'] }}</div><div class="{{ $lbl }}">Cumulative Total Project Generated from Approved Idea</div></div>
            </div>
        </div>

        {{-- Project Dashboard (T-11) --}}
        <div>
            <h3 class="text-base font-semibold text-gray-700 mb-2">Project Monitoring</h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                <div class="{{ $card }}"><div class="{{ $num }} text-blue-700">{{ $project['in_submitted'] }}</div><div class="{{ $lbl }}">Total Project in Submitted Status</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-green-700">{{ $project['cumulative_appr'] }}</div><div class="{{ $lbl }}">Cumulative Total Approved Projects</div></div>
                <div class="{{ $card }}"><div class="{{ $num }}">{{ $project['ongoing'] }}</div><div class="{{ $lbl }}">Total Project in Ongoing Status</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-amber-600">{{ $project['delayed'] }}</div><div class="{{ $lbl }}">Total Projects in Delayed Status</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-green-700">{{ $project['completed'] }}</div><div class="{{ $lbl }}">Total Completed Projects</div></div>
                <div class="{{ $card }}"><div class="{{ $num }}">{{ $project['avg_improvement'] }}%</div><div class="{{ $lbl }}">Average % Improvement</div></div>
            </div>
        </div>

    </div>

</x-app-layout>
