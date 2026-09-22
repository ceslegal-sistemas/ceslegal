<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Livewire\SocializacionRit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocializacionRitEtapaDocumentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_rit_activo_no_avanza_de_etapa(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->assertSet('etapa', 'sin_rit');
    }

    public function test_documento_nuevo_pasa_a_la_etapa_datos(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '555666777')
            ->call('buscarTrabajador')
            ->assertSet('etapa', 'datos')
            ->assertSet('trabajadorExistente', false);
    }

    public function test_documento_de_trabajador_existente_que_ya_acepto_salta_a_ya_acepto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '1010101010',
            'genero' => 'masculino', 'nombres' => 'Pedro', 'apellidos' => 'Ruiz', 'cargo' => 'Operario', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now(),
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '1010101010')
            ->call('buscarTrabajador')
            ->assertSet('etapa', 'ya_acepto');
    }

    /**
     * Hallazgo critico de la revision del spec: el empresa_id SIEMPRE sale
     * de la $empresa resuelta por token (prop del componente), nunca de un
     * campo del formulario - un mismo numero de documento puede existir en
     * OTRA empresa sin cruzarse.
     */
    public function test_no_encuentra_trabajador_de_otra_empresa_con_el_mismo_documento(): void
    {
        $empresaA = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresaA->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        $empresaB = Empresa::factory()->create(['active' => true]);
        Trabajador::create([
            'empresa_id' => $empresaB->id, 'tipo_documento' => 'CC', 'numero_documento' => '2020202020',
            'genero' => 'femenino', 'nombres' => 'Otra', 'apellidos' => 'Empresa', 'cargo' => 'X', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresaA])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '2020202020')
            ->call('buscarTrabajador')
            ->assertSet('etapa', 'datos')
            ->assertSet('trabajadorExistente', false);
    }

    /**
     * Version CON sesion autenticada del test anterior - la que realmente
     * ejercita el withoutGlobalScope('bufeteOrEmpresa'). Sin un usuario
     * autenticado, ScopedToBufeteOrEmpresa no filtra nada (no-op), asi que
     * el test de arriba pasaria igual con o sin el bypass - este es el que
     * de verdad prueba el hallazgo de seguridad del spec: un bufete con
     * OTRA empresa activa en el mismo navegador no debe interferir con la
     * resolucion del trabajador de la empresa del token.
     */
    public function test_bufete_con_otra_empresa_activa_en_sesion_no_interfiere(): void
    {
        $empresaDelToken = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresaDelToken->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajadorExistente = Trabajador::create([
            'empresa_id' => $empresaDelToken->id, 'tipo_documento' => 'CC', 'numero_documento' => '3030303030',
            'genero' => 'masculino', 'nombres' => 'Real', 'apellidos' => 'DelToken', 'cargo' => 'X', 'active' => true,
        ]);

        $otraEmpresa = Empresa::factory()->create(['active' => true]);
        $bufete = \App\Models\Bufete::factory()->create();
        $usuarioBufete = \App\Models\User::factory()->create([
            'role' => 'bufete', 'bufete_id' => $bufete->id, 'active' => true,
        ]);
        $this->actingAs($usuarioBufete);
        \App\Support\EmpresaActiva::set($otraEmpresa->id);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresaDelToken])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '3030303030')
            ->call('buscarTrabajador')
            ->assertSet('trabajadorExistente', true)
            ->assertSet('trabajadorId', $trabajadorExistente->id);
    }
}
