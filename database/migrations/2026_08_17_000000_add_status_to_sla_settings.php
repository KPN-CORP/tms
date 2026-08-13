<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `status` ke sla_settings — status yang menjadi basis perhitungan SLA
 * untuk tiap jenis approval (mis. Idea SLA 3 hari dihitung saat status "On Review").
 * Nullable & idempotent agar aman dijalankan di staging.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('sla_settings', 'status')) {
                $table->string('status')->nullable()->after('days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sla_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sla_settings', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
