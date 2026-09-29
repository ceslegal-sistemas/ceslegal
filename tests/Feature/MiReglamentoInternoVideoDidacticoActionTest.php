<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Jobs\GenerarVideoDidacticoRITJob;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Acción "Generar video didáctico" (pedido del equipo, 2026-09-28) - siempre
 * manual, solo super_admin por ahora, dado el costo real y todavía no
 * confirmado en https://ai.studio/spend.
 */
class MiReglamentoInternoVideoDidacticoActionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(Empresa $empresa): User
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        \App\Support\EmpresaActiva::set($empresa->id);
        $this->actingAs($user);

        return $user;
    }

    public function test_visible_para_super_admin_con_rit_con_texto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->actingAsSuperAdmin($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionVisible('generarVideoDidactico');
    }

    public function test_oculta_si_no_hay_texto_completo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => null, 'estado_generacion' => 'generando']);
        $this->actingAsSuperAdmin($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionHidden('generarVideoDidactico');
    }

    public function test_oculta_mientras_ya_esta_generando(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'video_didactico_estado' => 'generando',
        ]);
        $this->actingAsSuperAdmin($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionHidden('generarVideoDidactico');
    }

    public function test_oculta_para_usuario_no_super_admin(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionHidden('generarVideoDidactico');
    }

    public function test_ejecutar_la_accion_marca_generando_y_despacha_el_job(): void
    {
        Bus::fake();

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->actingAsSuperAdmin($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('generarVideoDidactico');

        $this->assertSame('generando', $rit->fresh()->video_didactico_estado);
        Bus::assertDispatched(GenerarVideoDidacticoRITJob::class, fn ($job) => $job->rit->id === $rit->id);
    }

    /**
     * Hallazgo real en producción (2026-09-29): un RIT con reglamento_origen_id
     * (fuente=mejora_ia) pero cuyo origen NUNCA tuvo un video generado no debe
     * narrar "qué cambió" - a ningún trabajador se le mostró nunca un video de
     * esa versión vieja, así que no hay nada que "actualizar" desde su
     * perspectiva. Debe explicar el reglamento en temas generales, como si
     * fuera la primera vez.
     */
    public function test_no_usa_modo_cambios_si_el_origen_nunca_tuvo_video(): void
    {
        Bus::fake();

        $empresa = Empresa::factory()->create(['active' => true]);
        $origen = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => false, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia', 'texto_completo' => 'v2',
            'reglamento_origen_id' => $origen->id,
        ]);
        $this->actingAsSuperAdmin($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('generarVideoDidactico');

        Bus::assertDispatched(GenerarVideoDidacticoRITJob::class, fn ($job) => $job->rit->id === $rit->id && $job->cambios === []);
    }

    /** Si el origen SÍ tuvo video, ahí sí tiene sentido narrar qué cambió respecto a lo que ya vieron. */
    public function test_usa_modo_cambios_si_el_origen_ya_tuvo_video(): void
    {
        Bus::fake();

        $empresa = Empresa::factory()->create(['active' => true]);
        $origen = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => false, 'fuente' => 'construido_ia', 'texto_completo' => 'Capitulo uno. Texto original.',
            'video_didactico_path' => 'rit-videos/fake.mp4', 'video_didactico_generado_en' => now(),
        ]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia', 'texto_completo' => 'Capitulo uno. Texto modificado.',
            'reglamento_origen_id' => $origen->id,
        ]);
        $this->actingAsSuperAdmin($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('generarVideoDidactico');

        Bus::assertDispatched(GenerarVideoDidacticoRITJob::class, fn ($job) => $job->rit->id === $rit->id && $job->cambios !== []);
    }
}
