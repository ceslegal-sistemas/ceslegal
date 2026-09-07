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
        Schema::table('documentos_legales', function (Blueprint $table) {
            $table->date('fecha_expedicion')->nullable()->after('referencia');
            $table->string('fuente_emisor')->nullable()->after('fecha_expedicion');
            // nullOnDelete (no cascade/restrict): si el documento VIEJO se
            // borra, el que lo reemplaza no debe bloquearse ni borrarse en
            // cascada - solo pierde el vínculo. Mismo criterio aprendido del
            // bug de FK RESTRICT en sugerencias_actualizacion_rit
            // (2026-09-07): nunca dejar que borrar A tumbe o bloquee B solo
            // porque B lo referencia.
            $table->foreignId('reemplaza_a_id')->nullable()->after('fuente_emisor')
                ->constrained('documentos_legales')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_legales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reemplaza_a_id');
            $table->dropColumn(['fecha_expedicion', 'fuente_emisor']);
        });
    }
};
