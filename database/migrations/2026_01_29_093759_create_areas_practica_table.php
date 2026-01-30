<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas_practica', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('color', 20)->default('gray');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        // Insertar datos iniciales
        DB::table('areas_practica')->insert([
            ['nombre' => 'Disciplinario', 'color' => 'danger', 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Documental', 'color' => 'info', 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Acompañamiento', 'color' => 'warning', 'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Contractual', 'color' => 'success', 'orden' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Societario', 'color' => 'primary', 'orden' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('areas_practica');
    }
};
