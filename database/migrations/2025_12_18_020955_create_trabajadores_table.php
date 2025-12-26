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
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('restrict');
            $table->enum('tipo_documento', ['CC', 'CE', 'TI', 'PASS']);
            $table->string('numero_documento', 50);
            $table->string('nombres', 255);
            $table->string('apellidos', 255);
            $table->string('cargo', 255);
            $table->date('fecha_ingreso')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->text('direccion')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tipo_documento', 'numero_documento']);
            $table->index('empresa_id', 'idx_empresa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trabajadores');
    }
};
