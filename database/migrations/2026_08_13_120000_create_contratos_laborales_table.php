<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_laborales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores');
            $table->foreignId('empresa_id')->constrained('empresas');

            $table->enum('tipo', ['fijo', 'indefinido', 'obra_labor', 'ocasional']);
            $table->decimal('salario', 12, 2);
            $table->string('periodicidad_pago');
            $table->string('jornada')->nullable();
            $table->text('funciones_cargo');

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('descripcion_obra')->nullable();

            $table->string('eps')->nullable();
            $table->string('arl')->nullable();
            $table->string('fondo_pension')->nullable();
            $table->string('caja_compensacion')->nullable();

            $table->text('clausulas_generadas')->nullable();
            $table->json('articulos_cst_citados')->nullable();

            $table->enum('estado', ['borrador', 'generado', 'activo', 'modificado', 'terminado'])
                ->default('borrador');
            $table->string('documento_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_laborales');
    }
};
