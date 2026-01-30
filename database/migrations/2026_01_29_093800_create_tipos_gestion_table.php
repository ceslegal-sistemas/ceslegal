<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_gestion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        // Insertar datos iniciales
        DB::table('tipos_gestion')->insert([
            ['nombre' => 'Oficio', 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Memorando', 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Contrato', 'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Notificación', 'orden' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Suspensión', 'orden' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Societario', 'orden' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Asesoría', 'orden' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Poder', 'orden' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Acta', 'orden' => 9, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_gestion');
    }
};
