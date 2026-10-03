<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selección múltiple en el quiz de socialización (pedido de Andrés
 * Sarmiento, reunión 2026-10-03: "no sean 3 preguntas sino 5, de selección
 * múltiple o verdadero/falso"). Columnas nuevas, nullable - las preguntas
 * V/F existentes siguen funcionando igual (tipo_pregunta default 'vf',
 * opciones/respuesta_correcta_indice solo se usan si tipo_pregunta='multiple').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamento_interno_tema', function (Blueprint $table) {
            $table->string('tipo_pregunta', 20)->default('vf')->after('pregunta_vf');
            $table->json('opciones')->nullable()->after('tipo_pregunta');
            $table->unsignedTinyInteger('respuesta_correcta_indice')->nullable()->after('opciones');
        });
    }

    public function down(): void
    {
        Schema::table('reglamento_interno_tema', function (Blueprint $table) {
            $table->dropColumn(['tipo_pregunta', 'opciones', 'respuesta_correcta_indice']);
        });
    }
};
