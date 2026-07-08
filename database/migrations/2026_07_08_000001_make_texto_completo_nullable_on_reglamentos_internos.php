<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite texto_completo NULL en reglamentos_internos.
 *
 * ReglamentoInternoService::procesarDocumento() ya contempla que la extracción
 * de texto falle con gracia (p. ej. un PDF escaneado sin texto seleccionable) y
 * guarda 'texto_completo' => null para registrar el RIT de todas formas. La
 * columna era NOT NULL, lo que provocaba un error 23000 al subir esos PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->longText('texto_completo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->longText('texto_completo')->nullable(false)->change();
        });
    }
};
