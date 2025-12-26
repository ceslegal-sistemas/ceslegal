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
        // Cambiar la columna role de ENUM a VARCHAR para mayor flexibilidad
        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) DEFAULT 'abogado'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver al ENUM original (solo si es necesario revertir)
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'abogado', 'cliente', 'rrhh') DEFAULT 'abogado'");
    }
};
