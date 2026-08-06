<?php

namespace App\Http\Controllers;

use App\Models\KpnCompany;
use App\Models\KpnEmployee;
use App\Models\KpnLocation;
use Illuminate\Http\Request;

/**
 * Data organisasi dari hcis (kpncorp) untuk dropdown cascade dari Business Unit.
 */
class OrgController extends Controller
{
    /** JSON daftar Unit/Department untuk sebuah Business Unit (?bu=Cement) — dari employees.unit. */
    public function departments(Request $request)
    {
        return $this->respond($request, fn ($bu) => KpnEmployee::unitsFor($bu));
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

    /** JSON employee searchable (semua BU) untuk dropdown approver Committee (?q=...). */
    public function employees(Request $request)
    {
        return response()->json(
            KpnEmployee::search((string) $request->get('q'), 30)
                ->map(fn ($e) => [
                    'employee_id' => $e->employee_id,
                    'email'       => $e->email,
                    'label'       => $e->label(),
                ])
                ->all()
        );
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
