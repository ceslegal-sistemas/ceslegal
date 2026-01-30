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
        Schema::create('informes_juridicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->enum('mes', [
                'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
            ]);
            $table->enum('area_practica', [
                'disciplinario',
                'documental',
                'acompanamiento',
                'contractual',
                'societario',
                'otro'
            ]);
            $table->string('tipo_gestion', 100);
            $table->string('subtipo', 100)->nullable();
            $table->text('descripcion');
            $table->enum('estado', ['entregado', 'pendiente', 'en_proceso'])->default('pendiente');
            $table->text('observacion')->nullable();
            $table->unsignedInteger('tiempo_minutos')->default(0);
            $table->timestamps();

            $table->index(['empresa_id', 'anio', 'mes']);
            $table->index('created_by');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informes_juridicos');
    }
};
