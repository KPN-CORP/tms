<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RoleSelectionController;

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
    | Role Selection
    |--------------------------------------------------------------------------
    */

    Route::get('/select-role', [RoleSelectionController::class, 'index'])
        ->name('select-role');

    Route::get('/go-admin', [RoleSelectionController::class, 'admin'])
        ->name('go.admin');

    Route::get('/go-committee', [RoleSelectionController::class, 'committee'])
        ->name('go.committee');

    Route::get('/go-employee', [RoleSelectionController::class, 'employee'])
        ->name('go.employee');

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Role Management
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('permission:manage-role')
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

            /*
            |--------------------------------------------------------------------------
            | Assign User
            |--------------------------------------------------------------------------
            */

            Route::get('/roles/{role}/assign-users', [RoleController::class, 'assignUser'])
                ->name('roles.assign-user');

            Route::post('/roles/{role}/assign-users', [RoleController::class, 'saveAssignUser'])
                ->name('roles.assign-user.store');

        });

    /*
    |--------------------------------------------------------------------------
    | Menu Management
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('permission:manage-menu')
        ->group(function () {

            Route::get('/menus', [MenuController::class, 'index'])
                ->name('menus.index');

        });

});

require __DIR__.'/auth.php';