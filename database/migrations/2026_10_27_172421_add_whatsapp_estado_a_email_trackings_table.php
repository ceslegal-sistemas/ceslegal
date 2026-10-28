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
            // Pedido del usuario (2026-10-27): acuse de que el trabajador ya
            // leyó el mensaje enviado por WhatsApp, igual que el pixel de
            // apertura ya existente para correo. whatsapp_message_id es el
            // wamid que Meta devuelve al enviar (ver WhatsAppService::enviar())
            // - se usa para correlacionar el webhook de estado (sent/
            // delivered/read/failed) con este registro.
            $table->string('whatsapp_message_id')->nullable()->after('canales');
            $table->string('whatsapp_estado')->nullable()->after('whatsapp_message_id');
            $table->timestamp('whatsapp_entregado_en')->nullable()->after('whatsapp_estado');
            $table->timestamp('whatsapp_leido_en')->nullable()->after('whatsapp_entregado_en');
            $table->index('whatsapp_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_trackings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_message_id', 'whatsapp_estado', 'whatsapp_entregado_en', 'whatsapp_leido_en']);
        });
    }
};
