<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nuevo texto del mensaje de bloqueo cuando el trabajador insiste en no
 * entender el RIT (redacción del equipo legal, 2026-10-08). Admite los
 * marcadores :empresa, :trabajador y :fecha.
 *
 * Solo reemplaza el valor guardado si todavía es UNO DE LOS TEXTOS CONOCIDOS
 * anteriores - si alguien ya lo editó a mano desde Textos Configurables, no se
 * pisa su cambio. La descripción (que documenta los marcadores) sí se actualiza.
 */
return new class extends Migration
{
    public function up(): void
    {
        $anteriores = [
            'Te hemos entregado de manera didáctica, a través de un video, un resumen y el Reglamento Interno de Trabajo completo con todos los cambios realizados, la información necesaria para comprenderlo. Aun así, continúas indicando que no entiendes el Reglamento Interno de Trabajo de :empresa, por lo que no es posible continuar con tu proceso de socialización en este momento. Hemos informado de esta situación al área de Recursos Humanos de tu empresa para que tomen las decisiones que correspondan.',
            'Te hemos entregado de manera didáctica, a través de un video, la explicación y socialización del Reglamento Interno de Trabajo completo con todos los cambios realizados y la información necesaria para comprenderlo. Aun así, continúas indicando que no entiendes el Reglamento Interno de Trabajo de la empresa, por lo que no es posible continuar con tu proceso de socialización en este momento. Por consiguiente, hemos informado de esta situación al área de Recursos Humanos de tu empresa para que tomen las decisiones que correspondan.',
        ];

        $fila = DB::table('configuraciones_textos')->where('clave', 'mensaje_no_comprendio_bloqueado');

        $cambios = [
            'descripcion' => 'Mensaje final cuando el trabajador insiste por segunda vez en no entender el RIT - bloquea el proceso y se envía a RRHH. Marcadores: :empresa, :trabajador, :fecha.',
            'updated_at' => now(),
        ];

        if ((clone $fila)->whereIn('valor', $anteriores)->exists()) {
            $cambios['valor'] = config('ces.mensaje_no_comprendio_bloqueado');
        }

        $fila->update($cambios);
    }

    public function down(): void
    {
        // Sin reversa: el texto anterior no se restaura (puede haber sido editado).
    }
};
