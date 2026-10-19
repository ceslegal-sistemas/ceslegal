<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 de WhatsApp (docs/superpowers/specs/2026-10-19-whatsapp-fase1-otp-descargos-design.md):
 * preferencia de canal + evidencia de opt-in del trabajador, para el OTP de
 * Descargos. whatsapp_consentimiento_en/_ip siguen el mismo patrón de
 * evidencia que ya usan la aceptación del RIT y las firmas de descargos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->boolean('notificar_por_email')->default(true)->after('telefono');
            $table->boolean('notificar_por_whatsapp')->default(false)->after('notificar_por_email');
            $table->timestamp('whatsapp_consentimiento_en')->nullable()->after('notificar_por_whatsapp');
            $table->string('whatsapp_consentimiento_ip', 45)->nullable()->after('whatsapp_consentimiento_en');
        });
    }

    public function down(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->dropColumn([
                'notificar_por_email',
                'notificar_por_whatsapp',
                'whatsapp_consentimiento_en',
                'whatsapp_consentimiento_ip',
            ]);
        });
    }
};
