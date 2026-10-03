<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\CulminacionSocializacionRit;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Botón manual "Culminar Socialización del RIT" (pedido de Andrés Sarmiento,
 * reunión 2026-10-03): el admin declara bajo su responsabilidad que ya
 * notificó a todos los trabajadores, con su propia selfie de verificación.
 * El flujo completo del modal (foto vía Alpine/webcam) sigue el mismo
 * criterio de testing que aceptarSugerenciasRITAction/InteractsConAceptacionMejoraRIT:
 * no se simula la captura de cámara en PHPUnit, se prueba el modelo y la
 * lógica de vigencia directamente.
 */
class CulminarSocializacionRitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Por defecto en Fase 2 (Socialización) - el botón solo debe ser
     * visible pasados los 15 días hábiles de objeción (pedido explícito del
     * usuario, 2026-10-03), nunca durante la Fase 1 (Publicación).
     */
    private function crearEmpresaConRitYUsuario(string $textoRit = 'v1'): array
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => $textoRit,
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '555666777',
            'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Op', 'active' => true,
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        return [$empresa, $rit, $user];
    }

    public function test_muestra_el_boton_si_no_hay_culminacion_vigente(): void
    {
        [$empresa, $rit, $user] = $this->crearEmpresaConRitYUsuario();

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSee('Culminar Socialización del RIT');
    }

    public function test_muestra_la_constancia_si_ya_hay_culminacion_vigente(): void
    {
        [$empresa, $rit, $user] = $this->crearEmpresaConRitYUsuario();

        CulminacionSocializacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'user_id' => $user->id,
            'texto_rit_hash' => hash('sha256', $rit->texto_completo),
            'declarado_en' => now(),
        ]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSee('Socialización culminada el')
            ->assertDontSee('Culminar Socialización del RIT');
    }

    /**
     * Mismo criterio de staleness que aceptoRitVigente()/confirmoPublicacionVigente():
     * si el RIT mutó de contenido (Plan B quirúrgico, mismo id) desde que se
     * culminó, el hash ya no coincide - vuelve a contar como "no culminada".
     */
    public function test_vuelve_a_pedir_culminacion_si_el_rit_cambio_de_contenido(): void
    {
        [$empresa, $rit, $user] = $this->crearEmpresaConRitYUsuario();

        CulminacionSocializacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'user_id' => $user->id,
            'texto_rit_hash' => hash('sha256', 'texto viejo, ya no coincide'),
            'declarado_en' => now(),
        ]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSee('Culminar Socialización del RIT')
            ->assertDontSee('Socialización culminada el');
    }

    /**
     * Pedido explícito del usuario (2026-10-03): durante la Fase 1
     * (Publicación, dentro de los 15 días hábiles) el botón debe quedar
     * invisible - solo tiene sentido "culminar la socialización" una vez
     * se entró en Fase 2.
     */
    public function test_no_muestra_el_boton_durante_la_fase_1_de_publicacion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->toDateString(),
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertDontSee('Culminar Socialización del RIT');
    }

    /**
     * Pedido explícito del usuario (2026-10-03): el botón debe estar
     * disponible aunque la empresa no tenga trabajadores registrados en el
     * sistema (ej. socializó 100% en persona/papel) - vive FUERA de la
     * tarjeta de progreso, que solo se muestra si hay trabajadores.
     */
    public function test_muestra_el_boton_aunque_no_haya_trabajadores_registrados(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSee('Culminar Socialización del RIT');
    }

    public function test_se_puede_crear_y_relacionar_con_empresa_rit_y_usuario(): void
    {
        [$empresa, $rit, $user] = $this->crearEmpresaConRitYUsuario();

        $culminacion = CulminacionSocializacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'user_id' => $user->id,
            'texto_rit_hash' => hash('sha256', $rit->texto_completo),
            'foto_admin_path' => 'fotos-verificacion/culminacion-socializacion/1/abc.jpg',
            'declarado_en' => now(),
            'ip' => '127.0.0.1',
            'user_agent' => 'PestTest',
        ]);

        $this->assertTrue($culminacion->empresa->is($empresa));
        $this->assertTrue($culminacion->reglamentoInterno->is($rit));
        $this->assertTrue($culminacion->user->is($user));
    }
}
