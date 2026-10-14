<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Tests\TestCase;

/**
 * Pedido del usuario (2026-10-14, captura de "Historial de Descargos"):
 * mientras un proceso está en estado "Apertura", la citación todavía se
 * está generando y enviando al trabajador en segundo plano
 * (GenerarYEnviarCitacionJob, ver CreateProcesoDisciplinario::afterCreate()).
 * Antes de este cambio, la columna "Generación de Sanción" quedaba
 * completamente vacía en esa fila, sin ninguna señal de que algo seguía en
 * curso - el cliente no sabía si debía esperar o si algo había fallado.
 *
 * Probado directo sobre ProcesoDisciplinarioResource::badgeGeneracionSancion()
 * (mismo criterio que otros tests de este recurso: la tabla usa
 * ->deferLoading(), así que un GET HTTP normal no renderiza ninguna fila -
 * solo el esqueleto - y no sirve para verificar contenido de celdas).
 */
class ListProcesoDisciplinariosBadgeGenerandoEnAperturaTest extends TestCase
{
    public function test_apertura_sin_sancion_muestra_generando(): void
    {
        $badge = ProcesoDisciplinarioResource::badgeGeneracionSancion(null, 'apertura');

        $this->assertSame('Generando...', $badge['label']);
        $this->assertSame('warning', $badge['color']);
        $this->assertSame('heroicon-o-arrow-path', $badge['icon']);
    }

    public function test_cerrado_sin_sancion_no_muestra_nada(): void
    {
        $badge = ProcesoDisciplinarioResource::badgeGeneracionSancion(null, 'cerrado');

        $this->assertNull($badge['label']);
    }

    public function test_descargos_pendientes_sin_sancion_no_muestra_nada(): void
    {
        // El proceso ya salió de "apertura" (la citación terminó de
        // generarse y enviarse) pero aún no ha llegado a la fase de
        // sanción - no debe seguir mostrando "Generando...".
        $badge = ProcesoDisciplinarioResource::badgeGeneracionSancion(null, 'descargos_pendientes');

        $this->assertNull($badge['label']);
    }

    public function test_sancion_procesando_sigue_mostrando_generando_sin_importar_el_estado(): void
    {
        $badge = ProcesoDisciplinarioResource::badgeGeneracionSancion('procesando', 'sancion_emitida');

        $this->assertSame('Generando...', $badge['label']);
        $this->assertSame('warning', $badge['color']);
    }

    public function test_sancion_completada_no_se_pisa_por_el_estado_apertura(): void
    {
        $badge = ProcesoDisciplinarioResource::badgeGeneracionSancion('completado', 'apertura');

        $this->assertSame('Completado', $badge['label']);
        $this->assertSame('success', $badge['color']);
    }
}
