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
        Schema::create('email_trackings', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('tipo_correo', 50); // citacion, sancion
            $table->foreignId('proceso_id')->constrained('procesos_disciplinarios')->onDelete('cascade');
            $table->foreignId('trabajador_id')->constrained('trabajadores')->onDelete('cascade');
            $table->string('email_destinatario');
            $table->timestamp('enviado_en');
            $table->timestamp('abierto_en')->nullable();
            $table->unsignedInteger('veces_abierto')->default(0);
            $table->string('ip_apertura', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['proceso_id', 'tipo_correo']);
            $table->index('abierto_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_trackings');
    }
};
