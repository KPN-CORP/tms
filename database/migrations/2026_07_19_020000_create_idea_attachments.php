<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-17: lampiran pada Idea (supporting document).
 * Mirror struktur project_attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idea_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_attachments');
    }
};
