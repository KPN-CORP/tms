<?php

namespace App\Http\Controllers;

use App\Models\KpnBusinessUnit;
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
    /** JSON daftar semua Business Unit (nama_bisnis) — dari master_bisnisunits, kecuali "Others". */
    public function businessUnits()
    {
        return response()->json($this->buNamesExceptOthers()->values());
    }

    /**
     * JSON daftar Business Unit sbg [{value: id lokal, text: nama}] — untuk field yang
     * value-nya FK business_units.id (mis. filter, user, committee). Kecuali "Others".
     */
    public function businessUnitsLocal(\App\Services\OrgResolver $org)
    {
        return response()->json(
            $this->buNamesExceptOthers()
                ->map(fn ($name) => ['value' => $org->businessUnit($name)->id, 'text' => $name])
                ->values()
        );
    }

    /** Nama BU dari hcis, kecuali "Others". */
    private function buNamesExceptOthers(): \Illuminate\Support\Collection
    {
        return KpnBusinessUnit::names()
            ->reject(fn ($name) => mb_strtolower(trim((string) $name)) === 'others')
            ->values();
    }

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

    /**
     * JSON daftar Job Level dari hcis (employees.job_level), mis. 1A, 2A, ... 10B.
     * Tidak cascade dari BU: job level berlaku lintas Business Unit.
     */
    public function jobLevels()
    {
        return response()->json(KpnEmployee::jobLevels()->values());
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
