<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change staging (App\Services\Project\ProjectChangeStagingService).
 *
 * Saat project sudah berjalan, suntingan Leader pada Team / Plan & Indicator /
 * Budget tidak langsung berlaku: terkumpul jadi SATU draft per
 * (project, change_type), diajukan sebagai change request, dirutekan ke
 * committee type yang cocok, dan baru diterapkan saat applyUpdate().
 *
 * payload        = bentuk baru yang diminta
 * payload_before = bentuk sebelum perubahan (untuk diff & tampilan revisi)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedBigInteger('requested_by')->index(); // user HCIS
            $table->enum('change_type', [
                'budget', 'planning', 'team', 'general',
                'team_change', 'plan_indicator_change', 'budget_change', 'leadership_change',
            ]);
            $table->text('description')->nullable();
            $table->enum('approver_role', ['sponsor', 'committee', 'auto', 'idea_committee', 'unassigned'])
                ->default('sponsor');
            $table->unsignedTinyInteger('current_layer')->default(1);
            $table->enum('status', [
                'draft', 'pending', 'revision_required', 'approved', 'rejected', 'applied', 'cancelled',
            ])->default('draft');
            $table->unsignedBigInteger('reviewed_by')->nullable(); // user HCIS
            $table->text('review_note')->nullable();
            $table->json('snapshot_before')->nullable();
            $table->json('snapshot_after')->nullable();
            $table->longText('payload')->nullable();
            $table->longText('payload_before')->nullable();
            $table->timestamps();

            // Satu draft aktif per (project, change_type) — dicari lewat indeks ini.
            $table->index(['project_id', 'change_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
    }
};
