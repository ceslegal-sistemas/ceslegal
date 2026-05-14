<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actas_inspeccion', function (Blueprint $table) {
            $table->id();
            $table->string('numero_acta', 30)->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_cierre')->nullable();
            $table->text('objetivo');
            $table->text('tema')->nullable();
            $table->json('asistentes')->nullable();    // [{nombre, cargo}]
            $table->json('compromisos')->nullable();   // [{compromiso, responsable}]
            $table->text('hallazgos')->nullable();
            $table->text('observaciones')->nullable();
            $table->enum('estado', ['borrador', 'finalizada'])->default('borrador');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actas_inspeccion');
    }
};
