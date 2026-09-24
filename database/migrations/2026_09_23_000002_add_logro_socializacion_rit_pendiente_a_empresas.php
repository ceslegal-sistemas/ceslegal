<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Puente cross-sesion: el logro se otorga desde la sesion PUBLICA
            // anonima del trabajador (SocializacionRit::aceptarReglamento()),
            // sin ningun usuario del panel viendo la pantalla en ese momento.
            // Se marca aqui y Dashboard::mount() lo revisa/limpia la
            // proxima vez que CUALQUIER usuario del panel de esta empresa
            // entre - NO es lo mismo que LogroDescargosService::celebrar()
            // (esa dispara en vivo, misma request) ni que el flag de sesion
            // celebrar_registro_rit (no cruza de una sesion a otra).
            $table->timestamp('logro_socializacion_rit_pendiente_celebrar')->nullable()->after('numero_empleados');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('logro_socializacion_rit_pendiente_celebrar');
        });
    }
};
