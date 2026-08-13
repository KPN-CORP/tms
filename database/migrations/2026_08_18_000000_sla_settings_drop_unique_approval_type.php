<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lepas unique(approval_type) di sla_settings agar boleh ADA BANYAK SLA per jenis
 * approval — dibedakan oleh kolom `status` (mis. 3 hari @ On Review, 30 hari @ Submitted).
 * Idempotent (cek index dulu) agar aman di staging.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = collect(DB::select(
            "SHOW INDEX FROM sla_settings WHERE Key_name = 'sla_settings_approval_type_unique'"
        ))->isNotEmpty();

        if ($exists) {
            Schema::table('sla_settings', fn (Blueprint $t) => $t->dropUnique('sla_settings_approval_type_unique'));
        }
    }

    public function down(): void
    {
        $exists = collect(DB::select(
            "SHOW INDEX FROM sla_settings WHERE Key_name = 'sla_settings_approval_type_unique'"
        ))->isNotEmpty();

        if (! $exists) {
            Schema::table('sla_settings', fn (Blueprint $t) => $t->unique('approval_type'));
        }
    }
};
