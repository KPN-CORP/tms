<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hak akses guideline per grup (Employee & Committee) untuk View & Download.
 * Admin/Super Admin (pengelola) selalu punya akses penuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guidelines', function (Blueprint $table) {
            foreach ([
                'employee_can_view',
                'employee_can_download',
                'committee_can_view',
                'committee_can_download',
            ] as $col) {
                if (! Schema::hasColumn('guidelines', $col)) {
                    $table->boolean($col)->default(true)->after('is_active');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('guidelines', function (Blueprint $table) {
            foreach (['employee_can_view', 'employee_can_download', 'committee_can_view', 'committee_can_download'] as $col) {
                if (Schema::hasColumn('guidelines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
