<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('plan'); // basico, pro, firma
            $table->enum('ciclo_facturacion', ['mensual', 'anual'])->default('mensual');
            $table->enum('estado', ['trial', 'activa', 'pendiente_pago', 'vencida', 'cancelada'])->default('pendiente_pago');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            $table->string('payment_reference')->nullable()->unique(); // CES-{empresa_id}-{timestamp}
            $table->string('payment_transaction_id')->nullable();
            $table->decimal('monto_pagado', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};
