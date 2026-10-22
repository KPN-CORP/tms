<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SLA setting per jenis approval (izin: sla.manage).
 * Admin mengatur berapa hari batas review untuk tiap jenis yang harus di-review
 * (idea, project proposal, project completion, tracking, team change).
 * Info-only: dipakai menghitung status On Time / Due Soon / Overdue (tanpa notifikasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_settings', function (Blueprint $table) {
            $table->id();
            $table->string('approval_type')->unique();   // idea, project_proposal, dst.
            $table->unsignedSmallInteger('days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_settings');
    }
};
