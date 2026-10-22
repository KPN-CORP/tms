<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi & audit:
 *  - guidelines   : dokumen panduan + flag siapa boleh lihat/unduh.
 *  - sla_settings : batas hari review per (approval_type, status). TIDAK unik
 *                   pada approval_type — satu jenis approval bisa punya SLA
 *                   berbeda per status. SlaService hanya menampilkan badge
 *                   On Time / Due Soon / Overdue; pengiriman email ditangani
 *                   fitur Email Notification yang terpisah.
 *  - activity_logs: audit trail dari App\Models\Concerns\LogsActivity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidelines', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('employee_can_view')->default(true);
            $table->boolean('employee_can_download')->default(true);
            $table->boolean('committee_can_view')->default(true);
            $table->boolean('committee_can_download')->default(true);
            $table->unsignedBigInteger('uploaded_by')->nullable(); // user HCIS
            $table->timestamps();
        });

        Schema::create('sla_settings', function (Blueprint $table) {
            $table->id();
            $table->string('approval_type', 50)->index(); // idea, project_proposal, dst.
            $table->unsignedSmallInteger('days')->default(7);
            $table->string('status', 30)->nullable();      // status yang dipantau SLA ini
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 20);                   // created | updated | deleted
            $table->string('subject_type');                // FQCN model
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label')->nullable();   // ringkasan human-readable
            $table->unsignedBigInteger('causer_id')->nullable(); // user HCIS
            $table->string('causer_name')->nullable();     // disimpan agar tetap ada meski user hilang
            $table->json('changes')->nullable();           // {field: {old, new}} | snapshot
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('sla_settings');
        Schema::dropIfExists('guidelines');
    }
};
