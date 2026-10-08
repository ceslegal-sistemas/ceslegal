<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido de Andrés Sarmiento (reunión 2026-10-05, reporte "Equivalente
 * Funcional"): a diferencia de ProcesoDisciplinario (que ya captura
 * autorizador_nombre/foto_autorizador_path para la sanción), el RIT nunca
 * registró QUIÉN lo autorizó. Esta tabla es ese registro - append-only
 * (una fila por cada vez que un funcionario autoriza una versión del RIT),
 * nunca se sobreescribe, para poder encontrar TODAS las autorizaciones de
 * un mismo funcionario a través de múltiples versiones/actualizaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autorizaciones_reglamento_interno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('autorizador_nombre');
            $table->string('autorizador_cargo');
            $table->string('foto_autorizador_path')->nullable();
            $table->timestamp('foto_autorizador_en')->nullable();
            $table->timestamp('disclaimer_datos_autorizador_en')->nullable();
            $table->string('disclaimer_datos_autorizador_ip', 45)->nullable();
            $table->string('texto_rit_hash', 64);
            $table->longText('texto_rit_snapshot')->nullable();
            $table->timestamps();

            $table->index(['autorizador_nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorizaciones_reglamento_interno');
    }
};
