<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attachment Actual Implementation Plan — 1 file per plan (path + nama asli).
 * Idempoten (guard hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementation_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('implementation_plans', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('implementation_plans', 'attachment_name')) {
                $table->string('attachment_name')->nullable()->after('attachment_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('implementation_plans', function (Blueprint $table) {
            foreach (['attachment_path', 'attachment_name'] as $col) {
                if (Schema::hasColumn('implementation_plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
