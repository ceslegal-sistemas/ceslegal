<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Logro "Constructor de RIT" (2026-09-28) - se otorga al comenzar a
 * construir el RIT con el wizard de IA (handleRecordCreation()), no al
 * terminar de generarlo (eso corre en un job aparte). La lógica de
 * otorgamiento en sí ya está probada a fondo en LogroSimpleServiceTest;
 * aquí solo se verifica por código fuente que el gancho está conectado en
 * el lugar correcto - reconstruir el wizard completo (muchos pasos y
 * campos obligatorios) solo para esto sería frágil sin aportar más
 * confianza real.
 */
class LogroConstructorRitTest extends TestCase
{
    public function test_el_gancho_esta_conectado_en_handleRecordCreation(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php'));

        $posicionGancho = strpos($fuente, "otorgarUnicoSiNoExiste(\$empresa, 'Constructor de RIT')");
        $posicionMetodo = strpos($fuente, 'function handleRecordCreation(array $data): Model');
        $posicionDispatch = strpos($fuente, 'GenerarTextoRITJob::dispatch($record, Auth::id());');

        $this->assertNotFalse($posicionGancho, 'No se encontró el gancho del logro "Constructor de RIT".');
        $this->assertGreaterThan($posicionMetodo, $posicionGancho, 'El gancho debe estar dentro de handleRecordCreation().');
        $this->assertLessThan($posicionDispatch, $posicionGancho, 'El gancho debe otorgarse antes de despachar el job (o al menos en el mismo método).');
    }
}
