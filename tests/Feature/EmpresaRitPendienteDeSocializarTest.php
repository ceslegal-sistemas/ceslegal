<?php

namespace Tests\Feature;

use App\Models\CulminacionSocializacionRit;
use App\Models\Empresa;
use App\Models\PublicacionReglamentoInterno;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Empresa::ritPendienteDeSocializar() - señal usada por el popup bloqueante
 * al hacer login (pedido de Andrés Sarmiento, reunión 2026-09-28).
 */
class EmpresaRitPendienteDeSocializarTest extends TestCase
{
    use RefreshDatabase;

    private function crearTrabajador(Empresa $empresa, string $numeroDocumento = '123456789'): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => $numeroDocumento,
            'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Operario', 'active' => true,
        ]);
    }

    public function test_sin_rit_activo_no_esta_pendiente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        $this->assertFalse($empresa->ritPendienteDeSocializar());
    }

    public function test_fase_publicacion_pendiente_si_falta_un_trabajador_por_confirmar(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $this->crearTrabajador($empresa);

        $this->assertTrue($empresa->ritPendienteDeSocializar());
    }

    public function test_fase_publicacion_no_pendiente_si_todos_confirmaron(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        PublicacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'confirmado_en' => now(),
            'texto_rit_hash' => hash('sha256', 'v1'),
        ]);

        $this->assertFalse($empresa->ritPendienteDeSocializar());
    }

    public function test_fase_publicacion_no_pendiente_sin_trabajadores(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);

        $this->assertFalse($empresa->ritPendienteDeSocializar());
    }

    public function test_fase_socializacion_pendiente_sin_culminacion_vigente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);

        $this->assertTrue($empresa->ritPendienteDeSocializar());
    }

    public function test_fase_socializacion_no_pendiente_con_culminacion_vigente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        CulminacionSocializacionRit::create([
            'empresa_id' => $empresa->id, 'reglamento_interno_id' => $rit->id,
            'user_id' => User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true])->id,
            'texto_rit_hash' => hash('sha256', 'v1'), 'declarado_en' => now(),
        ]);

        $this->assertFalse($empresa->ritPendienteDeSocializar());
    }

    /**
     * El RIT cambió de contenido DESPUÉS de la culminación declarada -
     * sigue pendiente porque la culminación vieja ya no corresponde al
     * texto vigente (comparación por hash, no por id de fila).
     */
    public function test_fase_socializacion_con_culminacion_de_una_version_vieja_sigue_pendiente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v2 nuevo',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        CulminacionSocializacionRit::create([
            'empresa_id' => $empresa->id, 'reglamento_interno_id' => $rit->id,
            'user_id' => User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true])->id,
            'texto_rit_hash' => hash('sha256', 'v1 viejo'), 'declarado_en' => now()->subMonths(2),
        ]);

        $this->assertTrue($empresa->ritPendienteDeSocializar());
    }
}
