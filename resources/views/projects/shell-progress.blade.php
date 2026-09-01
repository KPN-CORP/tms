<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Project Shell Progress</h2>
    </x-slot>

    @php
        $box   = 'w-full border rounded-lg px-4 py-2 bg-gray-50 text-gray-600';
        $secBtn = 'w-full bg-red-800 text-white px-6 py-3 font-semibold flex items-center justify-between';
        [$stLabel, $stCls] = $project->statusBadge();

        // Ikon kecil per jenis activity agar linimasa mudah dipindai.
        $kindDot = [
            'status' => 'bg-red-500',
            'update' => 'bg-blue-500',
            'plan'   => 'bg-green-500',
        ];
        $kindLabel = [
            'status' => 'Status',
            'update' => 'Update',
            'plan'   => 'Implementation',
        ];
    @endphp

    {{-- Lebar & posisi mengikuti Project Detail. --}}
    <div class="p-6 space-y-6 mx-auto w-full max-w-7xl">

        <div class="flex items-start justify-between">
            <div>
                <a href="{{ route('projects.shell') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to Project Shell</a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $project->project_name }}</h1>
                <p class="font-mono text-red-700">{{ $project->project_id }}</p>
            </div>
            <div class="text-right space-y-1">
                <span class="inline-flex px-3 py-1 text-xs rounded-full {{ $stCls }}">{{ $stLabel }}</span>
                <div>
                    <a href="{{ route('projects.show', $project) }}"
                       class="inline-flex px-4 py-1 text-sm border border-red-700 text-red-700 rounded-lg hover:bg-red-50">Open full detail</a>
                </div>
            </div>
        </div>

        @if(session('success'))<div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">{{ session('error') }}</div>@endif
        @if($errors->any())
            <ul class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm list-disc list-inside">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        @endif

        @if($project->status === 'cancelled' && $project->cancellation())
            @php $c = $project->cancellation(); @endphp
            <div class="rounded-xl bg-gray-100 border border-gray-300 px-4 py-3">
                <p class="text-sm font-semibold text-gray-700">Project cancelled</p>
                <p class="text-sm text-gray-600 mt-1 whitespace-pre-line">{{ $project->cancellationReason() ?: '—' }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    by {{ optional($c->changedBy)->name ?? 'Unknown' }} · <x-datetime :value="$c->created_at" />
                </p>
            </div>
        @endif

        {{-- 1. Project Progress Summary --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="{{ $secBtn }}"><span>Project Progress Summary</span></div>
            <div class="p-6 space-y-4">

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="border rounded-lg px-4 py-3">
                        <div class="text-xs text-gray-400 uppercase">Implementation</div>
                        <div class="text-2xl font-bold text-gray-800 mt-1">
                            {{ $summary['planPercent'] === null ? '—' : $summary['planPercent'] . '%' }}
                        </div>
                        <div class="text-xs text-gray-500">{{ $summary['planDone'] }} of {{ $summary['planTotal'] }} activities done</div>
                    </div>
                    <div class="border rounded-lg px-4 py-3">
                        <div class="text-xs text-gray-400 uppercase">Indicators</div>
                        <div class="text-2xl font-bold text-gray-800 mt-1">
                            {{ $summary['indicatorPercent'] === null ? '—' : $summary['indicatorPercent'] . '%' }}
                        </div>
                        <div class="text-xs text-gray-500">weight {{ rtrim(rtrim(number_format($summary['weightDone'], 2, '.', ''), '0'), '.') }} of {{ rtrim(rtrim(number_format($summary['weightTotal'], 2, '.', ''), '0'), '.') }}</div>
                    </div>
                    <div class="border rounded-lg px-4 py-3">
                        <div class="text-xs text-gray-400 uppercase">Team Members</div>
                        <div class="text-2xl font-bold text-gray-800 mt-1">{{ $summary['memberCount'] }}</div>
                        <div class="text-xs text-gray-500">registered in this project</div>
                    </div>
                    <div class="border rounded-lg px-4 py-3">
                        <div class="text-xs text-gray-400 uppercase">Last Status Change</div>
                        <div class="text-sm font-semibold text-gray-800 mt-2">
                            <x-datetime :value="$summary['lastActivity']" fallback="—" />
                        </div>
                    </div>
                </div>

                {{-- Info project terbaru --}}
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Category</label><div class="{{ $box }}">{{ $project->project_category }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Status</label><div class="{{ $box }}">{{ $stLabel }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Leader</label><div class="{{ $box }}">{{ optional($project->leader)->name ?? '-' }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Sponsor</label><div class="{{ $box }}">{{ optional($project->sponsor)->name ?? '-' }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Origin Idea</label><div class="{{ $box }} font-mono">{{ optional($project->idea)->idea_id ?? '-' }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Shell Created</label><div class="{{ $box }}"><x-datetime :value="$project->created_at" /></div></div>
                </div>

                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Project Scope</label><div class="{{ $box }} whitespace-pre-line">{{ $project->project_scope }}</div></div>
                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Expected Outcome</label><div class="{{ $box }} whitespace-pre-line">{{ $project->expected_outcome }}</div></div>
            </div>
        </div>

        {{-- 2. Activities --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="{{ $secBtn }}"><span>Activities</span></div>
            <div class="p-6">
                @forelse($activities as $a)
                    <div class="flex gap-3 pb-4 last:pb-0 relative">
                        {{-- Garis linimasa --}}
                        <div class="flex flex-col items-center shrink-0">
                            <span class="w-2.5 h-2.5 rounded-full mt-1.5 {{ $kindDot[$a['kind']] ?? 'bg-gray-400' }}"></span>
                            @unless($loop->last)<span class="w-px flex-1 bg-gray-200 mt-1"></span>@endunless
                        </div>
                        <div class="min-w-0 flex-1 border-b last:border-b-0 pb-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-gray-400 uppercase">{{ $kindLabel[$a['kind']] ?? $a['kind'] }}</span>
                                <span class="text-xs text-gray-400"><x-datetime :value="$a['at']" /></span>
                            </div>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $a['title'] }}</p>
                            @if(filled($a['note']))
                                <p class="text-sm text-gray-600 mt-0.5 whitespace-pre-line">{{ $a['note'] }}</p>
                            @endif
                            @if($a['actor'])
                                <p class="text-xs text-gray-400 mt-0.5">by {{ $a['actor'] }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-gray-400 text-center py-6">No activity recorded yet.</p>
                @endforelse
            </div>
        </div>

        {{-- 3. Cancellation --}}
        @if($canCancel)
            <div class="bg-white rounded-xl shadow overflow-hidden" x-data="{ cancelOpen: false }">
                <div class="{{ $secBtn }}"><span>Project Cancellation</span></div>
                <div class="p-6 flex items-center justify-between gap-4">
                    <p class="text-sm text-gray-600">Cancelling stops this project permanently. Pending update requests are dropped and the project cannot be reopened.</p>
                    <button type="button" @click="cancelOpen = true"
                            class="shrink-0 px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Cancel Project</button>
                </div>

                {{-- Dialog konfirmasi + alasan (wajib). --}}
                <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center" @keydown.escape.window="cancelOpen = false">
                    <div class="fixed inset-0 bg-black/40" @click="cancelOpen = false"></div>
                    <form method="POST" action="{{ route('projects.cancel', $project) }}"
                          class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" x-transition.opacity>
                        @csrf
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 w-10 h-10 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-xl">!</div>
                            <div class="min-w-0">
                                <h3 class="text-lg font-semibold text-gray-800">Cancel Project</h3>
                                <p class="text-sm text-gray-600 mt-1">Provide a cancellation reason. A cancelled project cannot be reopened.</p>
                            </div>
                        </div>
                        <textarea name="reason" rows="4" required maxlength="2000"
                                  placeholder="Reason for cancelling this project"
                                  class="mt-4 w-full border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200"></textarea>
                        <div class="flex justify-end gap-3 mt-4">
                            <button type="button" @click="cancelOpen = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Keep Project</button>
                            <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Yes, Cancel Project</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </div>

</x-app-layout>
