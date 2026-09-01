<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pergantian berkas pada Implementation Plan: siapa yang mengunggah /
 * mengganti dan kapan. Diisi baik saat unggah pertama maupun setiap kali file
 * lama digantikan, sehingga terlihat langsung di UI (tidak hanya di audit log).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementation_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('implementation_plans', 'attachment_uploaded_by')) {
                $table->unsignedBigInteger('attachment_uploaded_by')->nullable()->after('attachment_name');
            }
            if (! Schema::hasColumn('implementation_plans', 'attachment_uploaded_at')) {
                $table->timestamp('attachment_uploaded_at')->nullable()->after('attachment_uploaded_by');
            }
            // Berapa kali berkas diganti (0 = belum pernah diganti sejak unggah pertama).
            if (! Schema::hasColumn('implementation_plans', 'attachment_replace_count')) {
                $table->unsignedInteger('attachment_replace_count')->default(0)->after('attachment_uploaded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('implementation_plans', function (Blueprint $table) {
            foreach (['attachment_uploaded_by', 'attachment_uploaded_at', 'attachment_replace_count'] as $col) {
                if (Schema::hasColumn('implementation_plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
