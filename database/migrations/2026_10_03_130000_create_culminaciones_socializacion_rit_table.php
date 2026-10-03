<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Botón manual "Culminar Socialización del RIT" (pedido de Andrés Sarmiento,
 * reunión 2026-10-03): declaración del ADMIN de que ya notificó a todos los
 * trabajadores y que la socialización fue aceptada/realizada, con su propia
 * selfie de verificación - evidencia distinta de PublicacionReglamentoInterno
 * y AceptacionReglamentoInterno (esas son evidencia del TRABAJADOR; esta es
 * evidencia de la EMPRESA cerrando el proceso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('culminaciones_socializacion_rit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('texto_rit_hash', 64);
            $table->string('foto_admin_path')->nullable();
            $table->timestamp('declarado_en');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Sin constraint unico, mismo criterio que PublicacionReglamentoInterno
            // y AceptacionReglamentoInterno: cada declaracion es un registro
            // historico nuevo (si el RIT vuelve a cambiar, se vuelve a culminar).
            $table->index(['empresa_id', 'reglamento_interno_id'], 'culminaciones_rit_empresa_rit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('culminaciones_socializacion_rit');
    }
};
