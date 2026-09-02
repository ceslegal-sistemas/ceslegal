<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido explícito del usuario: la notificación de campana no bastaba sola -
 * se acumularon 14 sugerencias sin revisar en 14 empresas en producción
 * porque nadie entra a "Mi Reglamento Interno" a buscarlas. Este banner
 * aparece de una vez al entrar al Dashboard.
 */
class DashboardSugerenciasRitNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function crearSugerenciaPendiente(Empresa $empresa): SugerenciaActualizacionRit
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Texto del RIT.',
        ]);

        $documento = DocumentoLegal::create([
            'titulo' => 'Ley de prueba',
            'tipo' => 'ley',
            'estado' => 'procesado',
            'activo' => true,
        ]);

        return SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Texto viejo',
            'texto_propuesto' => 'Texto nuevo',
            'justificacion_ia' => 'La ley X modifica Y.',
            'estado' => 'pendiente',
        ]);
    }

    public function test_muestra_el_banner_al_cliente_con_sugerencia_pendiente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSugerenciaPendiente($empresa);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Hay una actualización legal que aplica a su Reglamento Interno');
    }

    public function test_no_muestra_el_banner_sin_sugerencias_pendientes(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('actualización legal que aplica a su Reglamento Interno');
    }

    public function test_no_muestra_el_banner_a_super_admin(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSugerenciaPendiente($empresa);
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertDontSee('actualización legal que aplica a su Reglamento Interno');
    }

    public function test_notifica_con_titulo_claro_y_prioridad_urgente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $sugerencia = $this->crearSugerenciaPendiente($empresa);
        User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        app(\App\Services\NotificacionService::class)->notificarSugerenciaActualizacionRit($sugerencia);

        $registro = \App\Models\User::where('empresa_id', $empresa->id)->first()
            ->notifications()
            ->latest()
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame('Hay una actualización legal que aplica a su Reglamento Interno', $registro->data['title']);
        $this->assertStringContainsString('¿Desea actualizarlo?', $registro->data['body']);
    }
}
