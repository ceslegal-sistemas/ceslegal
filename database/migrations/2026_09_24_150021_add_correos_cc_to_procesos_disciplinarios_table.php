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
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            // Correos a notificar (informativamente, no como CC tecnico real)
            // cuando se envia la citacion a descargos - ej. un jefe o RRHH que
            // debe enterarse pero no es el destinatario de la citacion formal.
            $table->json('correos_cc')->nullable()->after('modalidad_descargos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn('correos_cc');
        });
    }
};
