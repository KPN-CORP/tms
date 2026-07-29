<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalisasi approval flow (T-92/95): committee_assignments dipakai untuk
 * beberapa jenis approval (idea, project_proposal, ...).
 * + current_layer & tabel jejak approval untuk Project Proposal.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tambah approval_type + index terpisah untuk FK business_unit_id
        // (unique lama (business_unit_id, layer) dipakai FK, jadi harus ada index
        //  pengganti sebelum unique itu dibuang).
        Schema::table('committee_assignments', function (Blueprint $table) {
            $table->enum('approval_type', ['idea', 'project_proposal', 'project_completion'])->default('idea')->after('id');
            $table->index('business_unit_id');
        });

        Schema::table('committee_assignments', function (Blueprint $table) {
            $table->dropUnique('committee_assignments_business_unit_id_layer_unique');
            $table->unique(['approval_type', 'business_unit_id', 'layer'], 'ca_type_bu_layer_unique');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_layer')->default(1)->after('status');
        });

        Schema::create('project_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedTinyInteger('layer');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision'); // approve | reject
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_approvals');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('current_layer');
        });

        Schema::table('committee_assignments', function (Blueprint $table) {
            $table->dropUnique('ca_type_bu_layer_unique');
            $table->unique(['business_unit_id', 'layer']);
            $table->dropIndex(['business_unit_id']);
            $table->dropColumn('approval_type');
        });
    }
};
