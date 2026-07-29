<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Source of truth RBAC (Spatie).
 *
 * Semua role global + permission-nya (dot-notation).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan cache permission Spatie
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rolePermissions = [
            'Employee' => [
                'dashboard.view',
                'guideline.view',
                'idea.create',
                'idea.submit',
                'history.view',
            ],
            'Committee' => [
                'idea.review',
                'idea.approve',
                'idea.reject',
                'project.create-shell',
                'visibility.manage',
            ],
            'Project Leader' => [
                'proposal.edit',
                'proposal.submit',
                'success-indicator.manage',
                'budget.manage',
                'implementation.manage',
                'team.manage',
            ],
            'Project Sponsor' => [
                'proposal.approve',
                'proposal.revision',
                'completion.approve',
            ],
            'Project Team' => [
                'project.update',
                'budget.actual',
                'completion.submit',
            ],
            'Admin' => [
                'user.manage',
                'committee.assign',
                'sla.manage',
                'reminder.manage',
                'visibility.manage',
                'project.bulk-upload',
            ],
            'Super Admin' => [
                'role.manage',
                'user.manage',
                'guideline.upload',
                'project-category.manage',
                'backup-approver.manage',
                'job-level.manage',
                'override.role',
            ],
        ];

        // Izin dasar yang dimiliki SEMUA role (semua boleh submit ide).
        $baseline = ['idea.create', 'idea.submit', 'history.view'];

        // Buat semua permission unik (termasuk baseline)
        $allPermissions = collect($rolePermissions)->flatten()->merge($baseline)->unique();
        foreach ($allPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Buat role + assign permission (spesifik + baseline)
        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions(array_values(array_unique(array_merge($baseline, $permissions))));
        }

        // Assign user contoh (idempotent): [role, job_level grade]
        $assignments = [
            'sabi'   => ['Employee', 2],       // Senior Staff
            'rose'   => ['Committee', 5],       // Manager
            'lisa'   => ['Project Leader', 5],  // Manager
            'jennie' => ['Admin', 6],           // Senior Manager
            'kim'    => ['Super Admin', 8],     // Director
        ];
        foreach ($assignments as $userName => [$roleName, $jobLevel]) {
            $user = User::where('name', $userName)->first();
            if ($user) {
                $user->syncRoles([$roleName]);
                if ($user->job_level === null) {
                    $user->update(['job_level' => $jobLevel]);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
