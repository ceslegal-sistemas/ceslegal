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
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipo', [
                'apertura',
                'descargos_pendientes',
                'descargos_realizados',
                'termino_vencido',
                'sancion_emitida',
                'impugnacion_realizada',
                'cerrado',
                'contrato_generado'
            ]);
            $table->string('titulo', 255);
            $table->text('mensaje');
            $table->string('relacionado_tipo', 255)->nullable();
            $table->unsignedBigInteger('relacionado_id')->nullable();
            $table->boolean('leida')->default(false);
            $table->dateTime('fecha_lectura')->nullable();
            $table->enum('prioridad', ['baja', 'media', 'alta', 'urgente'])->default('media');
            $table->timestamps();

            $table->index(['user_id', 'leida'], 'idx_user_leida');
            $table->index('tipo', 'idx_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
