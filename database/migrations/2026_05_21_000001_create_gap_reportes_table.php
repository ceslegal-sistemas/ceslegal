<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gap_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auditoria_rit_id')->constrained('auditorias_rit')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('estado', 20)->default('generando'); // generando | completado | error
            $table->string('ruta_ejecutivo')->nullable();
            $table->string('ruta_tecnico')->nullable();
            $table->unsignedTinyInteger('score_snapshot')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamps();

            $table->unique('auditoria_rit_id');
            $table->index(['empresa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gap_reportes');
    }
};
