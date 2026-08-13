<?php

namespace App\Http\Controllers;

use App\Models\CommitteeAssignment;
use App\Models\SlaSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SLA Setting (izin: sla.manage) — CRUD seperti Project Category.
 * Boleh ada banyak SLA per jenis approval, dibedakan `status` (mis. 3 hari @ On Review,
 * 30 hari @ Submitted). Info-only untuk hitung On Time / Due Soon / Overdue.
 */
class SlaSettingController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.sla.index', [
            'settings'      => SlaSetting::orderBy('approval_type')->orderByRaw('status is null')->orderBy('status')->get(),
            'labels'        => CommitteeAssignment::TYPES,
            'typeOptions'   => CommitteeAssignment::TYPES,
            'statusOptions' => SlaSetting::STATUS_OPTIONS,
            'editing'       => SlaSetting::find($request->integer('edit')),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        SlaSetting::create($data + ['is_active' => true]);

        return redirect()->route('admin.sla.index')->with('success', 'SLA added.');
    }

    public function update(Request $request, SlaSetting $sla)
    {
        $data = $this->validated($request, $sla);
        $sla->update($data);

        return redirect()->route('admin.sla.index')->with('success', 'SLA updated.');
    }

    public function toggle(SlaSetting $sla)
    {
        $sla->update(['is_active' => ! $sla->is_active]);

        return back()->with('success', $sla->is_active ? 'SLA restored.' : 'SLA archived.');
    }

    public function destroy(SlaSetting $sla)
    {
        $label = ($sla->typeLabel()) . ($sla->status ? ' — ' . $sla->statusLabel() : '');
        $sla->delete();

        return redirect()->route('admin.sla.index')->with('success', "SLA \"{$label}\" deleted.");
    }

    private function validated(Request $request, ?SlaSetting $sla = null): array
    {
        return $request->validate([
            'approval_type' => [
                'required',
                Rule::in(array_keys(CommitteeAssignment::TYPES)),
                // Kombinasi (approval_type + status) tidak boleh duplikat.
                Rule::unique('sla_settings')
                    ->where(fn ($q) => $q->where('status', $request->input('status')))
                    ->ignore($sla?->id),
            ],
            'days'   => ['required', 'integer', 'min:1', 'max:365'],
            'status' => ['required', Rule::in(array_keys(SlaSetting::STATUS_OPTIONS))],
        ], [
            'approval_type.unique' => 'SLA untuk jenis & status ini sudah ada.',
        ]);
    }
}
