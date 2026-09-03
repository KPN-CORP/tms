<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lampiran per baris Budget — polanya sama dgn implementation_plan_attachments. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_budget_attachments')) {
            Schema::create('project_budget_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_budget_id')->index();
                $table->string('file_name');
                $table->string('file_path');
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_attachments');
    }
};
