<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\PublicacionReglamentoInterno;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\EquivalenteFuncionalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-10-26): trazabilidad de la
 * publicación del RIT en el reporte "Equivalente Funcional" - 11º tipo de
 * evento (rit_publicacion), sourced de la tabla ya existente
 * publicaciones_reglamento_interno (Fase 1 de socialización).
 */
class EquivalenteFuncionalRitPublicacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_consulta_unificada_incluye_rit_publicacion_sin_romper_el_union(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($admin);

        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '1007568729',
            'genero' => 'masculino', 'nombres' => 'Juan Pablo', 'apellidos' => 'Rendon', 'cargo' => 'Desarrollador', 'active' => true,
        ]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'texto_completo' => 'Texto del reglamento de prueba.',
            'activo' => true,
            'version' => 1,
        ]);
        $publicacion = PublicacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', $rit->texto_completo),
            'confirmado_en' => now(),
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $servicio = app(EquivalenteFuncionalService::class);

        // Si el UNION ALL tuviera un desajuste de columnas (orden o
        // cantidad distinta a self::COLUMNAS), esto lanzaría una excepción
        // SQL antes de llegar al assert.
        $eventos = $servicio->consulta()->get();

        $evento = $eventos->firstWhere('uid', "rit_publicacion-{$publicacion->id}");

        $this->assertNotNull($evento, 'El evento rit_publicacion debía aparecer en la trazabilidad unificada.');
        $this->assertSame('rit_publicacion', $evento->tipo);
        $this->assertSame('127.0.0.1', $evento->ip);
        $this->assertSame('Juan Pablo Rendon', trim($evento->actor_nombre));
        $this->assertSame(hash('sha256', $rit->texto_completo), $evento->d1);

        $this->assertArrayHasKey('rit_publicacion', EquivalenteFuncionalService::TIPOS);
    }
}
