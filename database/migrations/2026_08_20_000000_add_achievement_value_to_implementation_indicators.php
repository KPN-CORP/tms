<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `achievement_value` (TARGET) ke implementation_indicators —
 * beda dari `achievement` (nilai aktual yang dicapai). Nullable & idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementation_indicators', function (Blueprint $table) {
            if (! Schema::hasColumn('implementation_indicators', 'achievement_value')) {
                $table->decimal('achievement_value', 15, 2)->nullable()->after('baseline');
            }
        });
    }

    public function down(): void
    {
        Schema::table('implementation_indicators', function (Blueprint $table) {
            if (Schema::hasColumn('implementation_indicators', 'achievement_value')) {
                $table->dropColumn('achievement_value');
            }
        });
    }
};
