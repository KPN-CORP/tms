<?php

namespace App\Http\Controllers;

use App\Models\Widget;
use App\Services\RBAC\RbacService;

class DashboardController extends Controller
{
    public function index(RbacService $rbac)
    {
        return view('dashboard', [
            'widgets' => $rbac->widgets(),
            'activeRole' => $rbac->activeRole(),
        ]);
    }
}