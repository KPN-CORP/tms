<?php

namespace App\Http\Controllers;

use App\Models\CommitteeAssignment;
use App\Models\SlaSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SLA Setting (izin: sla.manage). Admin mengatur batas hari review per jenis
 * approval. Info-only (tanpa notifikasi/reminder).
 */
class SlaSettingController extends Controller
{
    /** Default hari per jenis saat baris belum ada. */
    private const DEFAULT_DAYS = [
        'idea'               => 7,
        'project_proposal'   => 14,
        'project_completion' => 14,
        'project_tracking'   => 7,
        'team_change'        => 7,
    ];

    public function index()
    {
        // Pastikan tiap jenis approval punya baris (idempotent).
        foreach (array_keys(CommitteeAssignment::TYPES) as $type) {
            SlaSetting::firstOrCreate(
                ['approval_type' => $type],
                ['days' => self::DEFAULT_DAYS[$type] ?? 7, 'is_active' => true]
            );
        }

        return view('admin.sla.index', [
            'settings'      => SlaSetting::orderBy('id')->get(),
            'labels'        => CommitteeAssignment::TYPES,
            'statusOptions' => SlaSetting::STATUS_OPTIONS,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'days'        => ['array'],
            'days.*'      => ['nullable', 'integer', 'min:1', 'max:365'],
            'status'      => ['array'],
            'status.*'    => ['nullable', 'string', Rule::in(array_keys(SlaSetting::STATUS_OPTIONS))],
            'active'      => ['array'],
        ]);

        foreach (SlaSetting::all() as $setting) {
            $setting->update([
                'days'      => $data['days'][$setting->approval_type] ?? $setting->days,
                'status'    => $data['status'][$setting->approval_type] ?? null,
                'is_active' => isset($data['active'][$setting->approval_type]),
            ]);
        }

        return redirect()
            ->route('admin.sla.index')
            ->with('success', 'SLA settings updated.');
    }
}
