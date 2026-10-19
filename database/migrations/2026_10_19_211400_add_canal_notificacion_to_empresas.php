<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 de WhatsApp (docs/superpowers/specs/2026-10-19-whatsapp-fase1-otp-descargos-design.md):
 * mismo mecanismo de opt-in del trabajador, pero a nivel empresa - RRHH
 * elige si quiere los avisos del sistema (ej. "no entendió el RIT dos
 * veces") también por WhatsApp, al número que ya tenían registrado
 * (empresa.telefono, ya existía). El correo de contacto sigue siendo el
 * canal garantizado, sin cambios - esto solo agrega WhatsApp como extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('notificar_por_whatsapp')->default(false)->after('telefono');
            $table->timestamp('whatsapp_consentimiento_en')->nullable()->after('notificar_por_whatsapp');
            $table->string('whatsapp_consentimiento_ip', 45)->nullable()->after('whatsapp_consentimiento_en');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['notificar_por_whatsapp', 'whatsapp_consentimiento_en', 'whatsapp_consentimiento_ip']);
        });
    }
};
