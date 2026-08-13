<?php

namespace App\Http\Controllers;

use App\Models\KpnCompany;
use App\Models\KpnDepartment;
use App\Models\KpnEmployee;
use App\Models\KpnLocation;
use App\Models\User;
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

    /**
     * JSON daftar Unit untuk FILTER (?bu=Cement) — dari departments.department_name
     * (cascade via parent_company_id, buang null/'-'). Dipakai filter My Ideas.
     */
    public function unitNames(Request $request)
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

    /**
     * JSON user (akun) searchable untuk dropdown Assign User / Restrict Employee (?q=...).
     * Value = users.id (bukan employee_id) karena role_employees & model_has_roles memakai id user.
     */
    public function users(Request $request)
    {
        $q = trim((string) $request->get('q'));
        if ($q === '') {
            return response()->json([]);
        }

        return response()->json(
            User::query()
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('employee_id', 'like', "%{$q}%"))
                ->orderBy('name')
                ->limit(30)
                ->get(['id', 'name', 'email', 'employee_id'])
                ->map(fn ($u) => [
                    'id'    => $u->id,
                    'label' => trim($u->name . ($u->employee_id ? ' - ' . $u->employee_id : '')),
                ])
                ->all()
        );
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
