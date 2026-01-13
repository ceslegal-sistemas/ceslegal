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
        Schema::table('trabajadores', function (Blueprint $table) {
            // Eliminar la constraint única antigua (tipo_documento + numero_documento)
            $table->dropUnique(['tipo_documento', 'numero_documento']);

            // Agregar nueva constraint única que incluye empresa_id
            // Esto permite que un trabajador con el mismo documento exista en diferentes empresas
            $table->unique(['tipo_documento', 'numero_documento', 'empresa_id'], 'trabajadores_documento_empresa_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            // Eliminar la constraint nueva
            $table->dropUnique('trabajadores_documento_empresa_unique');

            // Restaurar la constraint antigua
            $table->unique(['tipo_documento', 'numero_documento']);
        });
    }
};
