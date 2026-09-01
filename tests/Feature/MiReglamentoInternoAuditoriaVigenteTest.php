<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class MiReglamentoInternoAuditoriaVigenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug real reportado por el usuario con captura de pantalla: construyó un
     * RIT completamente nuevo desde cero con el wizard "Construir RIT" (que
     * desactiva el RIT viejo, ver ReglamentoInternoUnicoActivoTest) y, al
     * terminar, "Mi Reglamento Interno" seguía mostrando el aviso "Reglamento
     * actualizado con IA... Esta es su versión vigente" de una auditoría y
     * mejora VIEJAS, adoptadas semanas atrás sobre el RIT ya reemplazado.
     * Causa: mount() tomaba "la auditoría más reciente de la empresa" sin
     * filtrar por el RIT que audita - con el RIT nuevo aún sin auditar, la
     * auditoría vieja (ligada al RIT ya inactivo) seguía siendo "la más
     * reciente" y su banner de mejora adoptada se mostraba igual.
     */
    public function test_no_muestra_la_mejora_adoptada_de_un_rit_ya_reemplazado(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        $ritViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => false,
            'fuente' => 'subido',
            'texto_completo' => 'RIT viejo, ya reemplazado.',
        ]);
        $ritMejoradoViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => false,
            'fuente' => 'mejora_ia',
            'reglamento_origen_id' => $ritViejo->id,
            'texto_completo' => 'RIT mejorado del ciclo anterior, ya reemplazado.',
        ]);
        AuditoriaRIT::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $ritViejo->id,
            'estado' => 'completado',
            'estado_mejora' => 'completado',
            'reglamento_mejorado_id' => $ritMejoradoViejo->id,
            'decision_mejora' => 'adoptado',
            'secciones' => [['titulo' => 'x', 'score' => 60]],
        ]);

        $ritNuevo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'RIT nuevo, construido desde cero con el wizard.',
            'estado_generacion' => 'completado',
        ]);

        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $component = Livewire::actingAs($user)->test(MiReglamentoInterno::class);

        $this->assertTrue($component->get('reglamento')->is($ritNuevo));
        $this->assertNull($component->get('auditoria'));
    }

    /**
     * Pedido explícito del usuario, con captura de pantalla: si el RIT fue
     * generado con IA (construido_ia o mejora_ia), el botón "Auditar RIT" no
     * debe mostrarse - auditar con nuestra propia IA un documento que la
     * misma IA ya redactó/mejoró es circular. Solo tiene sentido cuando el
     * RIT fue subido manualmente por el cliente.
     */
    public function test_no_muestra_el_boton_auditar_para_un_rit_generado_con_ia(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritIA = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'RIT construido desde cero con el wizard.',
            'estado_generacion' => 'completado',
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertDontSeeHtml('wire:click="iniciarAuditoriaManual"');

        $this->assertSame($ritIA->id, $ritIA->id);
    }

    public function test_iniciar_auditoria_manual_rechaza_un_rit_generado_con_ia(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'RIT construido desde cero con el wizard.',
            'estado_generacion' => 'completado',
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->call('iniciarAuditoriaManual');

        $this->assertSame(0, AuditoriaRIT::count());
    }

    public function test_muestra_el_boton_auditar_para_un_rit_subido_manualmente(): void
    {
        // Bus::fake(): un RIT 'subido' sin auditoría dispara el auto-auditar de
        // mount() (ver ExtraerSancionesRITJob/ProcesarAuditoriaRIT) - no es lo
        // que este test verifica, y con QUEUE_CONNECTION=sync correría de
        // verdad contra Gemini si no se interceptara el job.
        Bus::fake();

        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'RIT real subido por el cliente.',
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSeeHtml('wire:click="iniciarAuditoriaManual"');
    }
}
