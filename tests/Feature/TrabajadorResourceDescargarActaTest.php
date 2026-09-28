<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\TrabajadorResource\Pages\ListTrabajadors;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Evidencia jurídica (2026-09-28, diseño aprobado sección 3): las 4 acciones
 * base (Ver/Editar/Desactivar/Activar) se agrupan en un ActionGroup, y
 * aparte se muestra "Descargar Acta" (1 aceptación) o "Ver Actas (N)"
 * (2+, modal con el historial completo) - nunca ambas a la vez.
 */
class TrabajadorResourceDescargarActaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Permission::findOrCreate('view_any_trabajador', 'web');
    }

    private function actingAsAutorizado(): User
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo('view_any_trabajador');
        $this->actingAs($user);

        return $user;
    }

    private function crearTrabajadorConEmpresa(): array
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '321321321',
            'genero' => 'masculino', 'nombres' => 'Acta', 'apellidos' => 'Prueba', 'cargo' => 'X', 'active' => true,
        ]);

        return [$empresa, $rit, $trabajador];
    }

    public function test_sin_actas_no_muestra_ninguna_de_las_2_acciones(): void
    {
        $this->actingAsAutorizado();
        [, , $trabajador] = $this->crearTrabajadorConEmpresa();

        Livewire::test(ListTrabajadors::class)
            ->assertTableActionHidden('descargar_acta', $trabajador)
            ->assertTableActionHidden('ver_actas', $trabajador);
    }

    public function test_con_1_acta_muestra_solo_descargar_acta(): void
    {
        $this->actingAsAutorizado();
        [, $rit, $trabajador] = $this->crearTrabajadorConEmpresa();
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', 'v1'), 'aceptado_en' => now(), 'ruta_acta' => 'actas-rit/1/pdf/acta_1.pdf',
        ]);

        Livewire::test(ListTrabajadors::class)
            ->assertTableActionVisible('descargar_acta', $trabajador)
            ->assertTableActionHidden('ver_actas', $trabajador);
    }

    public function test_con_2_actas_muestra_solo_ver_actas(): void
    {
        $this->actingAsAutorizado();
        [, $rit, $trabajador] = $this->crearTrabajadorConEmpresa();
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', 'v1'), 'aceptado_en' => now()->subDays(10), 'ruta_acta' => 'actas-rit/1/pdf/acta_1.pdf',
        ]);
        $rit->update(['texto_completo' => 'v2']);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', 'v2'), 'aceptado_en' => now(), 'ruta_acta' => 'actas-rit/1/pdf/acta_2.pdf',
        ]);

        Livewire::test(ListTrabajadors::class)
            ->assertTableActionHidden('descargar_acta', $trabajador)
            ->assertTableActionVisible('ver_actas', $trabajador);
    }

    public function test_descargar_sirve_el_pdf_real(): void
    {
        $this->actingAsAutorizado();
        [, $rit, $trabajador] = $this->crearTrabajadorConEmpresa();
        Storage::disk('local')->put('actas-rit/1/pdf/acta_1.pdf', '%PDF-1.4 contenido de prueba');
        $aceptacion = AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', 'v1'), 'aceptado_en' => now(), 'ruta_acta' => 'actas-rit/1/pdf/acta_1.pdf',
        ]);

        $response = $this->get(route('trabajador.acta-rit.descargar', ['trabajador' => $trabajador->id, 'aceptacion' => $aceptacion->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * IDOR: un acta que no pertenece al trabajador de la URL debe dar 404,
     * aunque exista y tenga un ruta_acta válida.
     */
    public function test_descargar_da_404_si_el_acta_no_pertenece_al_trabajador(): void
    {
        $this->actingAsAutorizado();
        [$empresa, $rit, $trabajador] = $this->crearTrabajadorConEmpresa();
        $otroTrabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '654654654',
            'genero' => 'femenino', 'nombres' => 'Otro', 'apellidos' => 'Trabajador', 'cargo' => 'X', 'active' => true,
        ]);
        Storage::disk('local')->put('actas-rit/1/pdf/acta_ajena.pdf', '%PDF-1.4');
        $aceptacionAjena = AceptacionReglamentoInterno::create([
            'trabajador_id' => $otroTrabajador->id, 'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', 'v1'), 'aceptado_en' => now(), 'ruta_acta' => 'actas-rit/1/pdf/acta_ajena.pdf',
        ]);

        $response = $this->get(route('trabajador.acta-rit.descargar', ['trabajador' => $trabajador->id, 'aceptacion' => $aceptacionAjena->id]));

        $response->assertNotFound();
    }
}
