<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Collection;

/**
 * Pengelompokan permission untuk tab di form Role.
 *
 * Grup diturunkan dari awalan nama permission sebelum titik ('idea.create' ->
 * 'idea'). Awalan yang belum dipetakan jatuh ke "Other", jadi permission baru
 * tetap muncul tanpa mengubah apa pun di sini.
 *
 * Dulu aturan ini hidup di dalam resources/views/admin/role/_form.blade.php;
 * dipindah ke PHP agar pengelompokannya punya satu tempat dan bisa dikirim ke
 * halaman Vue sebagai data.
 */
class PermissionGroups
{
    private const MAP = [
        'idea' => 'Idea',
        'proposal' => 'Proposal',
        'project' => 'Project',
        'project-category' => 'Project',
        'implementation' => 'Project',
        'completion' => 'Project',
        'success-indicator' => 'Project',
        'committee' => 'Committee',
        'backup-approver' => 'Committee',
        'override' => 'Committee',
        'budget' => 'Budget',
        'guideline' => 'Guideline',
        'audit' => 'Monitoring',
        'dashboard' => 'Monitoring',
        'history' => 'Monitoring',
        'report' => 'Monitoring',
        'role' => 'Administration',
        'user' => 'Administration',
        'team' => 'Administration',
        'job-level' => 'Administration',
        'sla' => 'Administration',
        'reminder' => 'Administration',
        'visibility' => 'Administration',
    ];

    private const ORDER = [
        'Idea', 'Proposal', 'Project', 'Committee', 'Budget',
        'Guideline', 'Monitoring', 'Administration', 'Other',
    ];

    /**
     * `name` ikut dikirim di samping `label`: halaman Vue menerjemahkan dari
     * nama permission ('idea.create'), sedangkan label hasil displayLabel()
     * tetap jadi cadangan -- permission baru tampil dalam bahasa Inggris, bukan
     * kosong. Pola yang sama dipakai menu sidebar (lihat SidebarBuilder).
     *
     * @param  Collection<int, Permission>  $permissions
     * @return array<int, array{label: string, permissions: array<int, array{id: int, name: string, label: string}>}>
     */
    public static function build(Collection $permissions): array
    {
        $grouped = $permissions->groupBy(
            fn (Permission $p) => self::MAP[explode('.', (string) $p->name)[0]] ?? 'Other'
        );

        // Grup yang dikenal tampil dalam urutan tetap; sisanya menyusul.
        $names = collect(self::ORDER)
            ->filter(fn (string $name) => $grouped->has($name))
            ->merge($grouped->keys()->reject(fn (string $name) => in_array($name, self::ORDER, true)))
            ->unique()
            ->values();

        return $names
            ->map(fn (string $name) => [
                'label' => $name,
                'permissions' => $grouped[$name]
                    ->map(fn (Permission $p) => [
                        'id' => $p->id,
                        'name' => (string) $p->name,
                        'label' => $p->displayLabel(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}
