<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status 'revision' diganti menjadi 'revision_required'.
 *
 * Label di UI memang sudah "Revision Required", tetapi nilai di database masih
 * 'revision'. Disamakan agar konsisten sekarang Revision Required bisa dipicu
 * oleh Project Sponsor (Layer 1) maupun seluruh Committee layer.
 *
 * ENUM MySQL menolak nilai yang belum terdaftar, jadi dilakukan tiga langkah:
 * daftarkan nilai baru -> pindahkan baris lama -> buang nilai lama.
 */
return new class extends Migration
{
    private const LAMA = "'draft','submitted','revision','committee_review','approved','rejected','ongoing','delayed','completion_review','completed','cancelled'";
    private const BARU = "'draft','submitted','revision_required','committee_review','approved','rejected','ongoing','delayed','completion_review','completed','cancelled'";

    public function up(): void
    {
        DB::statement("ALTER TABLE projects MODIFY status ENUM('draft','submitted','revision','revision_required','committee_review','approved','rejected','ongoing','delayed','completion_review','completed','cancelled') NOT NULL DEFAULT 'draft'");
        DB::table('projects')->where('status', 'revision')->update(['status' => 'revision_required']);
        DB::statement('ALTER TABLE projects MODIFY status ENUM(' . self::BARU . ") NOT NULL DEFAULT 'draft'");

        // Riwayat status (varchar) ikut disamakan agar linimasa terbaca konsisten.
        DB::table('project_status_logs')->where('old_status', 'revision')->update(['old_status' => 'revision_required']);
        DB::table('project_status_logs')->where('new_status', 'revision')->update(['new_status' => 'revision_required']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE projects MODIFY status ENUM('draft','submitted','revision','revision_required','committee_review','approved','rejected','ongoing','delayed','completion_review','completed','cancelled') NOT NULL DEFAULT 'draft'");
        DB::table('projects')->where('status', 'revision_required')->update(['status' => 'revision']);
        DB::statement('ALTER TABLE projects MODIFY status ENUM(' . self::LAMA . ") NOT NULL DEFAULT 'draft'");

        DB::table('project_status_logs')->where('old_status', 'revision_required')->update(['old_status' => 'revision']);
        DB::table('project_status_logs')->where('new_status', 'revision_required')->update(['new_status' => 'revision']);
    }
};
