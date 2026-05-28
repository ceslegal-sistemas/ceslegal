<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correos_enviados', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('enviado_por')->constrained('users')->onDelete('cascade');
            $table->foreignId('trabajador_id')->nullable()->constrained('trabajadores')->nullOnDelete();
            $table->foreignId('proceso_id')->nullable()->constrained('procesos_disciplinarios')->nullOnDelete();
            $table->string('destinatario_nombre');
            $table->string('email_destinatario');
            $table->json('email_cc')->nullable();
            $table->string('asunto');
            $table->longText('cuerpo');
            $table->json('adjuntos')->nullable();
            $table->enum('prioridad', ['normal', 'alta', 'urgente'])->default('normal');
            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('abierto_en')->nullable();
            $table->unsignedInteger('veces_abierto')->default(0);
            $table->string('ip_apertura', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('estado', ['pendiente', 'entregado', 'leido'])->default('pendiente');
            $table->timestamps();

            $table->index('enviado_por');
            $table->index('trabajador_id');
            $table->index('proceso_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correos_enviados');
    }
};
