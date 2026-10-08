<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido de Andrés Sarmiento (reunión 2026-10-05, item 3 del backlog): cada
 * vez que se regenera el video didáctico, RitVideoDidacticoService
 * SOBREESCRIBE reglamentos_internos.video_didactico_capitulos - los
 * archivos .mp4 en sí nunca se borran (cada uno tiene un nombre único con
 * Str::random()), pero el puntero a ellos se perdía, haciéndolos
 * irrecuperables desde la interfaz. Esta tabla es ese puntero histórico -
 * append-only (nunca se sobreescribe), una fila por cada video que fue
 * REEMPLAZADO por uno nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_videos_didacticos_rit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->json('capitulos');
            $table->string('texto_rit_hash', 64)->nullable();
            $table->timestamp('generado_en')->nullable();
            $table->timestamp('archivado_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_videos_didacticos_rit');
    }
};
