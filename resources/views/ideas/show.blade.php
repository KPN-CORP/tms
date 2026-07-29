<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Idea Detail</h2>
    </x-slot>

    @php
        // Info status: [judul banner, pesan, kelas border literal, label pill]
        $statusInfo = [
            'draft'           => ['Draft', 'Idea ini masih berupa draft.', 'border-gray-400', 'draft'],
            'submitted'       => ['Submitted', 'Your idea has been submitted and is waiting to be reviewed.', 'border-blue-400', 'submitted'],
            'review'          => ['Under Review', 'Your idea is currently being reviewed by the committee.', 'border-amber-400', 'on-review'],
            'approved'        => ['Approved', 'Your idea has been approved for implementation.', 'border-green-400', 'approved'],
            'rejected'        => ['Rejected', 'Your idea was not approved by the committee.', 'border-red-400', 'rejected'],
            'project_created' => ['Project Created', 'A project has been generated from your idea.', 'border-purple-400', 'project-created'],
        ];
        [$sTitle, $sMsg, $sBorder, $sLabel] = $statusInfo[$idea->status] ?? [$idea->status, '', 'border-gray-400', $idea->status];

        $creator = $idea->user;

        // read-only "input look"
        $box = 'w-full border rounded-lg px-4 py-2 bg-gray-50 text-gray-600';
    @endphp

    <div class="p-6 space-y-6 max-w-5xl">

        {{-- Back + Title --}}
        <div>
            <a href="{{ route('ideas.index') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to My Ideas</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Idea: {{ $idea->idea_name }}</h1>
        </div>

        {{-- Status banner --}}
        <div class="bg-white rounded-xl shadow px-6 py-4 border-l-4 {{ $sBorder }}">
            <div class="font-semibold text-gray-800">{{ $sTitle }}</div>
            <div class="text-sm text-gray-500">{{ $sMsg }}</div>
        </div>

        {{-- Idea Detail card --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">

            <div class="bg-red-800 text-white px-6 py-3 flex items-center justify-between">
                <span class="font-semibold">Idea Detail</span>
                <span class="text-xs bg-white/20 rounded-full px-3 py-1">Status: {{ $sLabel }}</span>
            </div>

            <div class="p-6 space-y-6">

                {{-- Employee Information (pembuat ide) --}}
                <div class="grid grid-cols-4 gap-4">
                    <div class="border rounded-lg px-4 py-3">
                        <div class="text-xs text-gray-400">EMPLOYEE ID</div>
                        <div class="font-semibold">{{ optional($creator)->employee_id ?? '-' }}</div>
                    </div>
                    <div class="border rounded-lg px-4 py-3">
                        <div class="text-xs text-gray-400">FULL NAME</div>
                        <div class="font-semibold">{{ optional($creator)->name ?? '-' }}</div>
                    </div>
                    <div class="border rounded-lg px-4 py-3">
                        <div class="text-xs text-gray-400">BUSINESS UNIT</div>
                        <div class="font-semibold">{{ optional(optional($creator)->businessUnit)->name ?? '-' }}</div>
                    </div>
                    <div class="border rounded-lg px-4 py-3">
                        <div class="text-xs text-gray-400">DEPARTMENT</div>
                        <div class="font-semibold">{{ optional(optional($creator)->department)->name ?? '-' }}</div>
                    </div>
                </div>

                {{-- Idea ID + Submission Date --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-semibold text-gray-600 w-28">Idea ID</span>
                        <span class="font-mono text-red-700">{{ $idea->idea_id }}</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-semibold text-gray-600 w-32">Submission Date</span>
                        <span>{{ $idea->created_at?->format('j M Y') }}</span>
                    </div>
                </div>

                {{-- Fields (read-only) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Idea Name</label>
                    <div class="{{ $box }}">{{ $idea->idea_name }}</div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Problem / Root Cause</label>
                    <div class="{{ $box }} min-h-[64px] whitespace-pre-line">{{ $idea->problem }}</div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Detailed Description</label>
                    <div class="{{ $box }} min-h-[80px] whitespace-pre-line">{{ $idea->description }}</div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Expected Outcome</label>
                    <div class="{{ $box }} min-h-[64px] whitespace-pre-line">{{ $idea->expected_outcome }}</div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Targeted Business Unit</label>
                        <div class="{{ $box }}">{{ optional($idea->businessUnit)->name ?? '-' }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Targeted Department</label>
                        <div class="{{ $box }}">{{ optional($idea->department)->name ?? '-' }}</div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Supporting Documents</label>
                    @forelse($idea->attachments as $att)
                        <div class="flex items-center justify-between border-b py-2 last:border-0 text-sm">
                            <a href="{{ route('ideas.attachments.download', [$idea, $att]) }}" class="text-red-700 hover:underline">{{ $att->file_name }}</a>
                            <span class="text-gray-400">{{ $att->file_size ? number_format($att->file_size / 1024, 0) . ' KB' : '' }}</span>
                        </div>
                    @empty
                        <div class="{{ $box }} text-gray-400">Tidak ada lampiran.</div>
                    @endforelse
                </div>

            </div>
        </div>

    </div>

</x-app-layout>
