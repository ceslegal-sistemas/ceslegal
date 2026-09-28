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
        Schema::table('terminaciones_contrato', function (Blueprint $table) {
            // Fase 2: cuando la terminación viene de la sanción "Terminación
            // de Contrato" de Emitir Sanción (en vez de la acción manual de
            // Historial de Contratos) - nullable, esta columna nunca se
            // llena cuando el abogado la termina manualmente.
            $table->foreignId('proceso_disciplinario_id')->nullable()
                ->after('abogado_id')
                ->constrained('procesos_disciplinarios')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terminaciones_contrato', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proceso_disciplinario_id');
        });
    }
};
