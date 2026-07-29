<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project Category master (T-2/96/107-111): dikelola Super Admin (Add/Edit/Archive),
 * termasuk grade range Leader/Sponsor & jumlah team member (informatif).
 * Menggantikan enum hardcode di projects.project_category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('leader_grade_min')->nullable();
            $table->unsignedInteger('leader_grade_max')->nullable();
            $table->unsignedInteger('sponsor_grade_min')->nullable();
            $table->unsignedInteger('sponsor_grade_max')->nullable();
            $table->unsignedInteger('max_team_members')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed 3 kategori awal (kode lama).
        DB::table('project_categories')->insert([
            ['name' => 'Quality Control Circle', 'code' => 'QCC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Quality Control Project', 'code' => 'QCP', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Suggestion System', 'code' => 'SS', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // projects: enum -> varchar (kode) + FK ke master.
        DB::statement("ALTER TABLE projects MODIFY project_category VARCHAR(20) NOT NULL");

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_category_id')->nullable()->after('project_category')
                ->constrained('project_categories')->nullOnDelete();
        });

        // Backfill FK dari kode yang sudah ada (COLLATE untuk hindari beda collation antar tabel).
        DB::statement("UPDATE projects p JOIN project_categories c ON c.code = p.project_category COLLATE utf8mb4_general_ci SET p.project_category_id = c.id");
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_category_id');
        });

        DB::statement("ALTER TABLE projects MODIFY project_category ENUM('QCC','QCP','SS') NOT NULL");

        Schema::dropIfExists('project_categories');
    }
};
