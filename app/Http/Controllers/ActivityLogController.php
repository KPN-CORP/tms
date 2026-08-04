<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Viewer audit trail (izin: audit.view) — §9.2.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $type  = (string) $request->get('type');
        $event = (string) $request->get('event');

        $logs = ActivityLog::query()
            ->with('causer')
            ->when($type !== '', fn ($q) => $q->where('subject_type', $type))
            ->when($event !== '', fn ($q) => $q->where('event', $event))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        // Daftar tipe subject yang pernah tercatat (untuk dropdown filter).
        $types = ActivityLog::query()
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type');

        return view('admin.activity-log.index', [
            'logs'  => $logs,
            'types' => $types,
            'type'  => $type,
            'event' => $event,
        ]);
    }
}
