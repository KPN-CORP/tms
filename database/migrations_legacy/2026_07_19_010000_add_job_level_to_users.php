<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-1/12/71/86: Job Level (grade) pada user.
 * Dipakai untuk eligibility Project Category (leader/sponsor grade range).
 * Angka lebih besar = jabatan lebih tinggi (lihat User::JOB_LEVELS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('job_level')->nullable()->after('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_level');
        });
    }
};
