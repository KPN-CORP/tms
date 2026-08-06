<?php

namespace App\Services;

use App\Models\BusinessUnit;
use App\Models\Department;

/**
 * Menjembatani nama org dari hcis ke FK lokal (tm_system) secara konsisten:
 * find-or-create by NAMA. Dipakai bersama oleh Idea & Committee Assignment
 * agar business_unit_id/department_id yang dihasilkan SAMA (routing cocok).
 */
class OrgResolver
{
    public function businessUnit(string $name): BusinessUnit
    {
        $name = trim($name);
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 10)) ?: 'GEN';

        return BusinessUnit::firstOrCreate(['name' => $name], ['code' => $code]);
    }

    public function department(string $name, int $businessUnitId): Department
    {
        return Department::firstOrCreate([
            'name'             => trim($name),
            'business_unit_id' => $businessUnitId,
        ]);
    }
}
