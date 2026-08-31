<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Review Idea</h2>
    </x-slot>

    @php
        $box = 'w-full border rounded-lg px-4 py-2 bg-gray-50 text-gray-600';
        $creator = $idea->user;
        $creatorEmp = \App\Models\KpnEmployee::forEmail(optional($creator)->email);
    @endphp

    <div class="p-6 space-y-6 max-w-5xl">

        <div>
            <a href="{{ route('ideas.taskbox') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to Task Box</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Idea: {{ $idea->idea_name }}</h1>
        </div>

        {{-- Idea Detail --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 flex items-center justify-between">
                <span class="font-semibold">Idea Detail</span>
                <div class="flex items-center gap-2 text-xs">
                    <span class="bg-white/20 rounded-full px-3 py-1">Layer {{ $idea->current_layer }}</span>
                    <span class="bg-white/20 rounded-full px-3 py-1">Status: {{ $idea->status }}</span>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <div class="grid grid-cols-4 gap-4">
                    <div class="border rounded-lg px-4 py-3"><div class="text-xs text-gray-400">EMPLOYEE ID</div><div class="font-semibold">{{ optional($creator)->employee_id ?? '-' }}</div></div>
                    <div class="border rounded-lg px-4 py-3"><div class="text-xs text-gray-400">FULL NAME</div><div class="font-semibold">{{ optional($creator)->name ?? '-' }}</div></div>
                    <div class="border rounded-lg px-4 py-3"><div class="text-xs text-gray-400">BUSINESS UNIT</div><div class="font-semibold">{{ ($creatorEmp?->businessUnitName()) ?? optional(optional($creator)->businessUnit)->name ?? '-' }}</div></div>
                    <div class="border rounded-lg px-4 py-3"><div class="text-xs text-gray-400">DEPARTMENT</div><div class="font-semibold">{{ ($creatorEmp?->departmentName()) ?? optional(optional($creator)->department)->name ?? '-' }}</div></div>
                </div>

                <div class="flex items-center gap-4"><span class="text-sm font-semibold text-gray-600 w-28">Idea ID</span><span class="font-mono text-red-700">{{ $idea->idea_id }}</span></div>

                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Idea Name</label><div class="{{ $box }}">{{ $idea->idea_name }}</div></div>
                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Problem / Root Cause</label><div class="{{ $box }} whitespace-pre-line">{{ $idea->problem }}</div></div>
                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Detailed Idea Description</label><div class="{{ $box }} whitespace-pre-line">{{ $idea->description }}</div></div>
                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Expected Outcome</label><div class="{{ $box }} whitespace-pre-line">{{ $idea->expected_outcome }}</div></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Targeted Business Unit</label><div class="{{ $box }}">{{ $idea->business_unit_name ?? optional($idea->businessUnit)->name }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Targeted Unit</label><div class="{{ $box }}">{{ $idea->department_name ?? optional($idea->department)->name }}</div></div>
                </div>
                <div>
                    @include('ideas._attachments', ['idea' => $idea])
                </div>
            </div>
        </div>

        {{-- Approval history --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Approval History</h3>
            @forelse($idea->approvals as $a)
                <div class="flex items-start gap-3 border-b py-2 last:border-0">
                    <span class="text-xs rounded-full px-2 py-1 {{ $a->decision === 'approve' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        Layer {{ $a->layer }} · {{ ucfirst($a->decision) }}
                    </span>
                    <div class="text-sm">
                        <span class="font-semibold">{{ optional($a->user)->name }}</span>
                        <span class="text-gray-400">· {{ $a->created_at?->format('d M Y H:i') }}</span>
                        @if($a->note)<div class="text-gray-500">{{ $a->note }}</div>@endif
                    </div>
                </div>
            @empty
                <p class="text-gray-400 text-sm">No decision yet.</p>
            @endforelse
        </div>

        {{-- Aksi approve/reject (hanya reviewer layer aktif) --}}
        @if($canReview)
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Your Decision (Layer {{ $idea->current_layer }})</h3>
                <div class="mb-3">
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Note (optional)</label>
                    <textarea form="approveForm" id="idea-decision-note" name="note" rows="2" class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200"></textarea>
                </div>
                <div class="flex gap-3">
                    <form id="approveForm" method="POST" action="{{ route('ideas.review.approve', $idea) }}">
                        @csrf
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('ideas.review.reject', $idea) }}"
                          onsubmit="this.note.value=document.getElementById('idea-decision-note').value">
                        @csrf
                        <input type="hidden" name="note">
                        <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Reject</button>
                    </form>
                </div>
            </div>
        @else
            <div class="bg-white rounded-xl shadow p-4 text-sm text-gray-500">
                You are not the active-layer reviewer for this idea (view only).
            </div>
        @endif

    </div>

</x-app-layout>
