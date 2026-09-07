<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega 'anexar_completo' al ENUM de tipo_cambio + las columnas que ese
 * tipo necesita (titulo_anexo/texto_anexo) y una columna compartida por
 * ambos tipos (alerta_incoherencia, la verificación cruzada IA descrita en
 * RitActualizacionAutomaticaService). Vía SQL crudo en MySQL, no
 * Schema::table()->enum()->change() - mismo motivo y mismo patrón ya usado
 * en 2026_09_02_151002_add_plazo_to_modificaciones_contractuales_tipo_enum.php
 * (Doctrina puede reescribir el ENUM de forma imprevisible en MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sugerencias_actualizacion_rit', function (Blueprint $table) {
            $table->string('titulo_anexo')->nullable()->after('texto_propuesto');
            $table->longText('texto_anexo')->nullable()->after('titulo_anexo');
            $table->text('alerta_incoherencia')->nullable()->after('justificacion_ia');
        });

        if (DB::getDriverName() !== 'mysql') {
            Schema::table('sugerencias_actualizacion_rit', function (Blueprint $table) {
                $table->enum('tipo_cambio', ['modificar', 'agregar', 'eliminar', 'anexar_completo'])->change();
            });
            return;
        }

        DB::statement("ALTER TABLE sugerencias_actualizacion_rit MODIFY tipo_cambio ENUM('modificar', 'agregar', 'eliminar', 'anexar_completo') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('sugerencias_actualizacion_rit', function (Blueprint $table) {
                $table->enum('tipo_cambio', ['modificar', 'agregar', 'eliminar'])->change();
            });
        } else {
            DB::statement("ALTER TABLE sugerencias_actualizacion_rit MODIFY tipo_cambio ENUM('modificar', 'agregar', 'eliminar') NOT NULL");
        }

        Schema::table('sugerencias_actualizacion_rit', function (Blueprint $table) {
            $table->dropColumn(['titulo_anexo', 'texto_anexo', 'alerta_incoherencia']);
        });
    }
};
