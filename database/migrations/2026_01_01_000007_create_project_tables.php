<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project (shell -> proposal -> implementation -> completion) dan tabel sekitarnya.
 *
 * - projects.project_id : business ID "P-{BU}-{YYYYMMDD}-{CAT}-{00000}".
 * - projects.idea_id    : menunjuk ideas.idea_id (STRING business ID), bukan
 *                         ideas.id — lihat relasi Project::idea(). Jadi kolom ini
 *                         di-index, bukan foreign key.
 * - is_shell_draft      : draft shell disembunyikan global scope (Project::booted).
 * - Kolom nullable menyeluruh karena shell draft boleh disimpan setengah jadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_id', 50)->nullable()->index();
            $table->string('idea_id', 50)->nullable()->index(); // -> ideas.idea_id
            $table->string('project_name')->nullable();
            $table->string('project_scope')->nullable();
            $table->string('expected_outcome', 500)->nullable();
            $table->text('notes')->nullable();
            $table->text('project_summary')->nullable();
            $table->string('project_category', 20)->nullable(); // kode kategori (legacy, ikut di-maintain)
            $table->foreignId('project_category_id')->nullable()->constrained('project_categories')->nullOnDelete();
            $table->enum('status', [
                'draft', 'submitted', 'revision_required', 'committee_review',
                'approved', 'rejected', 'ongoing', 'delayed',
                'completion_review', 'completed', 'cancelled',
            ])->default('draft');
            $table->boolean('is_shell_draft')->default(false);
            $table->unsignedTinyInteger('current_layer')->default(1);
            $table->string('actual_status', 20)->default('draft');
            // Sponsor & Leader = user HCIS — tanpa foreign key.
            $table->unsignedBigInteger('project_sponsor_id')->nullable()->index();
            $table->unsignedBigInteger('project_leader_id')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'current_layer']);
        });

        Schema::create('project_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedTinyInteger('layer');
            $table->unsignedBigInteger('user_id')->index();  // user HCIS
            $table->unsignedBigInteger('on_behalf_of_id')->nullable();
            $table->string('decision', 20); // approve | reject
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index(); // user HCIS
            $table->string('role', 100)->nullable();
            $table->date('joined_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('project_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->unsignedBigInteger('changed_by')->nullable(); // user HCIS
            // Alasan cancel disimpan di sini dengan awalan "Cancelled: ".
            $table->text('remarks')->nullable();
            // Hanya created_at — modelnya $timestamps = false.
            $table->timestamp('created_at')->useCurrent();
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable(); // user HCIS
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachments');
        Schema::dropIfExists('project_status_logs');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_approvals');
        Schema::dropIfExists('projects');
    }
};
