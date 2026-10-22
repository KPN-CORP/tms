<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel bawaan framework (auth scaffolding Breeze + queue).
 *
 * `personal_access_tokens` TIDAK dibuat di sini — migrasi itu sudah disediakan
 * paket laravel/sanctum sendiri (loadMigrationsFrom), jadi menyalinnya ke
 * database/migrations membuat tabel dibuat dua kali dan migrate gagal.
 *
 * CATATAN PENTING: App\Models\User memakai koneksi `kpncorp` (hcis), BUKAN
 * tabel `users` di sini. Tabel lokal ini hanya dipakai oleh scaffolding Breeze
 * dan test suite. Karena itu TIDAK ADA satu pun foreign key di tm_system yang
 * menunjuk ke `users` lokal — semua kolom *_id/*_by user menyimpan id hcis dan
 * sengaja dibiarkan tanpa constraint (lihat komentar di tiap tabel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('business_unit_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedTinyInteger('job_level')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
