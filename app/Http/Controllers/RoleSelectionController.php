<?php

namespace App\Http\Controllers;

class RoleSelectionController extends Controller
{
    public function index()
    {
        return view('role.select-role');
    }

    public function employee()
    {
        session(['active_role' => 'employee']);

        return redirect()->route('dashboard');
    }

    public function committee()
    {
        session(['active_role' => 'committee']);

        return redirect()->route('dashboard');
    }

    public function admin()
    {
        session(['active_role' => 'admin']);

        return redirect()->route('dashboard');
    }
}

