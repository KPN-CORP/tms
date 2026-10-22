<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom on_behalf_of_id — keputusan yang diambil Super Admin ATAS NAMA committee
 * yang sedang memegang layer aktif (izin 'override.role').
 *
 * user_id tetap berisi PELAKU sebenarnya, sehingga jejak audit tidak pernah
 * menyamarkan siapa yang menekan tombol. on_behalf_of_id menyimpan committee yang
 * seharusnya memutus, dipakai riwayat menampilkan "Approved by Dali behalf of
 * Janice Olivia". NULL = keputusan biasa oleh committee-nya sendiri.
 */
return new class extends Migration
{
    private const TABEL = ['idea_approvals', 'project_approvals'];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            if (Schema::hasColumn($tabel, 'on_behalf_of_id')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) {
                // Tanpa foreign key: users berada di koneksi hcis (database terpisah).
                $table->unsignedBigInteger('on_behalf_of_id')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasColumn($tabel, 'on_behalf_of_id')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) {
                $table->dropColumn('on_behalf_of_id');
            });
        }
    }
};
