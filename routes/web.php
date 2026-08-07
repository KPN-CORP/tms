<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CommitteeAssignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectCategoryController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GuidelineController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\SlaSettingController;
use App\Http\Controllers\Auth\SsoController;

Route::redirect('/', '/login');

Route::get("dbauth", [SsoController::class, "dbauth"]);

Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Org data (hcis) untuk dropdown cascade dari Business Unit
    Route::get('/org/departments', [\App\Http\Controllers\OrgController::class, 'departments'])->name('org.departments');
    Route::get('/org/locations', [\App\Http\Controllers\OrgController::class, 'locations'])->name('org.locations');
    Route::get('/org/companies', [\App\Http\Controllers\OrgController::class, 'companies'])->name('org.companies');
    Route::get('/org/employees', [\App\Http\Controllers\OrgController::class, 'employees'])->name('org.employees');

    /*
    |--------------------------------------------------------------------------
    | Ideas — My Ideas / Create / Draft (izin: idea.create)
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:idea.create')->group(function () {
        Route::get('/ideas', [IdeaController::class, 'index'])->name('ideas.index');
        Route::get('/ideas/create', [IdeaController::class, 'create'])->name('ideas.create');
        Route::post('/ideas', [IdeaController::class, 'store'])->name('ideas.store');
        Route::get('/ideas/{idea}', [IdeaController::class, 'show'])->name('ideas.show');
        Route::get('/ideas/{idea}/edit', [IdeaController::class, 'edit'])->name('ideas.edit');
        Route::put('/ideas/{idea}', [IdeaController::class, 'update'])->name('ideas.update');
        Route::delete('/ideas/{idea}', [IdeaController::class, 'destroy'])->name('ideas.destroy');
        Route::get('/ideas/{idea}/attachments/{attachment}', [IdeaController::class, 'downloadAttachment'])->name('ideas.attachments.download');
        Route::delete('/ideas/{idea}/attachments/{attachment}', [IdeaController::class, 'destroyAttachment'])->name('ideas.attachments.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Review Ideas — daftar ide tersaring scope role (izin: idea.review)
    |--------------------------------------------------------------------------
    */

    Route::middleware('committee.member:idea')->group(function () {
        Route::get('/task-box', [IdeaController::class, 'taskBox'])->name('ideas.taskbox');
        Route::get('/review/ideas/{idea}', [IdeaController::class, 'reviewShow'])->name('ideas.review.show');
        Route::post('/review/ideas/{idea}/approve', [IdeaController::class, 'approve'])->name('ideas.review.approve');
        Route::post('/review/ideas/{idea}/reject', [IdeaController::class, 'reject'])->name('ideas.review.reject');
    });

    
    /*
    |--------------------------------------------------------------------------
    | Committee Assignment (izin: committee.assign)
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->name('admin.')->middleware('permission:committee.assign')->group(function () {
        Route::get('/committee-assignments', [CommitteeAssignmentController::class, 'index'])->name('committee.index');
        Route::post('/committee-assignments', [CommitteeAssignmentController::class, 'store'])->name('committee.store');
        Route::delete('/committee-assignments', [CommitteeAssignmentController::class, 'destroy'])->name('committee.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Project Category master (izin: project-category.manage)
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->name('admin.')->middleware('permission:project-category.manage')->group(function () {
        Route::get('/project-categories', [ProjectCategoryController::class, 'index'])->name('project-categories.index');
        Route::post('/project-categories', [ProjectCategoryController::class, 'store'])->name('project-categories.store');
        Route::put('/project-categories/{category}', [ProjectCategoryController::class, 'update'])->name('project-categories.update');
        Route::post('/project-categories/{category}/toggle', [ProjectCategoryController::class, 'toggle'])->name('project-categories.toggle');
    });

    /*
    |--------------------------------------------------------------------------
    | Project Shell (committee layer terakhir: izin project.create-shell)
    |--------------------------------------------------------------------------
    */

    Route::middleware('committee.member:idea')->group(function () {
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Manage Project — akses row-level (submitter/member/leader/sponsor/committee)
    |--------------------------------------------------------------------------
    */

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');

    // Review project (proposal & completion — akses dicek via committee assignment)
    Route::get('/review/projects', [ProjectController::class, 'reviewQueue'])->name('projects.review');
    Route::post('/projects/{project}/review-approve', [ProjectController::class, 'reviewApprove'])->name('projects.review.approve');
    Route::post('/projects/{project}/review-reject', [ProjectController::class, 'reviewReject'])->name('projects.review.reject');
    Route::post('/projects/{project}/submit-completion', [ProjectController::class, 'submitCompletion'])->name('projects.completion.submit');

    // Project Update Request (perubahan saat berjalan) + Cancellation
    Route::post('/projects/{project}/updates', [ProjectController::class, 'requestUpdate'])->name('projects.updates.request');
    Route::post('/projects/{project}/updates/{update}/approve', [ProjectController::class, 'approveUpdate'])->name('projects.updates.approve');
    Route::post('/projects/{project}/updates/{update}/reject', [ProjectController::class, 'rejectUpdate'])->name('projects.updates.reject');
    Route::post('/projects/{project}/updates/{update}/apply', [ProjectController::class, 'applyUpdate'])->name('projects.updates.apply');
    Route::post('/projects/{project}/cancel', [ProjectController::class, 'cancelProject'])->name('projects.cancel');

    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    // Section proposal (hanya Project Leader project ybs — dicek di controller)
    Route::post('/projects/{project}/implementation', [ProjectController::class, 'storeImplementation'])->name('projects.implementation.store');
    Route::delete('/projects/{project}/implementation/{plan}', [ProjectController::class, 'destroyImplementation'])->name('projects.implementation.destroy');
    Route::put('/projects/{project}/implementation/{plan}/actual', [ProjectController::class, 'updateImplementationActual'])->name('projects.implementation.actual');
    Route::post('/projects/{project}/indicators', [ProjectController::class, 'storeIndicator'])->name('projects.indicators.store');
    Route::delete('/projects/{project}/indicators/{indicator}', [ProjectController::class, 'destroyIndicator'])->name('projects.indicators.destroy');
    Route::post('/projects/{project}/budgets', [ProjectController::class, 'storeBudget'])->name('projects.budgets.store');
    Route::delete('/projects/{project}/budgets/{budget}', [ProjectController::class, 'destroyBudget'])->name('projects.budgets.destroy');
    Route::put('/projects/{project}/budgets/{budget}/actual', [ProjectController::class, 'updateBudgetActual'])->name('projects.budgets.actual');
    Route::post('/projects/{project}/members', [ProjectController::class, 'storeMember'])->name('projects.members.store');
    Route::delete('/projects/{project}/members/{member}', [ProjectController::class, 'destroyMember'])->name('projects.members.destroy');
    Route::post('/projects/{project}/attachments', [ProjectController::class, 'storeAttachment'])->name('projects.attachments.store');
    Route::get('/projects/{project}/attachments/{attachment}', [ProjectController::class, 'downloadAttachment'])->name('projects.attachments.download');
    Route::delete('/projects/{project}/attachments/{attachment}', [ProjectController::class, 'destroyAttachment'])->name('projects.attachments.destroy');

    // Submit proposal (Leader) & keputusan Sponsor
    Route::post('/projects/{project}/submit', [ProjectController::class, 'submitProposal'])->name('projects.submit');
    Route::post('/projects/{project}/sponsor-approve', [ProjectController::class, 'sponsorApprove'])->name('projects.sponsor.approve');
    Route::post('/projects/{project}/sponsor-revision', [ProjectController::class, 'sponsorRevision'])->name('projects.sponsor.revision');

    /*
    |--------------------------------------------------------------------------
    | Role Management (izin: role.manage)
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('permission:role.manage')
        ->group(function () {

            Route::get('/roles', [RoleController::class, 'manageRole'])
                ->name('roles.index');

            Route::get('/roles/create', [RoleController::class, 'createRole'])
                ->name('roles.create');

            Route::post('/roles', [RoleController::class, 'saveRole'])
                ->name('roles.store');

            Route::get('/roles/{role}/edit', [RoleController::class, 'editRole'])
                ->name('roles.edit');

            Route::put('/roles/{role}', [RoleController::class, 'updateRole'])
                ->name('roles.update');

            Route::delete('/roles/{role}', [RoleController::class, 'deleteRole'])
                ->name('roles.destroy');

            Route::get('/roles/{role}/assign-users', [RoleController::class, 'assignUser'])
                ->name('roles.assign-user');

            Route::post('/roles/{role}/assign-users', [RoleController::class, 'saveAssignUser'])
                ->name('roles.assign-user.store');

        });

    /*
    |--------------------------------------------------------------------------
    | User Management (izin: user.manage) — §9.5
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->name('admin.')->middleware('permission:user.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Guideline — pustaka dokumen (§9.7)
    | Lihat: guideline.view / guideline.upload. Kelola: guideline.upload.
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:guideline.view|guideline.upload')->group(function () {
        Route::get('/guidelines', [GuidelineController::class, 'index'])->name('guidelines.index');
        Route::get('/guidelines/{guideline}/download', [GuidelineController::class, 'download'])->name('guidelines.download');
    });

    Route::middleware('permission:guideline.upload')->group(function () {
        Route::post('/guidelines', [GuidelineController::class, 'store'])->name('guidelines.store');
        Route::post('/guidelines/{guideline}/toggle', [GuidelineController::class, 'toggle'])->name('guidelines.toggle');
        Route::delete('/guidelines/{guideline}', [GuidelineController::class, 'destroy'])->name('guidelines.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Activity Log — audit trail (izin: audit.view) — §9.2
    | DI-HIDE SEMENTARA: route dinonaktifkan agar fitur tidak bisa diakses.
    | Pencatatan audit (trait LogsActivity) tetap berjalan di background.
    | Uncomment blok di bawah untuk mengaktifkan kembali.
    |--------------------------------------------------------------------------
    */

    // Route::prefix('admin')->name('admin.')->middleware('permission:audit.view')->group(function () {
    //     Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    // });

    /*
    |--------------------------------------------------------------------------
    | SLA Setting (izin: sla.manage)
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->name('admin.')->middleware('permission:sla.manage')->group(function () {
        Route::get('/sla', [SlaSettingController::class, 'index'])->name('sla.index');
        Route::put('/sla', [SlaSettingController::class, 'update'])->name('sla.update');
    });

});

require __DIR__.'/auth.php';
