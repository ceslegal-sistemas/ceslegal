<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario (2026-10-15, con capturas): el modal
 * "Re-generar Sanción" tenía su propio arreglo de opciones hardcodeado
 * (Llamado de Atención, Suspensión Laboral, Terminación de Contrato, No
 * Aplicar Sanción), sin 'multa' - un tipo real y válido de sanción (ver
 * SancionEnumMultaTest, y la Resolución de Impugnación real del proceso
 * PD-2026-0002 que cita "Sanción impugnada: Multa"). Cuando el proceso
 * que se re-generaba tenía tipo_sancion='multa', el Select de Filament no
 * encontraba esa clave entre sus opciones: el dropdown salía vacío (el
 * valor por defecto no calzaba con ningún option) y, al escribir "multa"
 * para buscarlo, aparecía como una opción fantasma sin traducir.
 *
 * El flujo real de "Emitir Sanción" (primera vez) ya maneja esto bien vía
 * ProcesoDisciplinarioResource::resolverEstadoSancionPorAceptacionRit() -
 * el bug estaba aislado en el segundo arreglo duplicado del modal de
 * re-generación. asegurarTipoSancionActualEsOpcion() es la red de
 * seguridad final: garantiza que la sanción YA EMITIDA del proceso siga
 * siendo seleccionable aunque el análisis fresco de la IA ya no la
 * recomiende, sin importar de qué tipo sea.
 */
class RegenerarSancionOpcionesMultaTest extends TestCase
{
    public function test_agrega_multa_si_el_tipo_actual_es_multa_y_no_esta_entre_las_opciones(): void
    {
        $opciones = [
            'llamado_atencion' => 'Llamado de Atención',
            'suspension' => 'Suspensión Laboral',
            'terminacion' => 'Terminación de Contrato',
            'no_sancion' => 'No Aplicar Sanción',
        ];

        $resultado = ProcesoDisciplinarioResource::asegurarTipoSancionActualEsOpcion($opciones, 'multa');

        $this->assertArrayHasKey('multa', $resultado);
        $this->assertSame('Multa', $resultado['multa']);
    }

    public function test_no_duplica_ni_sobrescribe_si_el_tipo_actual_ya_esta_entre_las_opciones(): void
    {
        $opciones = ['multa' => 'Multa', 'no_sancion' => 'No Aplicar Sanción'];

        $resultado = ProcesoDisciplinarioResource::asegurarTipoSancionActualEsOpcion($opciones, 'multa');

        $this->assertSame($opciones, $resultado);
    }

    public function test_no_hace_nada_sin_tipo_de_sancion_actual(): void
    {
        $opciones = ['llamado_atencion' => 'Llamado de Atención'];

        $resultado = ProcesoDisciplinarioResource::asegurarTipoSancionActualEsOpcion($opciones, null);

        $this->assertSame($opciones, $resultado);
    }

    public function test_funciona_para_cualquier_tipo_no_solo_multa(): void
    {
        $opciones = ['no_sancion' => 'No Aplicar Sanción'];

        $resultado = ProcesoDisciplinarioResource::asegurarTipoSancionActualEsOpcion($opciones, 'suspension');

        $this->assertArrayHasKey('suspension', $resultado);
        $this->assertSame('Suspensión Laboral', $resultado['suspension']);
    }
}
