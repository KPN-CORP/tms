<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Services\RBAC\RbacService;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {

            $rbac = app(RbacService::class);

            $view->with([
                'activeRole'   => $rbac->activeRole(),
                'sidebarMenus' => $rbac->menus(),
            ]);

        });

        Blade::if('permission', function ($permission) {

            return app(RbacService::class)
                ->can($permission);

        });
    }
}