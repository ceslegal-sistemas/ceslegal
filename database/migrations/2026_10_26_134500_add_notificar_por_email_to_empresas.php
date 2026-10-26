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
        Schema::table('empresas', function (Blueprint $table) {
            // Mismo patrón ya usado en trabajadores.notificar_por_email
            // (migración 2026_10_19_203000): default true porque el correo
            // ya era el canal "garantizado" antes de este selector explícito
            // - este campo solo hace visible/editable una preferencia que ya
            // existía implícita en el código.
            $table->boolean('notificar_por_email')->default(true)->after('notificar_por_whatsapp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('notificar_por_email');
        });
    }
};
