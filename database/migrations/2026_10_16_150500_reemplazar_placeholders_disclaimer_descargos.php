<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mismo hallazgo que disclaimer_autorizador (ver migración
 * 2026_10_16_150000_...): Carlos (jefe) amplió disclaimer_descargos
 * directo en producción con un párrafo de "Aviso de privacidad" que
 * incluye dos placeholders literales sin terminar - "[NOMBRE DEL
 * EMPLEADOR]" y "[URL]" - que además quedarían fijos para siempre (no
 * reflejarían la empresa real de cada trabajador ni la URL real del
 * sitio). Se reemplazan por los marcadores reales :empresa (ya existía y
 * ya se resolvía en FormularioDescargos.php) y :url_politica (nuevo,
 * resuelto con url('/politica-tratamiento-datos')).
 *
 * Idempotente: solo toca la fila si todavía contiene los placeholders
 * literales.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones_textos')
            ->where('clave', 'disclaimer_descargos')
            ->where('valor', 'like', '%[NOMBRE DEL EMPLEADOR]%')
            ->update([
                'valor' => DB::raw("REPLACE(valor, '[NOMBRE DEL EMPLEADOR]', ':empresa')"),
            ]);

        DB::table('configuraciones_textos')
            ->where('clave', 'disclaimer_descargos')
            ->where('valor', 'like', '%[URL]%')
            ->update([
                'valor' => DB::raw("REPLACE(valor, '[URL]', ':url_politica')"),
            ]);
    }

    public function down(): void
    {
        // Solo revierte :url_politica (inequívoco: no existía antes de esta
        // migración). No revierte :empresa -> "[NOMBRE DEL EMPLEADOR]": ese
        // marcador YA existía en el texto desde la semilla original (sección
        // 5, "la empresa :empresa"), así que no hay forma de distinguir cuál
        // ocurrencia vino de este reemplazo sin romper la otra.
        DB::table('configuraciones_textos')
            ->where('clave', 'disclaimer_descargos')
            ->where('valor', 'like', '%:url_politica%')
            ->update([
                'valor' => DB::raw("REPLACE(valor, ':url_politica', '[URL]')"),
            ]);
    }
};
