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
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            // Organigrama (lista de cargos + instancia_sancionatoria, misma forma
            // que el Repeater 'cargos' del wizard "Construir RIT") extraído con IA
            // del texto del RIT - solo aplica a RIT SUBIDO o redactado libremente,
            // que no pasó por ese wizard. Mismo patrón que 'conductas_sancionables'
            // (columna JSON generada bajo demanda, no en cada carga).
            $table->json('organigrama')->nullable()->after('conductas_sancionables');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn('organigrama');
        });
    }
};
