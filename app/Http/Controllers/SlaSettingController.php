<?php

namespace App\Http\Controllers;

use App\Models\CommitteeAssignment;
use App\Models\SlaSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
        $editing = SlaSetting::find($request->integer('edit'));
        $labels = CommitteeAssignment::TYPES;

        return Inertia::render('Admin/Sla/Index', [
            'settings' => SlaSetting::orderBy('approval_type')
                ->orderByRaw('status is null')
                ->orderBy('status')
                ->get()
                ->map(fn (SlaSetting $s) => [
                    'id' => $s->id,
                    'approval_type' => $s->approval_type,
                    'approval_label' => $labels[$s->approval_type] ?? $s->approval_type,
                    'status' => $s->status,
                    'status_label' => SlaSetting::STATUS_OPTIONS[$s->status] ?? $s->status,
                    'days' => $s->days,
                    'is_active' => (bool) $s->is_active,
                    'urls' => [
                        'edit' => route('admin.sla.index', ['edit' => $s->id]),
                        'toggle' => route('admin.sla.toggle', $s),
                        'destroy' => route('admin.sla.destroy', $s),
                    ],
                ])->values(),

            'typeOptions' => collect($labels)
                ->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])
                ->values(),

            'statusOptions' => collect(SlaSetting::STATUS_OPTIONS)
                ->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])
                ->values(),

            'editing' => $editing ? [
                'id' => $editing->id,
                'approval_type' => $editing->approval_type,
                'status' => $editing->status,
                'days' => $editing->days,
            ] : null,

            'urls' => [
                'index' => route('admin.sla.index'),
                'store' => route('admin.sla.store'),
                'update' => $editing ? route('admin.sla.update', $editing) : null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        SlaSetting::create($data + ['is_active' => true]);

        return redirect()->route('admin.sla.index')->with('success', __('flash.sla.added'));
    }

    public function update(Request $request, SlaSetting $sla)
    {
        $data = $this->validated($request, $sla);
        $sla->update($data);

        return redirect()->route('admin.sla.index')->with('success', __('flash.sla.updated'));
    }

    public function toggle(SlaSetting $sla)
    {
        $sla->update(['is_active' => ! $sla->is_active]);

        return back()->with('success', __($sla->is_active ? 'flash.sla.restored' : 'flash.sla.archived'));
    }

    public function destroy(SlaSetting $sla)
    {
        $label = ($sla->typeLabel()) . ($sla->status ? ' — ' . $sla->statusLabel() : '');
        $sla->delete();

        return redirect()->route('admin.sla.index')->with('success', __('flash.sla.deleted', ['label' => $label]));
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
            'approval_type.unique' => 'An SLA for this type and status already exists.',
        ]);
    }
}
