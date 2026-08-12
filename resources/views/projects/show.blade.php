<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Project Detail</h2>
    </x-slot>

    @php
        $box   = 'w-full border rounded-lg px-4 py-2 bg-gray-50 text-gray-600';
        $inp   = 'border rounded-lg px-3 py-2 text-sm focus:ring focus:ring-red-200';
        $idea  = $project->idea;
        $usersById = $users->keyBy('id');
        $totalWeight = (float) $project->indicators->sum('weightage');
        [$stLabel, $stCls] = $project->statusBadge();
    @endphp

    <div class="p-6 space-y-6 max-w-5xl">

        <div class="flex items-start justify-between">
            <div>
                <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to Manage Project</a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $project->project_name }}</h1>
                <p class="font-mono text-red-700">{{ $project->project_id }}</p>
            </div>
            <div class="text-right space-y-1">
                <span class="inline-flex px-3 py-1 text-xs rounded-full {{ $stCls }}">{{ $stLabel }}</span>
                @if($canEdit)<div><span class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-700">Anda Project Leader — bisa mengedit</span></div>@endif
            </div>
        </div>

        @if(session('success'))<div class="rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">{{ session('error') }}</div>@endif

        {{-- Aksi proposal / completion --}}
        @if(($isLeader && $canEdit) || ($isSponsor && $project->status === 'submitted') || $isReviewer || $canSubmitCompletion)
            <div class="bg-white rounded-xl shadow p-6">
                @if($isLeader && $canEdit)
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">Lengkapi Impl. Plan & Success Indicators (total weight 100%), lalu submit.</div>
                        <form method="POST" action="{{ route('projects.submit', $project) }}">
                            @csrf<button
                                data-confirm="Proposal akan dikirim ke Sponsor untuk approval dan tidak bisa diedit selama menunggu keputusan. Lanjutkan submit?"
                                data-confirm-title="Submit Proposal?"
                                data-confirm-ok="Ya, Submit Proposal"
                                class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Submit Proposal</button>
                        </form>
                    </div>
                @elseif($isSponsor && $project->status === 'submitted')
                    <h3 class="text-lg font-semibold mb-3">Keputusan Sponsor</h3>
                    <div class="mb-3"><label class="block text-sm font-semibold text-gray-600 mb-1">Note</label>
                        <textarea form="approveForm" id="sponsor-decision-note" name="note" rows="2" class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200" placeholder="Catatan (wajib bila minta revisi)"></textarea></div>
                    <div class="flex gap-3">
                        <form id="approveForm" method="POST" action="{{ route('projects.sponsor.approve', $project) }}">@csrf<button class="px-6 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">Approve</button></form>
                        <form method="POST" action="{{ route('projects.sponsor.revision', $project) }}" onsubmit="this.note.value=document.getElementById('sponsor-decision-note').value">@csrf<input type="hidden" name="note"><button class="px-6 py-2 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700">Request Revision</button></form>
                    </div>
                @elseif($isReviewer)
                    <h3 class="text-lg font-semibold mb-3">Keputusan Committee {{ $project->status === 'completion_review' ? 'Completion' : 'Proposal' }} (Layer {{ $project->current_layer }})</h3>
                    <div class="mb-3"><label class="block text-sm font-semibold text-gray-600 mb-1">Note</label>
                        <textarea form="pApprove" id="committee-decision-note" name="note" rows="2" class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200" placeholder="Catatan (opsional)"></textarea></div>
                    <div class="flex gap-3">
                        <form id="pApprove" method="POST" action="{{ route('projects.review.approve', $project) }}">@csrf<button class="px-6 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">Approve</button></form>
                        <form method="POST" action="{{ route('projects.review.reject', $project) }}" onsubmit="this.note.value=document.getElementById('committee-decision-note').value">@csrf<input type="hidden" name="note"><button class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Reject</button></form>
                    </div>
                @elseif($canSubmitCompletion)
                    <h3 class="text-lg font-semibold mb-3">Completion Request</h3>
                    <p class="text-sm text-gray-500 mb-3">Project sudah Approved. Isi ringkasan lalu ajukan penyelesaian (butuh min. 1 Success Indicator).</p>
                    <form method="POST" action="{{ route('projects.completion.submit', $project) }}" class="space-y-3">
                        @csrf
                        <div><label class="block text-sm font-semibold text-gray-600 mb-1">Project Summary <span class="text-red-600">*</span></label>
                            <textarea name="project_summary" rows="4" required placeholder="Ringkasan hasil project (maks 20.000 karakter)"
                                      class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200">{{ old('project_summary', $project->project_summary) }}</textarea></div>
                        <div class="flex justify-end"><button
                                data-confirm="Completion request akan dikirim ke committee untuk direview. Pastikan ringkasan & indikator sudah benar. Lanjutkan submit?"
                                data-confirm-title="Submit Completion?"
                                data-confirm-ok="Ya, Submit Completion"
                                class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Submit Completion</button></div>
                    </form>
                @endif
            </div>
        @endif

        {{-- 1. Idea Detail --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold">Idea Detail</div>
            <div class="p-6 space-y-4">
                @if($idea)
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-semibold text-gray-600 mb-1">Idea ID</label><div class="{{ $box }} font-mono">{{ $idea->idea_id }}</div></div>
                        <div><label class="block text-sm font-semibold text-gray-600 mb-1">Submitter</label><div class="{{ $box }}">{{ optional($idea->user)->name }}</div></div>
                    </div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Idea Name</label><div class="{{ $box }}">{{ $idea->idea_name }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Problem / Root Cause</label><div class="{{ $box }} whitespace-pre-line">{{ $idea->problem }}</div></div>
                @else<p class="text-gray-400">Ide terkait tidak ditemukan.</p>@endif
            </div>
        </div>

        {{-- 2. Project Detail --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold">Project Detail</div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Category</label><div class="{{ $box }}">{{ $project->project_category }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Created</label><div class="{{ $box }}">{{ $project->created_at?->format('d M Y') }}</div></div>
                </div>
                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Project Scope</label><div class="{{ $box }} whitespace-pre-line">{{ $project->project_scope }}</div></div>
                <div><label class="block text-sm font-semibold text-gray-600 mb-1">Expected Outcome</label><div class="{{ $box }} whitespace-pre-line">{{ $project->expected_outcome }}</div></div>
                @if($project->project_summary)
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Project Summary (Completion)</label><div class="{{ $box }} whitespace-pre-line">{{ $project->project_summary }}</div></div>
                @endif
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Sponsor</label><div class="{{ $box }}">{{ optional($project->sponsor)->name }}</div></div>
                    <div><label class="block text-sm font-semibold text-gray-600 mb-1">Leader</label><div class="{{ $box }}">{{ optional($project->leader)->name }}</div></div>
                </div>
            </div>
        </div>

        {{-- 3. Implementation Detail --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold">Implementation Detail</div>
            <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr><th class="px-4 py-3">Activity</th><th class="px-4 py-3">Planning</th><th class="px-4 py-3">Actual</th><th class="px-4 py-3">PIC</th><th class="px-4 py-3">Status</th>@if($canEdit)<th></th>@endif</tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($project->implementationPlans as $plan)
                        <tr>
                            <td class="px-4 py-3">{{ $plan->activity }}</td>
                            <td class="px-4 py-3 text-xs">{{ optional($plan->planning_start)->format('d/m/y') ?? '-' }} &ndash; {{ optional($plan->planning_end)->format('d/m/y') ?? '-' }}@if($plan->planning_days) <span class="text-gray-400">({{ $plan->planning_days }}d)</span>@endif</td>
                            <td class="px-4 py-3 text-xs">
                                @if($canTrack)
                                    <form method="POST" action="{{ route('projects.implementation.actual', [$project, $plan]) }}" class="flex items-center gap-1">
                                        @csrf @method('PUT')
                                        <input type="date" name="actual_start" value="{{ optional($plan->actual_start)->format('Y-m-d') }}" class="border rounded px-1 py-0.5 text-xs">
                                        <input type="date" name="actual_end" value="{{ optional($plan->actual_end)->format('Y-m-d') }}" class="border rounded px-1 py-0.5 text-xs">
                                        <button class="text-red-700 font-semibold">Save</button>
                                    </form>
                                @else
                                    {{ optional($plan->actual_start)->format('d/m/y') ?? '-' }} &ndash; {{ optional($plan->actual_end)->format('d/m/y') ?? '-' }}@if($plan->actual_days) <span class="text-gray-400">({{ $plan->actual_days }}d)</span>@endif
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">{{ collect($plan->pic_user_ids)->map(fn($id) => optional($usersById[$id] ?? null)->name)->filter()->implode(', ') ?: '-' }}</td>
                            <td class="px-4 py-3"><span class="text-xs rounded-full px-2 py-1 bg-gray-100 text-gray-700">{{ $plan->status_label }}</span></td>
                            @if($canEdit)<td class="px-4 py-3 text-right"><form method="POST" action="{{ route('projects.implementation.destroy', [$project, $plan]) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="text-red-600 text-xs">Delete</button></form></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canEdit ? 6 : 5 }}" class="px-4 py-6 text-center text-gray-400">Belum ada activity.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if($canEdit)
                <form method="POST" action="{{ route('projects.implementation.store', $project) }}" class="p-4 border-t bg-gray-50 grid grid-cols-2 gap-2">
                    @csrf
                    <input name="activity" placeholder="Activity" required class="{{ $inp }} col-span-2">
                    <label class="text-xs text-gray-500">Planning Start<input name="planning_start" type="date" class="{{ $inp }} w-full"></label>
                    <label class="text-xs text-gray-500">Planning End<input name="planning_end" type="date" class="{{ $inp }} w-full"></label>
                    <label class="text-xs text-gray-500">Actual Start<input name="actual_start" type="date" class="{{ $inp }} w-full"></label>
                    <label class="text-xs text-gray-500">Actual End<input name="actual_end" type="date" class="{{ $inp }} w-full"></label>
                    <label class="text-xs text-gray-500 col-span-2">PIC (Ctrl/Cmd+klik utk banyak)
                        <select name="pic_user_ids[]" multiple class="{{ $inp }} w-full h-24">
                            @foreach($teamMembers as $tm)<option value="{{ $tm->id }}">{{ $tm->name }}</option>@endforeach
                        </select></label>
                    <div class="col-span-2 flex justify-end"><button class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800">Add Activity</button></div>
                </form>
            @endif
        </div>

        {{-- 4. Success Indicators --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 flex items-center justify-between">
                <span class="font-semibold">Success Indicators</span>
                <span class="text-xs rounded-full px-3 py-1 {{ abs($totalWeight-100) < 0.01 ? 'bg-green-500/30' : 'bg-yellow-500/40' }}">Total Weight: {{ $totalWeight }}%</span>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr><th class="px-4 py-3">Indicator</th><th class="px-4 py-3">Baseline</th><th class="px-4 py-3">Achievement</th><th class="px-4 py-3">UoM</th><th class="px-4 py-3">Weight%</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">% Improve</th>@if($canEdit)<th></th>@endif</tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($project->indicators as $ind)
                        <tr>
                            <td class="px-4 py-3">{{ $ind->indicator }}</td>
                            <td class="px-4 py-3">{{ $ind->baseline }}</td>
                            <td class="px-4 py-3">{{ $ind->achievement }}</td>
                            <td class="px-4 py-3">{{ $ind->uom }}</td>
                            <td class="px-4 py-3">{{ $ind->weightage }}</td>
                            <td class="px-4 py-3 text-xs">{{ $ind->type }}</td>
                            <td class="px-4 py-3">{{ $ind->improvement !== null ? $ind->improvement.'%' : '-' }}</td>
                            @if($canEdit)<td class="px-4 py-3 text-right"><form method="POST" action="{{ route('projects.indicators.destroy', [$project, $ind]) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="text-red-600 text-xs">Delete</button></form></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canEdit ? 8 : 7 }}" class="px-4 py-6 text-center text-gray-400">Belum ada indicator.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if($canEdit)
                <form method="POST" action="{{ route('projects.indicators.store', $project) }}" class="p-4 border-t bg-gray-50 flex flex-wrap items-end gap-2">
                    @csrf
                    <input name="indicator" placeholder="Indicator" required class="{{ $inp }} flex-1 min-w-[140px]">
                    <input name="baseline" type="number" step="any" placeholder="Baseline" class="{{ $inp }} w-24">
                    <input name="achievement" type="number" step="any" placeholder="Achievement" class="{{ $inp }} w-28">
                    <input name="uom" placeholder="UoM" class="{{ $inp }} w-20">
                    <input name="weightage" type="number" step="any" placeholder="Weight%" class="{{ $inp }} w-24">
                    <select name="type" class="{{ $inp }}"><option value="">Type…</option>@foreach(\App\Models\ImplementationIndicator::TYPES as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select>
                    <button class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800">Add</button>
                </form>
                <p class="px-4 pb-4 text-xs text-gray-400">% Improvement dihitung otomatis dari Baseline &amp; Achievement sesuai Type. Total Weight harus 100% untuk submit.</p>
            @endif
        </div>

        {{-- 5. Budget --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold">Budget</div>
            <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr><th class="px-4 py-3">Item</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">UoM</th><th class="px-4 py-3">Unit Price</th><th class="px-4 py-3">Planned</th><th class="px-4 py-3">Actual (Qty × Price = Cost)</th>@if($canEdit)<th></th>@endif</tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($project->budgets as $b)
                        <tr>
                            <td class="px-4 py-3">{{ $b->item }}</td><td class="px-4 py-3">{{ $b->qty }}</td><td class="px-4 py-3">{{ $b->uom }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $b->unit_price) }}</td>
                            <td class="px-4 py-3">{{ number_format($b->planned_total) }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if($canTrack)
                                    <form method="POST" action="{{ route('projects.budgets.actual', [$project, $b]) }}" class="flex items-center gap-1">
                                        @csrf @method('PUT')
                                        <input type="number" step="any" name="actual_qty" value="{{ $b->actual_qty }}" placeholder="qty" class="border rounded px-1 py-0.5 w-16 text-xs">
                                        <input type="number" step="any" name="actual_price" value="{{ $b->actual_price }}" placeholder="price" class="border rounded px-1 py-0.5 w-20 text-xs">
                                        <button class="text-red-700 font-semibold">Save</button>
                                        <span class="text-gray-400">= {{ $b->actual_cost !== null ? number_format((float) $b->actual_cost) : '-' }}</span>
                                    </form>
                                @else
                                    {{ $b->actual_cost !== null ? number_format((float) $b->actual_cost) : '-' }}
                                @endif
                            </td>
                            @if($canEdit)<td class="px-4 py-3 text-right"><form method="POST" action="{{ route('projects.budgets.destroy', [$project, $b]) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="text-red-600 text-xs">Delete</button></form></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canEdit ? 7 : 6 }}" class="px-4 py-6 text-center text-gray-400">Belum ada budget.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if($canEdit)
                <form method="POST" action="{{ route('projects.budgets.store', $project) }}" class="p-4 border-t bg-gray-50 flex flex-wrap items-end gap-2">
                    @csrf
                    <input name="item" placeholder="Item" required class="{{ $inp }} flex-1 min-w-[160px]">
                    <input name="qty" type="number" step="any" placeholder="Qty" class="{{ $inp }} w-20">
                    <input name="uom" placeholder="UoM" class="{{ $inp }} w-20">
                    <input name="unit_price" type="number" step="any" placeholder="Unit Price" class="{{ $inp }} w-28">
                    <button class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800">Add</button>
                </form>
            @endif
        </div>

        {{-- 6. Team --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold">Team Members</div>
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Role in Project</th>@if($canEdit)<th></th>@endif</tr></thead>
                <tbody class="divide-y">
                    @forelse($project->members as $m)
                        <tr><td class="px-4 py-3">{{ optional($m->user)->name }}</td><td class="px-4 py-3">{{ $m->role }}</td>
                            @if($canEdit)<td class="px-4 py-3 text-right"><form method="POST" action="{{ route('projects.members.destroy', [$project, $m]) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="text-red-600 text-xs">Delete</button></form></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canEdit ? 3 : 2 }}" class="px-4 py-6 text-center text-gray-400">Belum ada member.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($canEdit)
                <form method="POST" action="{{ route('projects.members.store', $project) }}" class="p-4 border-t bg-gray-50 flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="user_id" required class="{{ $inp }} flex-1 min-w-[160px]"><option value="">Select member…</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                    <input name="role" placeholder="Role (mis. Member, Co-Leader)" required class="{{ $inp }} flex-1 min-w-[160px]">
                    <button class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800">Add</button>
                </form>
            @endif
        </div>

        {{-- Approval History — keputusan committee (approve/reject) + isi catatan/notes --}}
        @if($project->approvals->isNotEmpty())
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="bg-red-800 text-white px-6 py-3 font-semibold">Approval History</div>
                <div class="p-6">
                    @foreach($project->approvals as $a)
                        <div class="flex items-start gap-3 border-b py-2 last:border-0 text-sm">
                            <span class="text-xs rounded-full px-2 py-1 shrink-0 {{ $a->decision === 'approve' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                Layer {{ $a->layer }} · {{ ucfirst($a->decision) }}
                            </span>
                            <div>
                                <span class="font-semibold">{{ optional($a->user)->name }}</span>
                                <span class="text-gray-400">· {{ $a->created_at?->format('d M Y H:i') }}</span>
                                @if($a->note)<div class="text-gray-600 mt-0.5">{{ $a->note }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 7. Project Progress --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold">Project Progress</div>
            <div class="p-6">
                @forelse($project->statusLogs as $log)
                    <div class="flex items-start gap-3 border-b py-2 last:border-0 text-sm">
                        <span class="text-xs rounded-full px-2 py-1 bg-gray-100 text-gray-700">{{ $log->old_status ?? '—' }} &rarr; {{ $log->new_status }}</span>
                        <div><span class="font-semibold">{{ optional($log->changedBy)->name }}</span>
                            <span class="text-gray-400">· {{ $log->created_at?->format('d M Y H:i') }}</span>@if($log->remarks)<div class="text-gray-500">{{ $log->remarks }}</div>@endif</div>
                    </div>
                @empty
                    <p class="text-gray-400 text-sm">Belum ada perubahan status.</p>
                @endforelse
            </div>
        </div>

        {{-- Project Updates (perubahan saat berjalan) --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold">Project Updates</div>
            <div class="p-6 space-y-3">
                @php $ucls = ['pending'=>'bg-blue-100 text-blue-700','approved'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700','applied'=>'bg-gray-100 text-gray-700','cancelled'=>'bg-gray-200 text-gray-600']; @endphp
                @forelse($project->updates as $u)
                    <div class="border rounded-lg p-3 text-sm">
                        <div class="flex items-center justify-between">
                            <div><span class="font-semibold">{{ $updateTypes[$u->change_type] ?? $u->change_type }}</span>
                                <span class="text-gray-400">· {{ optional($u->requester)->name }} · {{ $u->created_at?->format('d M Y H:i') }}</span></div>
                            <span class="text-xs rounded-full px-2 py-1 {{ $ucls[$u->status] ?? 'bg-gray-100' }}">{{ ucfirst($u->status) }} · {{ ucfirst($u->approver_role) }}</span>
                        </div>
                        <div class="text-gray-600 mt-1">{{ $u->description }}</div>
                        @if($u->review_note)<div class="text-gray-400 mt-1">Note: {{ $u->review_note }}</div>@endif
                        @if($reviewableUpdateIds->contains($u->id))
                            <div class="flex gap-2 mt-2">
                                <form method="POST" action="{{ route('projects.updates.approve', [$project, $u]) }}">@csrf<button class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">Approve</button></form>
                                <form method="POST" action="{{ route('projects.updates.reject', [$project, $u]) }}">@csrf<button class="px-3 py-1 text-xs bg-red-700 text-white rounded hover:bg-red-800">Reject</button></form>
                            </div>
                        @elseif($isLeader && $u->status === 'approved')
                            <form method="POST" action="{{ route('projects.updates.apply', [$project, $u]) }}" class="mt-2" onsubmit="return confirm('Terapkan & tutup update ini?')">@csrf<button class="px-3 py-1 text-xs bg-red-700 text-white rounded hover:bg-red-800">Apply / Finish</button></form>
                        @endif
                    </div>
                @empty
                    <p class="text-gray-400 text-sm">Belum ada update request.</p>
                @endforelse

                @if($canRequestUpdate)
                    <form method="POST" action="{{ route('projects.updates.request', $project) }}" class="border-t pt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <select name="change_type" required class="{{ $inp }}">
                            <option value="">Jenis perubahan…</option>
                            @foreach($updateTypes as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                        </select>
                        <input name="description" required placeholder="Apa yang mau diubah?" class="{{ $inp }} flex-1 min-w-[200px]">
                        <button class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800">Request Update</button>
                    </form>
                    <p class="text-xs text-gray-400">Routing: Budget → Committee · Planning → Sponsor (Team) / Committee (Sponsor) · Team/General → Sponsor (Team) / auto (Sponsor). Setelah approved, Leader edit section lalu <b>Apply</b>.</p>
                @endif
            </div>
        </div>

        {{-- Cancel Project --}}
        @if($canCancel)
            <div class="bg-white rounded-xl shadow p-4 border border-red-200">
                <form method="POST" action="{{ route('projects.cancel', $project) }}" class="flex flex-wrap items-end gap-2" onsubmit="return confirm('Batalkan project ini? Tidak bisa di-reopen.')">
                    @csrf
                    <input name="reason" required placeholder="Alasan pembatalan (wajib)" class="{{ $inp }} flex-1 min-w-[240px]">
                    <button class="px-4 py-2 bg-red-700 text-white rounded-lg text-sm hover:bg-red-800">Cancel Project</button>
                </form>
            </div>
        @endif

        {{-- 8. Attachments --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-red-800 text-white px-6 py-3 font-semibold">Attachments</div>
            <div class="p-6 space-y-4">
                <div>
                    @forelse($project->attachments as $att)
                        <div class="flex items-center justify-between border-b py-2 last:border-0 text-sm">
                            <a href="{{ route('projects.attachments.download', [$project, $att]) }}" class="text-red-700 hover:underline">{{ $att->file_name }}</a>
                            <span class="flex items-center gap-3 text-gray-400">
                                <span>{{ $att->file_size ? number_format($att->file_size / 1024, 0) . ' KB' : '' }}</span>
                                <span>{{ optional($att->uploader)->name }}</span>
                                @if(auth()->id() === $att->uploaded_by || $isLeader || auth()->user()->hasRole('Super Admin'))
                                    <form method="POST" action="{{ route('projects.attachments.destroy', [$project, $att]) }}" onsubmit="return confirm('Hapus lampiran?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-400 text-sm">Belum ada attachment.</p>
                    @endforelse
                </div>

                @if($canUploadAttachment)
                    <form method="POST" action="{{ route('projects.attachments.store', $project) }}" enctype="multipart/form-data" class="flex items-center gap-3 pt-2 border-t">
                        @csrf
                        <input type="file" name="file" required class="text-sm">
                        <button class="px-4 py-1.5 bg-red-700 text-white rounded-lg text-sm font-semibold hover:bg-red-800">Upload</button>
                        @error('file')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </form>
                    <p class="text-xs text-gray-400">Maks 10 MB. - .pdf, .docx, .xlsx, .jpg, .jpeg, .png, .pptx</p>
                @endif
            </div>
        </div>

    </div>

</x-app-layout>
