<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite ya almacena el enum como text; solo aplicar en MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) DEFAULT 'abogado'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 50)->default('abogado')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'abogado', 'cliente', 'rrhh') DEFAULT 'abogado'");
        }
    }
};
