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
        // Eliminar el índice UNIQUE antiguo
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
        });

        // Crear un índice UNIQUE que solo aplica cuando deleted_at es NULL
        // Esto permite reutilizar códigos en registros soft-deleted
        DB::statement('CREATE UNIQUE INDEX procesos_disciplinarios_codigo_unique ON procesos_disciplinarios (codigo, deleted_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar el índice UNIQUE compuesto
        DB::statement('DROP INDEX procesos_disciplinarios_codigo_unique ON procesos_disciplinarios');

        // Restaurar el índice UNIQUE original
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->unique('codigo');
        });
    }
};
