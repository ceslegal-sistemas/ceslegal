<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Services\ReglamentoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganigramaCargosSolicitudContratoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cargos_de_empresa_usa_los_cargos_del_wizard_si_existen(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'respuestas_cuestionario' => [
                'cargos' => [
                    ['nombre_cargo' => 'Gerente General', 'instancia_sancionatoria' => 'primera_instancia'],
                    ['nombre_cargo' => 'Operario', 'instancia_sancionatoria' => 'ninguna'],
                ],
            ],
        ]);

        $cargos = app(ReglamentoInternoService::class)->cargosDeEmpresa($empresa->id);

        $this->assertCount(2, $cargos);
        $this->assertSame('Gerente General', $cargos[0]['nombre_cargo']);
    }

    public function test_cargos_de_empresa_usa_el_organigrama_extraido_si_no_hay_wizard(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        // organigrama se asigna DESPUÉS de crear el registro (con
        // saveQuietly(), igual que generarOrganigrama() en el código real) -
        // ReglamentoInternoObserver invalida organigrama/conductas cuando
        // texto_completo cambia, y en un create() texto_completo siempre
        // cuenta como "dirty", así que ponerlo en el mismo create() de este
        // test lo borraría de inmediato (comportamiento correcto del
        // observer, no un bug del test original).
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'texto_completo' => 'RIT con cargos mencionados',
        ]);
        $rit->organigrama = [
            ['nombre_cargo' => 'Jefe de Bodega', 'instancia_sancionatoria' => 'ninguna'],
        ];
        $rit->saveQuietly();

        $cargos = app(ReglamentoInternoService::class)->cargosDeEmpresa($empresa->id);

        $this->assertCount(1, $cargos);
        $this->assertSame('Jefe de Bodega', $cargos[0]['nombre_cargo']);
    }

    public function test_cargos_de_empresa_vacio_sin_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        $this->assertSame([], app(ReglamentoInternoService::class)->cargosDeEmpresa($empresa->id));
    }

    public function test_generar_organigrama_extrae_y_persiste_desde_el_texto_del_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'texto_completo' => 'El Gerente General tiene la facultad de imponer sanciones disciplinarias. Los Operarios de planta no tienen esa facultad.',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                ['nombre_cargo' => 'Gerente General', 'instancia_sancionatoria' => 'primera_instancia'],
                                ['nombre_cargo' => 'Operario de planta', 'instancia_sancionatoria' => 'ninguna'],
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $organigrama = app(ReglamentoInternoService::class)->generarOrganigrama($rit);

        $this->assertCount(2, $organigrama);
        $rit->refresh();
        $this->assertCount(2, $rit->organigrama);
        $this->assertSame('Gerente General', $rit->organigrama[0]['nombre_cargo']);
    }

    public function test_generar_organigrama_devuelve_vacio_sin_texto_de_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true]);

        $this->assertSame([], app(ReglamentoInternoService::class)->generarOrganigrama($rit));
    }

    public function test_select_de_cargo_del_wizard_cae_al_listado_fijo_sin_organigrama(): void
    {
        $cargos = SolicitudContratoResource::getCargosParaSelect(null);

        $this->assertArrayHasKey('__otro__', $cargos);
        $this->assertArrayHasKey('Gerente General', $cargos); // del catálogo fijo (getCargos())
    }

    public function test_select_de_cargo_usa_el_organigrama_cuando_existe(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'respuestas_cuestionario' => [
                'cargos' => [
                    ['nombre_cargo' => 'Coordinador de Logística', 'instancia_sancionatoria' => 'primera_instancia'],
                ],
            ],
        ]);

        $cargos = SolicitudContratoResource::getCargosParaSelect($empresa->id);

        $this->assertArrayHasKey('Coordinador de Logística', $cargos);
        $this->assertSame('Coordinador de Logística (con facultad disciplinaria)', $cargos['Coordinador de Logística']);
        $this->assertArrayNotHasKey('Gerente General', $cargos); // ya no cae al catálogo fijo
    }
}
