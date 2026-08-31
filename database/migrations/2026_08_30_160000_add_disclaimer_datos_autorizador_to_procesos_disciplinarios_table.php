<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormaliza la aceptación de la autorización de tratamiento de datos
 * personales (Ley 1581 de 2012) del AUTORIZADOR de la sanción.
 *
 * Ya quedaba registrada en la traza (sancion_process_events, evento
 * 'disclaimer_datos_aceptado'), pero consultarla exigía recorrer la traza.
 * El lado del trabajador siempre tuvo sus propias columnas equivalentes
 * (diligencias_descargos.disclaimer_aceptado_en / disclaimer_ip): esto
 * empareja ambos lados y deja el consentimiento junto al resto de datos
 * del autorizador (autorizador_nombre, foto_autorizador_en...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->timestamp('disclaimer_datos_autorizador_en')->nullable()->after('foto_autorizador_en');
            // 45: cabe una IPv6 completa y una IPv4 mapeada en IPv6.
            $table->string('disclaimer_datos_autorizador_ip', 45)->nullable()->after('disclaimer_datos_autorizador_en');
        });
    }

    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn(['disclaimer_datos_autorizador_en', 'disclaimer_datos_autorizador_ip']);
        });
    }
};
