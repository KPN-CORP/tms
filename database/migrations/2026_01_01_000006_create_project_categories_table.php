<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Project Category (dikelola Super Admin): grade range Leader/Sponsor,
 * batas jumlah anggota tim, dan komposisi role wajib (required_roles JSON,
 * bentuk: [{"role": "...", "total": n}, ...]).
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
            $table->json('required_roles')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tiga kategori bawaan (kode dipakai pada business ID project).
        $now = now();
        DB::table('project_categories')->insert([
            ['name' => 'Quality Control Circle',  'code' => 'QCC', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Quality Control Project', 'code' => 'QCP', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Suggestion System',       'code' => 'SS',  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_categories');
    }
};
