<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Activity Log</h2>
    </x-slot>

    <div class="p-6 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Activity Log</h1>
            <p class="text-gray-500">Audit trail: every data change (create / update / delete) with the fields that changed.</p>
        </div>

        {{-- Filter --}}
        <form method="GET" class="flex flex-wrap items-end gap-3 bg-white rounded-xl shadow p-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Subject</label>
                <select name="type" class="border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200">
                    <option value="">All</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" @selected($type === $t)>{{ class_basename($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Event</label>
                <select name="event" class="border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200">
                    <option value="">All</option>
                    @foreach(['created', 'updated', 'deleted'] as $e)
                        <option value="{{ $e }}" @selected($event === $e)>{{ ucfirst($e) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">Filter</button>
            @if($type !== '' || $event !== '')
                <a href="{{ route('admin.activity-logs.index') }}" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-100">Reset</a>
            @endif
        </form>

        {{-- List --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Changes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($logs as $log)
                            @php [$evLabel, $evClass] = $log->eventBadge(); @endphp
                            <tr class="hover:bg-gray-50 align-top">
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500"><x-datetime :value="$log->created_at" /></td>
                                <td class="px-4 py-3 text-xs">{{ $log->causer_name ?? $log->causer?->name ?? 'system' }}</td>
                                <td class="px-4 py-3"><span class="text-xs rounded-full px-2 py-1 {{ $evClass }}">{{ $evLabel }}</span></td>
                                <td class="px-4 py-3">
                                    <div class="text-xs font-semibold text-gray-700">{{ $log->subject_short }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->subject_label }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if(empty($log->changes))
                                        <span class="text-xs text-gray-400">—</span>
                                    @else
                                        <details>
                                            <summary class="text-xs text-red-700 cursor-pointer select-none">{{ count($log->changes) }} field</summary>
                                            <div class="mt-2 space-y-1">
                                                @foreach($log->changes as $field => $diff)
                                                    <div class="text-xs">
                                                        <span class="font-mono font-semibold text-gray-600">{{ $field }}</span>:
                                                        @if($log->event === 'updated')
                                                            <span class="text-gray-400 line-through">{{ \Illuminate\Support\Str::limit((string) data_get($diff, 'old'), 60) ?: '∅' }}</span>
                                                            <span class="text-gray-400">→</span>
                                                            <span class="text-gray-800">{{ \Illuminate\Support\Str::limit((string) data_get($diff, 'new'), 60) ?: '∅' }}</span>
                                                        @else
                                                            <span class="text-gray-800">{{ \Illuminate\Support\Str::limit((string) data_get($diff, 'new'), 60) ?: '∅' }}</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No activity recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $logs->links() }}</div>

    </div>

</x-app-layout>
