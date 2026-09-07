<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email Notifications — pesan terjadwal per SLA.
 *
 * Satu baris = satu pesan yang dikirim untuk SLA tertentu, dibatasi filter
 * organisasi (Business Unit / Company / Location / Job Level), berlaku pada
 * rentang tanggal, dan diulang pada hari-hari yang dipilih di "Repeat On".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_notification_schedules', function (Blueprint $table) {
            $table->id();

            // SLA yang menjadi dasar pesan ini. Dibiarkan nullable + nullOnDelete
            // supaya menghapus SLA tidak ikut menghapus riwayat pesannya.
            $table->foreignId('sla_setting_id')->nullable()->constrained('sla_settings')->nullOnDelete();

            $table->string('title');

            // Filter organisasi — semuanya multi-pilih, disimpan sebagai daftar NAMA
            // (bukan id) mengikuti pola filter lain di aplikasi ini. Kosong = tanpa batasan.
            $table->json('business_units')->nullable();
            $table->json('companies')->nullable();
            $table->json('locations')->nullable();
            $table->json('job_levels')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->boolean('attach_detail')->default(false);

            // Hari pengulangan: ['mon','tue',...]. Kosong = tidak diulang.
            $table->json('repeat_days')->nullable();

            $table->longText('message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notification_schedules');
    }
};
