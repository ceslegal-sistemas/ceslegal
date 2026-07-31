<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corregido antes de correr: el nombre de tabla real es
     * 'procesos_disciplinarios' (plural "procesos"), confirmado en
     * ProcesoDisciplinario::$table - el archivo original asumía
     * 'proceso_disciplinarios' (singular) y habría fallado al ejecutarse.
     */
    public function up(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            // Guarda el JSON completo devuelto por
            // IADescargoService::clasificarIncidente() - gravedad estimada,
            // certeza, nivel de interrogatorio mínimo exigido para la
            // diligencia, factores de riesgo y elementos faltantes si la
            // información aportada al citar fue insuficiente.
            //
            // Se persiste como TEXTO PLANO (el formulario ya hace
            // json_encode() antes de guardarlo vía el campo Hidden) - a
            // propósito NO se recomienda castear esta columna como 'array' en
            // el modelo Eloquent salvo que ajustes el formulario para enviar
            // el array sin codificar, porque un cast 'array' sobre un valor
            // que ya llega como string JSON puede terminar
            // doble-codificándolo. Si prefieres el cast, decodifica el valor
            // en el modelo (mutator) en vez de hacerlo en el formulario.
            $table->text('clasificacion_incidente_ia')->nullable()->after('hechos');
        });
    }

    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn('clasificacion_incidente_ia');
        });
    }
};
