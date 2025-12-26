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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social', 255);
            $table->string('nit', 50)->unique();
            $table->text('direccion');
            $table->string('telefono', 50);
            $table->string('email_contacto', 255);
            $table->string('ciudad', 100);
            $table->string('departamento', 100);
            $table->string('representante_legal', 255);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
