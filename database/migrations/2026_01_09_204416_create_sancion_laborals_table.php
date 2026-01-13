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
        Schema::create('sancion_laborals', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_falta', ['leve', 'grave'])->comment('Tipo de falta según reglamento');
            $table->text('descripcion')->comment('Descripción completa de la conducta');
            $table->string('nombre_claro')->comment('Nombre corto en lenguaje claro');
            $table->string('tipo_sancion')->comment('Tipo de sanción: llamado_atencion, suspension, terminacion');
            $table->integer('dias_suspension_min')->nullable()->comment('Días mínimos de suspensión (si aplica)');
            $table->integer('dias_suspension_max')->nullable()->comment('Días máximos de suspensión (si aplica)');
            $table->boolean('activa')->default(true)->comment('Si la sanción está activa');
            $table->integer('orden')->default(0)->comment('Orden de visualización');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sancion_laborals');
    }
};
