<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Centraliza 3 disclaimers que vivían hardcodeados directo en Blade (hallazgo
 * de la auditoría pedida por Andrés Sarmiento, 2026-10-03: solo
 * disclaimer_descargos estaba en configuraciones_textos, el resto no) - ahora
 * todos son editables desde ConfiguracionTextoResource igual que ese.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        DB::table('configuraciones_textos')->insert([
            [
                'clave' => 'disclaimer_autorizador',
                'grupo' => 'autorizador',
                'descripcion' => 'Autorización de tratamiento de datos (Ley 1581 de 2012) que acepta el AUTORIZADOR/citante de la empresa antes de tomar su foto de verificación - usado en Emitir Sanción, Crear Proceso Disciplinario y Aceptación de RIT Mejorado.',
                'valor' => config('ces.disclaimer_autorizador'),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'clave' => 'disclaimer_rit_publicacion',
                'grupo' => 'rit',
                'descripcion' => 'Declaración del trabajador en la Fase 1 (Publicación) de la socialización del RIT - confirma que fue informado, no que lo entendió. Marcador: :empresa.',
                'valor' => config('ces.disclaimer_rit_publicacion'),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'clave' => 'disclaimer_rit_socializacion',
                'grupo' => 'rit',
                'descripcion' => 'Declaración del trabajador en la Fase 2 (Socialización) de la socialización del RIT - "leí y entendí". Marcador: :empresa.',
                'valor' => config('ces.disclaimer_rit_socializacion'),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('configuraciones_textos')->whereIn('clave', [
            'disclaimer_autorizador',
            'disclaimer_rit_publicacion',
            'disclaimer_rit_socializacion',
        ])->delete();
    }
};
