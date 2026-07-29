<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project Update Request (T-56/58/59/260-268): perubahan proposal saat project
 * berjalan, dengan routing approver per jenis perubahan & snapshot before/after
 * (version tracking, T-78). Perubahan hanya diterapkan setelah approve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->enum('change_type', ['budget', 'planning', 'team', 'general']);
            $table->text('description');
            $table->enum('approver_role', ['sponsor', 'committee', 'auto']);
            $table->enum('status', ['pending', 'approved', 'rejected', 'applied', 'cancelled'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->json('snapshot_before')->nullable();
            $table->json('snapshot_after')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
    }
};
