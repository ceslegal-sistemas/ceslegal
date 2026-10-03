<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escalamiento a RRHH si el trabajador insiste en "no entendí" el RIT
 * (pedido de Andrés Sarmiento, reunión 2026-10-03). Cada vez que el
 * trabajador declara explícitamente que no entendió (botón manual) o falla
 * la misma pregunta del quiz muchas veces seguidas, se registra un
 * "rechazo" - a la segunda vez se bloquea el proceso y se avisa a RRHH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rechazos_comprension_rit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->onDelete('cascade');
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->onDelete('cascade');
            $table->string('texto_rit_hash', 64);
            $table->string('origen', 30); // 'boton_manual' | 'fallo_quiz_repetido'
            $table->timestamps();

            $table->index(['trabajador_id', 'reglamento_interno_id'], 'rechazos_rit_trabajador_rit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rechazos_comprension_rit');
    }
};
