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
        Schema::table('email_trackings', function (Blueprint $table) {
            // Pedido del usuario (2026-10-27): el acuse de recibido debe reflejar
            // por qué canal(es) se envió realmente, no solo si el correo fue
            // leído - desde que "Re-enviar Citación" y afines permiten elegir
            // correo/WhatsApp/ambos, un registro puede no tener envío de correo
            // en absoluto. Null = registros históricos (todos eran correo antes
            // de esta fecha), se interpreta como ['email'].
            $table->json('canales')->nullable()->after('email_destinatario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_trackings', function (Blueprint $table) {
            $table->dropColumn('canales');
        });
    }
};
