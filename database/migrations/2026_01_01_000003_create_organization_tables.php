<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master org LOKAL (tm_system) — cermin dari hcis, di-resolve find-or-create
 * by NAMA oleh App\Services\OrgResolver supaya Idea dan Committee Assignment
 * menghasilkan FK yang identik (routing committee cocok).
 *
 * Hierarki: Business Unit -> Company -> Location, dan Business Unit -> Department.
 * Padanan read-only di hcis ada pada App\Models\Kpn* (koneksi kpncorp).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            // OrgResolver::businessUnit() memakai firstOrCreate(['name' => ...]),
            // jadi nama harus unik agar tidak lahir dua BU dengan nama sama.
            $table->string('name')->unique();
            $table->string('code', 20)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->timestamps();
            $table->unique(['business_unit_id', 'name']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        // Department sengaja tanpa timestamps (lihat App\Models\Department).
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $table->string('name');
            // OrgResolver::department() firstOrCreate by (name, business_unit_id).
            $table->unique(['business_unit_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('business_units');
    }
};
