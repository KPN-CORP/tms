<x-app-layout>

    <x-slot name="header">
        Dashboard
    </x-slot>

    @php
        $user = Auth::user();
        $card = 'bg-white rounded-xl shadow p-5';
        $num  = 'text-3xl font-bold text-gray-800';
        $lbl  = 'text-sm text-gray-500';
    @endphp

    <div class="p-6 space-y-8">

        {{-- Greeting --}}
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Selamat datang, {{ $user->name }}</h2>
                <p class="text-gray-500 mt-1">
                    @forelse($user->getRoleNames() as $roleName)
                        <span class="inline-flex px-3 py-1 text-xs rounded-full bg-red-100 text-red-700">{{ $roleName }}</span>
                    @empty
                        <span class="text-gray-400">no roles yet</span>
                    @endforelse
                </p>
            </div>
        </div>

        {{-- Idea Dashboard (T-10) --}}
        <div>
            <h3 class="text-lg font-semibold text-gray-700 mb-3">Idea Monitoring</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="{{ $card }}"><div class="{{ $num }}">{{ $idea['draft'] }}</div><div class="{{ $lbl }}">Total Idea Draft</div></div>
                <div class="{{ $card }}"><div class="{{ $num }}">{{ $idea['cumulative_submit'] }}</div><div class="{{ $lbl }}">Cumulative Submitted</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-blue-700">{{ $idea['in_submitted'] }}</div><div class="{{ $lbl }}">In Submitted Status</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-green-700">{{ $idea['approved'] }}</div><div class="{{ $lbl }}">Approved for Implementation</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-red-700">{{ $idea['projects_generated'] }}</div><div class="{{ $lbl }}">Project Generated</div></div>
            </div>
        </div>

        {{-- Project Dashboard (T-11) --}}
        <div>
            <h3 class="text-lg font-semibold text-gray-700 mb-3">Project Monitoring</h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="{{ $card }}"><div class="{{ $num }} text-blue-700">{{ $project['in_submitted'] }}</div><div class="{{ $lbl }}">In Submitted</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-green-700">{{ $project['cumulative_appr'] }}</div><div class="{{ $lbl }}">Cumulative Approved</div></div>
                <div class="{{ $card }}"><div class="{{ $num }}">{{ $project['ongoing'] }}</div><div class="{{ $lbl }}">Ongoing</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-amber-600">{{ $project['delayed'] }}</div><div class="{{ $lbl }}">Delayed</div></div>
                <div class="{{ $card }}"><div class="{{ $num }} text-green-700">{{ $project['completed'] }}</div><div class="{{ $lbl }}">Completed</div></div>
                <div class="{{ $card }}"><div class="{{ $num }}">{{ $project['avg_improvement'] }}%</div><div class="{{ $lbl }}">Avg % Improvement</div></div>
            </div>
        </div>

        <p class="text-xs text-gray-400">Catatan: angka saat ini agregat global. Penyaringan berdasarkan scope akses (BU/Unit).</p>

    </div>

</x-app-layout>
