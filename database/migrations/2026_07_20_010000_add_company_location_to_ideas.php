<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan Idea ke Company & Location (opsional) agar restrict role
 * dimensi Company/Location bisa menyaring data (hierarki BU -> Company -> Location).
 * NULL = ide tidak ditujukan ke company/location tertentu (level BU).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('business_unit_id')
                ->constrained('companies')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('company_id')
                ->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
