<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicaciones_reglamento_interno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->onDelete('cascade');
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->onDelete('cascade');
            $table->string('texto_rit_hash', 64);
            $table->timestamp('confirmado_en');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Sin constraint unico (mismo criterio que aceptaciones_reglamento_interno,
            // ver migracion 2026_09_28_095031_add_evidencia_juridica...): cada
            // confirmacion es un registro historico nuevo, nunca se sobreescribe.
            $table->index(['trabajador_id', 'reglamento_interno_id'], 'publicaciones_rit_trabajador_rit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicaciones_reglamento_interno');
    }
};
