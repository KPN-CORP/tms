<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesuaikan field proposal dengan dokumen:
 * - Implementation Plan (T-42/62/63): planning & actual start/end, PIC multi-select.
 *   Status dihitung otomatis (accessor), tidak disimpan.
 * - Success Indicators (T-43/71): baseline & type (Higher/Lower Better);
 *   % improvement dihitung otomatis.
 *
 * Kolom lama dibiarkan (tidak dipakai) agar aman terhadap FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementation_plans', function (Blueprint $table) {
            $table->date('planning_start')->nullable()->after('activity');
            $table->date('planning_end')->nullable()->after('planning_start');
            $table->date('actual_start')->nullable()->after('planning_end');
            $table->date('actual_end')->nullable()->after('actual_start');
            $table->json('pic_user_ids')->nullable()->after('actual_end');
        });

        Schema::table('implementation_indicators', function (Blueprint $table) {
            $table->decimal('baseline', 18, 2)->nullable()->after('description');
            $table->enum('type', ['Higher Better', 'Lower Better'])->nullable()->after('weightage');
        });
    }

    public function down(): void
    {
        Schema::table('implementation_plans', function (Blueprint $table) {
            $table->dropColumn(['planning_start', 'planning_end', 'actual_start', 'actual_end', 'pic_user_ids']);
        });
        Schema::table('implementation_indicators', function (Blueprint $table) {
            $table->dropColumn(['baseline', 'type']);
        });
    }
};
