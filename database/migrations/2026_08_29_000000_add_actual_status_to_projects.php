<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status baseline Actual Implementation:
 *   draft     → Actual masih diisi anggota tim (editable)
 *   pending   → sudah di-submit, menunggu approve Project Sponsor (Actual terkunci)
 *   baselined → Sponsor approve → baseline terkunci; perubahan lewat change-request
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'actual_status')) {
                $table->string('actual_status', 20)->default('draft')->after('current_layer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'actual_status')) {
                $table->dropColumn('actual_status');
            }
        });
    }
};
