<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carlos (jefe) redactó la política completa de disclaimer_autorizador
 * directo en producción (ConfiguracionTextoResource) y escribió "Última
 * actualización: [actualizada]" como placeholder literal sin terminar -
 * texto plano que además quedaría desactualizado en cada edición futura.
 * Se reemplaza por el marcador real :fecha_actualizacion, que
 * webcam-autorizador.blade.php ahora resuelve con la fecha real de la
 * última edición del propio registro (updated_at).
 *
 * Idempotente: solo toca la fila si todavía contiene el placeholder
 * literal, así que no afecta entornos donde el texto sigue siendo el
 * seed corto original (local/test) ni si ya se corrigió antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones_textos')
            ->where('clave', 'disclaimer_autorizador')
            ->where('valor', 'like', '%[actualizada]%')
            ->update([
                'valor' => DB::raw("REPLACE(valor, '[actualizada]', ':fecha_actualizacion')"),
            ]);
    }

    public function down(): void
    {
        DB::table('configuraciones_textos')
            ->where('clave', 'disclaimer_autorizador')
            ->where('valor', 'like', '%:fecha_actualizacion%')
            ->update([
                'valor' => DB::raw("REPLACE(valor, ':fecha_actualizacion', '[actualizada]')"),
            ]);
    }
};
