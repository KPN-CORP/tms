<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Search + sort seragam untuk halaman list (My Ideas, Review Ideas, Manage
 * Project, dst). Menempel di ATAS query yang sudah tersaring scope/akses —
 * tidak pernah membocorkan data di luar hak akses user.
 *
 * Pemakaian di controller:
 *
 *     $config = [
 *         'searchable'   => ['idea_id', 'idea_name', 'businessUnit.name'],
 *         'sortable'     => ['idea_id' => 'idea_id', 'status' => 'status', 'modified_at' => 'modified_at'],
 *         'default_sort' => 'modified_at',
 *         'default_dir'  => 'desc',
 *     ];
 *     $query = Idea::where(...)->visibleTo($user);           // scope dulu
 *     $this->applyListSearchSort($query, $request, $config); // search + sort
 *     $ideas = $query->paginate(15)->withQueryString();
 *     $sortState = $this->listSortState($request, $config);  // untuk <x-sortable-th>
 *
 * - `searchable` mendukung kolom relasi via dot-notation ("businessUnit.name")
 *   → diterjemahkan ke whereHas. Kolom lokal → LIKE biasa.
 * - `sortable` = [key publik => kolom DB]. Hanya key terdaftar yang boleh
 *   di-sort (whitelist, aman dari SQL injection). Sort hanya kolom lokal.
 */
trait HasListQuery
{
    protected function applyListSearchSort(Builder $query, Request $request, array $config): Builder
    {
        $term = trim((string) $request->get('q'));
        $searchable = $config['searchable'] ?? [];

        if ($term !== '' && ! empty($searchable)) {
            $query->where(function (Builder $sub) use ($term, $searchable) {
                foreach ($searchable as $col) {
                    if (str_contains($col, '.')) {
                        [$relation, $relCol] = explode('.', $col, 2);
                        $sub->orWhereHas($relation, fn (Builder $r) => $r->where($relCol, 'like', "%{$term}%"));
                    } else {
                        $sub->orWhere($col, 'like', "%{$term}%");
                    }
                }
            });
        }

        [$sort, $dir] = $this->resolveListSort($request, $config);
        $query->orderBy($config['sortable'][$sort], $dir);

        return $query;
    }

    /** Jumlah baris per halaman ("Show N entries") — whitelist. */
    protected function listPerPage(Request $request, int $default = 10): int
    {
        $perPage = (int) $request->integer('per_page', $default);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : $default;
    }

    /** State sort terpakai (key publik + arah) untuk header tabel. */
    protected function listSortState(Request $request, array $config): array
    {
        [$sort, $dir] = $this->resolveListSort($request, $config);

        return ['sort' => $sort, 'dir' => $dir];
    }

    /**
     * Paginasi manual untuk Collection — dipakai saat filter akhir dilakukan di
     * PHP (mis. Approved Ideas: filter committee layer terakhir). Search & sort
     * tetap diterapkan di level query sebelum koleksi difilter.
     */
    protected function paginateListCollection(Collection $items, Request $request, int $perPage = 15): LengthAwarePaginator
    {
        $page = max(1, (int) $request->get('page', 1));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /** Resolusi sort/dir dari request dengan fallback ke default (validasi whitelist). */
    private function resolveListSort(Request $request, array $config): array
    {
        $sortable = $config['sortable'] ?? [];

        $sort = (string) $request->get('sort');
        if (! array_key_exists($sort, $sortable)) {
            $sort = $config['default_sort'] ?? (array_key_first($sortable) ?? 'id');
        }

        $dir = strtolower((string) $request->get('dir'));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $config['default_dir'] ?? 'desc';
        }

        return [$sort, $dir];
    }
}
