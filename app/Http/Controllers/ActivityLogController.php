<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasListQuery;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Viewer audit trail (izin: audit.view) — §9.2.
 */
class ActivityLogController extends Controller
{
    use HasListQuery;

    /**
     * Semua kunci sort menunjuk kolom lokal activity_logs. Nama pelaku sengaja
     * memakai `causer_name` — snapshot saat pencatatan — bukan relasi `causer`
     * yang hidup di database hcis dan tidak bisa ikut ORDER BY lintas koneksi.
     */
    private const LIST = [
        'searchable'   => [],
        'sortable'     => [
            'created_at' => 'created_at',
            'causer'     => 'causer_name',
            'event'      => 'event',
            'subject'    => 'subject_label',
        ],
        'default_sort' => 'created_at',
        'default_dir'  => 'desc',
    ];

    public function index(Request $request)
    {
        $type  = (string) $request->get('type');
        $event = (string) $request->get('event');

        $query = ActivityLog::query()
            ->with('causer')
            ->when($type !== '', fn ($q) => $q->where('subject_type', $type))
            ->when($event !== '', fn ($q) => $q->where('event', $event));

        $this->applyListSearchSort($query, $request, self::LIST);

        $perPage = $this->listPerPage($request, 25);

        $logs = $query->paginate($perPage)->withQueryString();

        // Daftar tipe subject yang pernah tercatat (untuk dropdown filter).
        $types = ActivityLog::query()
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type');

        return Inertia::render('Admin/ActivityLog/Index', [
            'logs' => $logs->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toIso8601ZuluString(),
                // Nama pelaku di-snapshot saat pencatatan, jadi jejaknya tetap
                // terbaca walau orangnya hilang dari hcis.
                'causer' => $log->causer_name ?? $log->causer?->name ?? 'system',
                'event' => $log->event,
                'event_label' => $log->eventBadge()[0],
                'subject_short' => $log->subject_short,
                'subject_label' => $log->subject_label,
                'changes' => collect($log->changes ?? [])
                    ->map(fn ($diff, $field) => [
                        'field' => $field,
                        'old' => \Illuminate\Support\Str::limit((string) data_get($diff, 'old'), 60),
                        'new' => \Illuminate\Support\Str::limit((string) data_get($diff, 'new'), 60),
                    ])->values(),
            ]),

            'types' => $types->map(fn ($t) => [
                'value' => $t,
                'label' => class_basename((string) $t),
            ])->values(),

            'filters' => [
                'type'  => $type,
                'event' => $event,
                'per_page' => $perPage,
            ] + $this->listSortState($request, self::LIST),
            'urls' => ['index' => route('admin.activity-logs.index')],
        ]);
    }
}
