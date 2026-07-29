<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lifecycle setelah Approved (T-65/66/67): completion request + completed.
 * (ongoing/delayed/cancelled ikut ditambahkan ke enum untuk tahap tracking berikutnya.)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE projects MODIFY status ENUM(
            'draft','submitted','revision','committee_review','approved','rejected',
            'ongoing','delayed','completion_review','completed','cancelled'
        ) NOT NULL DEFAULT 'draft'");

        Schema::table('projects', function (Blueprint $table) {
            $table->text('project_summary')->nullable()->after('expected_outcome');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('project_summary');
        });

        DB::statement("ALTER TABLE projects MODIFY status ENUM(
            'draft','submitted','revision','committee_review','approved','rejected'
        ) NOT NULL DEFAULT 'draft'");
    }
};
