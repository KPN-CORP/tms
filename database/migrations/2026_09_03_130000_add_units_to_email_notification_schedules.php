<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filter "Unit" pada Email Notifications — pasangan Business Unit, sama seperti
 * filter Unit di My Ideas / My Project. Disimpan sebagai daftar NAMA unit
 * (departments.department_name dari hcis), kosong berarti tanpa batasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_notification_schedules', function (Blueprint $table) {
            $table->json('units')->nullable()->after('business_units');
        });
    }

    public function down(): void
    {
        Schema::table('email_notification_schedules', function (Blueprint $table) {
            $table->dropColumn('units');
        });
    }
};
