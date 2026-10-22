<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan Business Unit & Department sebagai NAMA (dari hcis: master_bisnisunits.nama_bisnis
 * & departments.department_name) di samping FK lokal (bridge untuk RBAC scope & relasi lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->string('business_unit_name')->nullable()->after('business_unit_id');
            $table->string('department_name')->nullable()->after('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropColumn(['business_unit_name', 'department_name']);
        });
    }
};
