<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan Location (area) & Company (contribution_level) sebagai NAMA dari hcis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->string('location_name')->nullable()->after('location_id');
            $table->string('company_name')->nullable()->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropColumn(['location_name', 'company_name']);
        });
    }
};
