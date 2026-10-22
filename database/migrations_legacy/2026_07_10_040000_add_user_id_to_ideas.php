<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel ideas awalnya tidak menyimpan pembuat ide.
 * Kolom user_id diperlukan untuk fitur "My Ideas" (ide milik user yang login)
 * dan FR-024 (daftar Idea yang diajukan oleh pengguna).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
