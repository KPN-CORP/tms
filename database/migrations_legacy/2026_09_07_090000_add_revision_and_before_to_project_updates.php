<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dua tambahan untuk alur persetujuan Change Request:
 *
 *  1. status 'revision_required' — setiap layer approval bisa mengembalikan
 *     permintaan ke pengaju untuk diperbaiki, bukan hanya approve/reject.
 *  2. payload_before — nilai SEBELUM perubahan, sebaris dengan payload (nilai
 *     sesudah). Dipakai menampilkan perbandingan before/after saat penilai
 *     memeriksa hasil revisi.
 */
return new class extends Migration
{
    private const STATUS_LAMA = "'draft','pending','approved','rejected','applied','cancelled'";
    private const STATUS_BARU = "'draft','pending','revision_required','approved','rejected','applied','cancelled'";

    public function up(): void
    {
        DB::statement('ALTER TABLE project_updates MODIFY status ENUM(' . self::STATUS_BARU . ") NOT NULL DEFAULT 'draft'");

        Schema::table('project_updates', function (Blueprint $table) {
            $table->longText('payload_before')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        // Baris yang sedang menunggu revisi dikembalikan ke draft agar tidak
        // tersangkut di nilai enum yang akan dihapus.
        DB::table('project_updates')->where('status', 'revision_required')->update(['status' => 'draft']);
        DB::statement('ALTER TABLE project_updates MODIFY status ENUM(' . self::STATUS_LAMA . ") NOT NULL DEFAULT 'draft'");

        Schema::table('project_updates', function (Blueprint $table) {
            $table->dropColumn('payload_before');
        });
    }
};
