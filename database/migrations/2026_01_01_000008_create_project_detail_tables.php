<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Isi proposal project: Implementation Plan, Success Indicator, dan Budget,
 * masing-masing dengan lampirannya.
 *
 * - implementation_plans.attachment_* : jejak lampiran "utama" (legacy, masih
 *   dibaca UI). Lampiran majemuk ada di implementation_plan_attachments.
 * - implementation_indicators.achievement_value = TARGET, achievement = AKTUAL.
 * - project_budgets.actual_cost dihitung controller (actual_qty x actual_price).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implementation_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('activity', 2000)->nullable();
            $table->date('planning_start')->nullable();
            $table->date('planning_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->json('pic_user_ids')->nullable(); // daftar id user HCIS
            $table->text('remarks')->nullable();
            $table->unsignedInteger('sequence_no')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->unsignedBigInteger('attachment_uploaded_by')->nullable(); // user HCIS
            $table->timestamp('attachment_uploaded_at')->nullable();
            $table->unsignedInteger('attachment_replace_count')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sequence_no']);
        });

        Schema::create('implementation_plan_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('implementation_plan_id')->constrained('implementation_plans')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable(); // user HCIS
            $table->timestamps();
        });

        Schema::create('implementation_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('indicator')->nullable();
            $table->string('description', 2000)->nullable();
            $table->decimal('baseline', 18, 2)->nullable();
            $table->decimal('achievement_value', 18, 2)->nullable(); // target
            $table->decimal('achievement', 18, 2)->nullable();       // aktual
            $table->string('uom', 100)->nullable();
            $table->decimal('weightage', 8, 2)->nullable();
            $table->enum('type', ['Higher Better', 'Lower Better', 'Exact Value'])->nullable();
            $table->decimal('improvement', 18, 2)->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('project_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('item', 500)->nullable();
            $table->decimal('qty', 18, 2)->nullable();
            $table->string('uom', 100)->nullable();
            $table->decimal('unit_price', 20, 2)->nullable();
            $table->decimal('actual_qty', 18, 2)->nullable();
            $table->decimal('actual_price', 20, 2)->nullable();
            $table->decimal('actual_cost', 20, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('project_budget_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_budget_id')->constrained('project_budgets')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable(); // user HCIS
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_attachments');
        Schema::dropIfExists('project_budgets');
        Schema::dropIfExists('implementation_indicators');
        Schema::dropIfExists('implementation_plan_attachments');
        Schema::dropIfExists('implementation_plans');
    }
};
