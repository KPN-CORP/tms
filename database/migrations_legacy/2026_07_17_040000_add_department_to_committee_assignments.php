<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-95: routing committee Idea di-tie dengan Business Unit & Unit (Department).
 * department_id NULL = berlaku untuk seluruh department di BU (BU-wide default).
 * Routing: assignment department-specific mengalahkan BU-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_assignments', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('business_unit_id')
                ->constrained('departments')->nullOnDelete();
            $table->dropUnique('ca_type_bu_layer_unique');
            $table->unique(['approval_type', 'business_unit_id', 'department_id', 'layer'], 'ca_type_bu_dept_layer_unq');
        });
    }

    public function down(): void
    {
        Schema::table('committee_assignments', function (Blueprint $table) {
            $table->dropUnique('ca_type_bu_dept_layer_unq');
            $table->unique(['approval_type', 'business_unit_id', 'layer'], 'ca_type_bu_layer_unique');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
