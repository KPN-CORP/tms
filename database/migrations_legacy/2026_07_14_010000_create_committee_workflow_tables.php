<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow review ide multi-layer (waterfall).
 *
 * - committee_assignments: siapa committee di layer berapa untuk tiap Business Unit.
 * - idea_approvals: jejak keputusan approve/reject tiap layer.
 * - ideas.current_layer: layer yang sedang menunggu review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $table->unsignedTinyInteger('layer'); // 1..10
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['business_unit_id', 'layer']);
        });

        Schema::create('idea_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->unsignedTinyInteger('layer');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision'); // approve | reject
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('ideas', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_layer')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropColumn('current_layer');
        });
        Schema::dropIfExists('idea_approvals');
        Schema::dropIfExists('committee_assignments');
    }
};
