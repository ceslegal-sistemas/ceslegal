<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE informes_juridicos MODIFY COLUMN estado ENUM('entregado', 'pendiente', 'en_proceso', 'realizado') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        // Convertir registros 'realizado' a 'entregado' antes de revertir
        DB::table('informes_juridicos')->where('estado', 'realizado')->update(['estado' => 'entregado']);

        DB::statement("ALTER TABLE informes_juridicos MODIFY COLUMN estado ENUM('entregado', 'pendiente', 'en_proceso') NOT NULL DEFAULT 'pendiente'");
    }
};
