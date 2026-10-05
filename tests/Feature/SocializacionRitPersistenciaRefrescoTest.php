<?php

namespace Tests\Feature;

use App\Livewire\SocializacionRit;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-10-05): refrescar el navegador en
 * cualquier punto del flujo devolvía al trabajador hasta "ingrese su
 * cédula", perdiendo toda sensación de avance. guardarDatos() ahora guarda
 * el trabajador_id en la sesión de Laravel (sobrevive un F5, a diferencia
 * del estado en memoria de Livewire) y mount() intenta restaurar desde ahí.
 *
 * Solo aplica con $token real - sin token (ej. tests viejos que no lo pasan)
 * no hay nada que guardar ni restaurar, ver guardarProgresoEnSesion().
 */
class SocializacionRitPersistenciaRefrescoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_refrescar_tras_guardar_datos_restaura_en_presentacion_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $token = $empresa->tokenSocializacionRit();

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $token])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '555666777')
            ->set('numeroDocumentoConfirmacion', '555666777')
            ->call('buscarTrabajador')
            ->set('nombres', 'Ana')
            ->set('apellidos', 'Torres')
            ->set('genero', 'femenino')
            ->set('cargo', 'Vendedor')
            ->call('guardarDatos');

        // Simula un F5: instancia COMPLETAMENTE NUEVA del componente, sin
        // ningún estado en memoria - solo lo que sobrevive en sesión/BD.
        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $token])
            ->assertSet('etapa', 'presentacion_rit')
            ->assertSet('nombres', 'Ana');
    }

    public function test_sin_token_no_guarda_ni_restaura_nada(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '111222444')
            ->set('numeroDocumentoConfirmacion', '111222444')
            ->call('buscarTrabajador')
            ->set('nombres', 'Sin')
            ->set('apellidos', 'Token')
            ->set('genero', 'masculino')
            ->set('cargo', 'Vendedor')
            ->call('guardarDatos');

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->assertSet('etapa', 'documento');
    }

    public function test_si_ya_acepto_mientras_tanto_el_refresco_respeta_ese_estado_final(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $token = $empresa->tokenSocializacionRit();

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $token])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '222333555')
            ->set('numeroDocumentoConfirmacion', '222333555')
            ->call('buscarTrabajador')
            ->set('nombres', 'Ya')
            ->set('apellidos', 'Acepto')
            ->set('genero', 'masculino')
            ->set('cargo', 'Vendedor')
            ->call('guardarDatos');

        $trabajador = Trabajador::where('numero_documento', '222333555')->firstOrFail();
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'texto_rit_snapshot' => 'v1',
            'texto_rit_hash' => hash('sha256', 'v1'),
            'aceptado_en' => now(),
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $token])
            ->assertSet('etapa', 'ya_acepto');
    }
}
