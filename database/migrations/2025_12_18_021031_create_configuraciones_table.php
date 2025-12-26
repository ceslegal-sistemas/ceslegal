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
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 255)->unique();
            $table->text('valor');
            $table->enum('tipo', ['text', 'number', 'boolean', 'json']);
            $table->text('descripcion')->nullable();
            $table->string('categoria', 100);
            $table->boolean('editable')->default(true);
            $table->timestamps();

            $table->index('categoria', 'idx_categoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
