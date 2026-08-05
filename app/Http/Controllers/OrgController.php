<?php

namespace App\Http\Controllers;

use App\Models\KpnCompany;
use App\Models\KpnDepartment;
use App\Models\KpnLocation;
use Illuminate\Http\Request;

/**
 * Data organisasi dari hcis (kpncorp) untuk dropdown cascade dari Business Unit.
 */
class OrgController extends Controller
{
    /** JSON daftar department_name untuk sebuah Business Unit (?bu=Cement). */
    public function departments(Request $request)
    {
        return $this->respond($request, fn ($bu) => KpnDepartment::namesFor($bu));
    }

    /** JSON daftar area (locations) untuk sebuah Business Unit. */
    public function locations(Request $request)
    {
        return $this->respond($request, fn ($bu) => KpnLocation::areasFor($bu));
    }

    /** JSON daftar contribution_level (companies) untuk sebuah Business Unit. */
    public function companies(Request $request)
    {
        return $this->respond($request, fn ($bu) => KpnCompany::contributionsFor($bu));
    }

    private function respond(Request $request, \Closure $resolver)
    {
        $bu = trim((string) $request->get('bu'));

        if ($bu === '') {
            return response()->json([]);
        }

        return response()->json($resolver($bu)->all());
    }
}
