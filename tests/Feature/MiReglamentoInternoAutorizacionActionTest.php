<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\AutorizacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Equivalente funcional" de firma para el RIT (pedido de Andrés Sarmiento,
 * reunión 2026-10-05): a diferencia de Sanciones/Descargos, el RIT nunca
 * registraba QUIÉN lo autorizó. Mismo rigor que "Emitir Sanción": nombre,
 * cargo, foto y disclaimer Ley 1581 - ver HasVerificacionFotografica.
 */
class MiReglamentoInternoAutorizacionActionTest extends TestCase
{
    use RefreshDatabase;

    private function fotoBase64DePrueba(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    private function actingAsCliente(Empresa $empresa): User
    {
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        return $user;
    }

    public function test_el_boton_esta_habilitado_cuando_nunca_se_ha_autorizado(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionEnabled('autorizarReglamento');
    }

    public function test_oculto_si_no_hay_texto_completo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => null, 'estado_generacion' => 'generando']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionHidden('autorizarReglamento');
    }

    public function test_falla_sin_foto_de_verificacion(): void
    {
        Storage::fake('public');
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('autorizarReglamento', data: [
                'autorizador_nombre' => 'Juan Pérez',
                'autorizador_cargo' => 'Gerente',
            ]);

        $this->assertDatabaseCount('autorizaciones_reglamento_interno', 0);
    }

    public function test_autorizar_guarda_el_registro_con_hash_del_texto_actual(): void
    {
        Storage::fake('public');
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'Texto version 1.']);
        $user = $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->call('aceptarDisclaimerDatos')
            ->callAction('autorizarReglamento', data: [
                'autorizador_nombre' => 'Juan Pérez',
                'autorizador_cargo' => 'Gerente General',
                'foto_autorizador_base64' => $this->fotoBase64DePrueba(),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('autorizaciones_reglamento_interno', 1);

        $autorizacion = AutorizacionReglamentoInterno::first();
        $this->assertSame($rit->id, $autorizacion->reglamento_interno_id);
        $this->assertSame($empresa->id, $autorizacion->empresa_id);
        $this->assertSame($user->id, $autorizacion->user_id);
        $this->assertSame('Juan Pérez', $autorizacion->autorizador_nombre);
        $this->assertSame('Gerente General', $autorizacion->autorizador_cargo);
        $this->assertSame(hash('sha256', 'Texto version 1.'), $autorizacion->texto_rit_hash);
        $this->assertSame('Texto version 1.', $autorizacion->texto_rit_snapshot);
        $this->assertNotNull($autorizacion->foto_autorizador_path);
        $this->assertNotNull($autorizacion->foto_autorizador_en);
        $this->assertNotNull($autorizacion->disclaimer_datos_autorizador_en);
        Storage::disk('public')->assertExists($autorizacion->foto_autorizador_path);
    }

    public function test_despues_de_autorizar_el_boton_queda_deshabilitado(): void
    {
        Storage::fake('public');
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'Texto version 1.']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('autorizarReglamento', data: [
                'autorizador_nombre' => 'Juan Pérez',
                'autorizador_cargo' => 'Gerente',
                'foto_autorizador_base64' => $this->fotoBase64DePrueba(),
            ])
            ->assertActionDisabled('autorizarReglamento');
    }

    public function test_si_el_texto_cambia_despues_de_autorizar_vuelve_a_requerir_autorizacion(): void
    {
        Storage::fake('public');
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'Texto version 1.']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('autorizarReglamento', data: [
                'autorizador_nombre' => 'Juan Pérez',
                'autorizador_cargo' => 'Gerente',
                'foto_autorizador_base64' => $this->fotoBase64DePrueba(),
            ]);

        // Adopción de mejora / subida manual: el texto cambia (mismo id).
        $rit->update(['texto_completo' => 'Texto version 2, ya modificado.']);

        $this->assertTrue($rit->fresh()->requiereAutorizacion());

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionEnabled('autorizarReglamento');
    }

    public function test_dos_autorizaciones_del_mismo_rit_quedan_ambas_registradas(): void
    {
        Storage::fake('public');
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'Texto version 1.']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('autorizarReglamento', data: [
                'autorizador_nombre' => 'Juan Pérez',
                'autorizador_cargo' => 'Gerente',
                'foto_autorizador_base64' => $this->fotoBase64DePrueba(),
            ]);

        $rit->update(['texto_completo' => 'Texto version 2.']);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('autorizarReglamento', data: [
                'autorizador_nombre' => 'Juan Pérez',
                'autorizador_cargo' => 'Gerente',
                'foto_autorizador_base64' => $this->fotoBase64DePrueba(),
            ]);

        // Append-only: ambas autorizaciones quedan, ninguna se sobreescribe -
        // es lo que permite encontrar las 3 (o más) autorizaciones de un
        // mismo funcionario a través de las versiones del RIT.
        $this->assertDatabaseCount('autorizaciones_reglamento_interno', 2);
        $this->assertSame(2, AutorizacionReglamentoInterno::where('autorizador_nombre', 'Juan Pérez')->count());
    }
}
