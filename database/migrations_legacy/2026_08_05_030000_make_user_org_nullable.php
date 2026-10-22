<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * User dari hcis (mirror) tidak punya business_unit_id/department_id lokal,
 * jadi kolom ini dibuat nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY business_unit_id INT NULL');
        DB::statement('ALTER TABLE users MODIFY department_id INT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY business_unit_id INT NOT NULL');
        DB::statement('ALTER TABLE users MODIFY department_id INT NOT NULL');
    }
};
