<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedido de Andrés Sarmiento (reunión 2026-10-05): antes de disparar el
 * correo a RRHH en el 2do "no entendí", mostrar un paso de confirmación
 * intermedio - evita que un clic accidental escale el caso. Mismo patrón
 * ya usado para los otros 2 mensajes de este flujo (2026_10_03_150100).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones_textos')->insert([
            'clave' => 'aviso_no_comprendio_confirmacion',
            'grupo' => 'rit',
            'descripcion' => 'Advertencia de confirmación antes del 2do "no entendí" - el trabajador debe confirmar de forma consciente que no comprendió, con opción de retractarse. Marcador: :empresa.',
            'valor' => config('ces.aviso_no_comprendio_confirmacion'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('configuraciones_textos')->where('clave', 'aviso_no_comprendio_confirmacion')->delete();
    }
};
