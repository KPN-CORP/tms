<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Schema:: create ('users', function (Blueprint $table) {
     * $table ->id();
     * $table ->string('name');
     * $table ->timestamp('email_verified_at')->nullable();
     * $table ->string('password');
     * $table ->rememberToken();
     * $table ->timestamp()
     * $table ->admin if == then  < timestamp(0);
     * 
     * $table ->timestamp()
     * })
     * 
     * go.commite  -> (committee.dashboard)
     * 
     * schema:: create ('users', function(Blueprint $table){
     * $table ->id();
     * $table ->string('name');
     * $table ->timestamp();
     * $tbale ->timestamp();
     * $table ->
     * })
     * 
     * 
     */

    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
