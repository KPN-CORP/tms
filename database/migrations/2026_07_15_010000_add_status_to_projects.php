<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status lifecycle proposal project:
 * draft -> submitted -> (revision -> submitted)* -> committee_review -> approved
 *                                                 -> rejected
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'revision', 'committee_review', 'approved', 'rejected'])
                ->default('draft')
                ->after('project_category');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
