<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CommitteeAssignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectCategoryController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RoleController;

Route::redirect('/', '/login');

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

    /*
    |--------------------------------------------------------------------------
    | Ideas — My Ideas / Create / Draft (izin: idea.create)
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:idea.create')->group(function () {
        Route::get('/ideas', [IdeaController::class, 'index'])->name('ideas.index');
        Route::get('/ideas/drafts', [IdeaController::class, 'drafts'])->name('ideas.drafts');
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
        Route::get('/review/ideas', [IdeaController::class, 'review'])->name('ideas.review');
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
        Route::get('/approved-ideas', [ProjectController::class, 'approvedIdeas'])->name('projects.approved-ideas');
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

});

require __DIR__.'/auth.php';
