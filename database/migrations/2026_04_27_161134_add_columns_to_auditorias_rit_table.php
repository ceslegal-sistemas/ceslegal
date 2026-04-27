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
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->foreignId('empresa_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('reglamento_interno_id')->after('empresa_id')->nullable()->constrained('reglamentos_internos')->nullOnDelete();
            $table->enum('estado', ['pendiente', 'procesando', 'completado', 'error'])->after('reglamento_interno_id')->default('pendiente');
            $table->json('secciones')->after('estado')->nullable();
            $table->text('resumen_general')->after('secciones')->nullable();
            $table->unsignedTinyInteger('score')->after('resumen_general')->nullable();
            $table->text('mensaje_error')->after('score')->nullable();
            $table->string('fuente')->after('mensaje_error')->default('sistema');
            $table->index(['empresa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropForeign(['reglamento_interno_id']);
            $table->dropColumn(['empresa_id', 'reglamento_interno_id', 'estado', 'secciones', 'resumen_general', 'score', 'mensaje_error', 'fuente']);
        });
    }
};
