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
        // Cambiar tipo_contrato de ENUM a VARCHAR para permitir múltiples tipos
        DB::statement("ALTER TABLE solicitudes_contrato MODIFY COLUMN tipo_contrato VARCHAR(100) DEFAULT 'Contrato de Obra o Labor'");

        // Cambiar estado de ENUM a VARCHAR para mayor flexibilidad
        DB::statement("ALTER TABLE solicitudes_contrato MODIFY COLUMN estado VARCHAR(50) DEFAULT 'pendiente'");

        // Actualizar valores antiguos al nuevo formato
        DB::table('solicitudes_contrato')->where('estado', 'solicitado')->update(['estado' => 'pendiente']);
        DB::table('solicitudes_contrato')->where('estado', 'cerrado')->update(['estado' => 'finalizado']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver a los ENUMs originales
        DB::statement("ALTER TABLE solicitudes_contrato MODIFY COLUMN tipo_contrato ENUM('labor_obra') DEFAULT 'labor_obra'");
        DB::statement("ALTER TABLE solicitudes_contrato MODIFY COLUMN estado ENUM('solicitado', 'en_analisis', 'revision_objeto', 'contrato_generado', 'enviado_rrhh', 'cerrado') DEFAULT 'solicitado'");
    }
};
