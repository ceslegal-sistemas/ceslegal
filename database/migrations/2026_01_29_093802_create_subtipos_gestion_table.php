<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtipos_gestion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        // Insertar datos iniciales
        DB::table('subtipos_gestion')->insert([
            ['nombre' => 'Documento', 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Especial', 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Acta de Asamblea', 'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Contestación', 'orden' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Preaviso', 'orden' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Memorial', 'orden' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Presencial', 'orden' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Virtual', 'orden' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subtipos_gestion');
    }
};
