<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi committee: siapa penyetuju di layer berapa, untuk jenis approval
 * apa, pada Business Unit (dan opsional Department) mana.
 *
 * Routing (IdeaWorkflowService & ProjectApprovalWorkflowService):
 *  - department_id NULL = berlaku BU-wide; assignment department-specific menang.
 *  - Untuk type ber-budget (project_proposal, budget_change), total budget project
 *    dicocokkan ke rentang budget_min..budget_max dan rentang TERSEMPIT yang menang.
 *    budget_max "tak terbatas" ditulis sebagai 999999999999999 (BUDGET_MAX_UNBOUNDED).
 *  - Layer 1 dicadangkan untuk Project Sponsor pada semua type kecuali 'idea'
 *    (SPONSOR_LAYER_TYPES), jadi konfigurasi committee-nya mulai dari layer 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_assignments', function (Blueprint $table) {
            $table->id();
            $table->enum('approval_type', [
                'idea', 'project_proposal', 'project_completion', 'project_tracking',
                'team_change', 'plan_indicator_change', 'budget_change',
            ])->default('idea');
            $table->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->decimal('budget_min', 20, 2)->nullable();
            $table->decimal('budget_max', 20, 2)->nullable();
            $table->unsignedTinyInteger('layer'); // 1..10
            $table->unsignedBigInteger('user_id')->index(); // user HCIS — tanpa foreign key
            $table->timestamps();

            $table->unique(
                ['approval_type', 'business_unit_id', 'department_id', 'budget_min', 'budget_max', 'layer'],
                'ca_type_bu_dept_range_layer_unq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_assignments');
    }
};
