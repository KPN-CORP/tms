<?php

namespace App\Http\Middleware;

use App\Services\RBAC\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next, $permission)
    {
        if (!app(RbacService::class)->can($permission)) {
            abort(403);
        }

        return $next($request);
    }
}