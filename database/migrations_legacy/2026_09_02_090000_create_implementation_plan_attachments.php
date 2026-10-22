<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran Implementation Plan menjadi BANYAK per activity (sebelumnya satu file
 * pada kolom attachment_path/attachment_name). Lampiran lama dipindahkan ke tabel
 * baru agar tidak ada berkas yang hilang; kolom lama sengaja DIBIARKAN supaya
 * rollback tetap aman dan data lama masih bisa dibaca bila diperlukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('implementation_plan_attachments')) {
            Schema::create('implementation_plan_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('implementation_plan_id')->index();
                $table->string('file_name');
                $table->string('file_path');
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }

        // Pindahkan lampiran tunggal yang sudah ada.
        if (Schema::hasColumn('implementation_plans', 'attachment_path')) {
            $plans = DB::table('implementation_plans')
                ->whereNotNull('attachment_path')
                ->get(['id', 'attachment_path', 'attachment_name', 'attachment_uploaded_by', 'attachment_uploaded_at']);

            foreach ($plans as $p) {
                $sudahAda = DB::table('implementation_plan_attachments')
                    ->where('implementation_plan_id', $p->id)
                    ->where('file_path', $p->attachment_path)
                    ->exists();

                if (! $sudahAda) {
                    DB::table('implementation_plan_attachments')->insert([
                        'implementation_plan_id' => $p->id,
                        'file_name'   => $p->attachment_name ?: basename($p->attachment_path),
                        'file_path'   => $p->attachment_path,
                        'file_size'   => null,
                        'uploaded_by' => $p->attachment_uploaded_by ?? null,
                        'created_at'  => $p->attachment_uploaded_at ?? now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_plan_attachments');
    }
};
