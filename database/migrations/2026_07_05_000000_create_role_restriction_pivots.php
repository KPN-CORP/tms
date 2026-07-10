<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot untuk pembatasan (restriction) sebuah role terhadap
     * Business Unit / Company / Location / Employee.
     * Baris kosong = tanpa pembatasan (akses semua).
     */
    public function up(): void
    {
        Schema::create('role_business_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $table->unique(['role_id', 'business_unit_id']);
        });

        Schema::create('role_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unique(['role_id', 'company_id']);
        });

        Schema::create('role_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->unique(['role_id', 'location_id']);
        });

        Schema::create('role_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_employees');
        Schema::dropIfExists('role_locations');
        Schema::dropIfExists('role_companies');
        Schema::dropIfExists('role_business_units');
    }
};
