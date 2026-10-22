<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ide dan alur review-nya.
 *
 * - ideas.idea_id  : business ID "I-{BU}-{YYYYMMDD}-{00000}" (lihat IdeaController).
 *                    Dibuat sekali saat create, baik draft maupun submit.
 * - ideas.*_name   : snapshot nama org saat pengajuan — tetap benar meski master
 *                    org berubah/di-rename kemudian.
 * - ideas.current_layer : layer committee yang sedang menunggu review.
 *
 * Tabel ini memakai `modified_at` sebagai UPDATED_AT (lihat App\Models\Idea).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            // user_id = id user HCIS — lintas database, TANPA foreign key.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('idea_id', 50)->unique();
            $table->string('idea_name')->nullable();
            $table->text('problem')->nullable();
            $table->text('description')->nullable();
            $table->text('expected_outcome')->nullable();

            $table->foreignId('business_unit_id')->nullable()->constrained('business_units')->nullOnDelete();
            $table->string('business_unit_name')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('company_name')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('location_name')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('department_name')->nullable();

            $table->string('idea_docs')->nullable();
            $table->string('status', 30)->default('draft'); // draft|submitted|review|approved|rejected
            $table->unsignedTinyInteger('current_layer')->default(1);
            // Business ID project yang lahir dari ide ini (projects.project_id).
            $table->string('project_id', 50)->nullable()->index();
            $table->text('feedback')->nullable();
            $table->string('feedback_docs')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('modified_at')->nullable();

            $table->index(['status', 'current_layer']);
        });

        Schema::create('idea_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->unsignedTinyInteger('layer');
            // user HCIS — tanpa foreign key.
            $table->unsignedBigInteger('user_id')->index();
            // Diisi bila keputusan diambil "atas nama" committee lain.
            $table->unsignedBigInteger('on_behalf_of_id')->nullable();
            $table->string('decision', 20); // approve | reject
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('idea_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable(); // user HCIS
            // Hanya created_at — modelnya $timestamps = false.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_attachments');
        Schema::dropIfExists('idea_approvals');
        Schema::dropIfExists('ideas');
    }
};
