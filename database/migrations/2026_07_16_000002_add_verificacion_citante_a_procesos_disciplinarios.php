<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación fotográfica de quien CREA la citación de descargos (distinta de
 * autorizador_nombre/autorizador_cargo/foto_autorizador_*, que registran a quien
 * autoriza la SANCIÓN más adelante en el mismo proceso). Equivalencia funcional de
 * firma manuscrita (Ley 527/1999 Art. 7-8, Decreto 2364/2012) para el momento de
 * iniciar el proceso disciplinario contra el trabajador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->string('citante_nombre')->nullable();
            $table->string('citante_cargo')->nullable();
            $table->string('foto_citante_path')->nullable();
            $table->timestamp('foto_citante_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn(['citante_nombre', 'citante_cargo', 'foto_citante_path', 'foto_citante_en']);
        });
    }
};
