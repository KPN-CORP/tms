<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §9.2: Audit trail generik (ringan, tanpa paket).
 * Mencatat setiap create/update/delete pada model ber-trait LogsActivity:
 * siapa (causer), objek apa (subject), event, dan diff field (changes JSON).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event');                       // created | updated | deleted
            $table->string('subject_type');                // FQCN model
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label')->nullable();   // ringkasan human-readable
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('causer_name')->nullable();     // disimpan agar tetap ada meski user dihapus
            $table->json('changes')->nullable();           // {field: {old, new}} untuk update; snapshot untuk create/delete
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
