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
        Schema::table('documentos_legales', function (Blueprint $table) {
            // Independiente de 'tipo' (que clasifica la FUENTE legal: ley,
            // sentencia, concepto...): este campo indica CÓMO debe
            // incorporarse al RIT. Lo marca el abogado al subir el documento
            // - una política/protocolo que declara ser "parte integral del
            // Reglamento" debe ir completa como Anexo, no resumida en un
            // párrafo (ver RitActualizacionAutomaticaService).
            $table->boolean('incorporar_completo')->default(false)->after('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_legales', function (Blueprint $table) {
            $table->dropColumn('incorporar_completo');
        });
    }
};
