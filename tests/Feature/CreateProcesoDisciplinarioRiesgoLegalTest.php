<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages\CreateProcesoDisciplinario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-11): cuando la clasificación de IA
 * marca categoria_riesgo_legal='posible_acoso_o_violencia', el wizard debe
 * mostrar un aviso prominente y bloquear "Siguiente" hasta que el usuario
 * marque un checkbox de reconocimiento explícito.
 *
 * La lógica de decisión se extrajo a un método estático testeable en vez
 * de probarla vía fillForm()/nextStep() del Wizard - esa navegación es
 * "sabidamente frágil" en los Wizards de este proyecto (ver memoria
 * filament-wizard-fillform-no-aplica.md y el mismo criterio ya aplicado en
 * CrearSolicitudContratoWizardTest.php).
 */
class CreateProcesoDisciplinarioRiesgoLegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_tiene_riesgo_legal_alto_es_true_cuando_la_categoria_esta_marcada(): void
    {
        $json = json_encode(['categoria_riesgo_legal' => 'posible_acoso_o_violencia']);

        $this->assertTrue(CreateProcesoDisciplinario::tieneRiesgoLegalAlto($json));
    }

    public function test_tiene_riesgo_legal_alto_es_false_cuando_la_categoria_es_ninguna(): void
    {
        $json = json_encode(['categoria_riesgo_legal' => 'ninguna']);

        $this->assertFalse(CreateProcesoDisciplinario::tieneRiesgoLegalAlto($json));
    }

    public function test_tiene_riesgo_legal_alto_es_false_sin_clasificacion(): void
    {
        $this->assertFalse(CreateProcesoDisciplinario::tieneRiesgoLegalAlto(null));
        $this->assertFalse(CreateProcesoDisciplinario::tieneRiesgoLegalAlto(''));
    }

    public function test_tiene_riesgo_legal_alto_es_false_con_json_invalido(): void
    {
        $this->assertFalse(CreateProcesoDisciplinario::tieneRiesgoLegalAlto('esto no es json'));
    }

    /**
     * Bug real reportado por el usuario (2026-09-25): tras una recarga de
     * página (típicamente por un 522/429 de Cloudflare a mitad de sesión),
     * $chatListo volvía a false aunque la descripción ya generada seguía
     * visible en el textarea (restaurada desde el borrador de sesión en
     * mount()) - bloqueaba "Crear" con "Descripción jurídica requerida"
     * pese a que el dato sí estaba presente.
     */
    public function test_hechos_ya_generados_es_true_si_hechos_ia_tiene_texto_aunque_datos_extraidos_este_vacio(): void
    {
        $this->assertTrue(CreateProcesoDisciplinario::hechosYaGenerados(['hechos_ia' => 'Texto generado por IA.'], []));
    }

    public function test_hechos_ya_generados_usa_datos_extraidos_si_no_hay_hechos_ia(): void
    {
        $this->assertTrue(CreateProcesoDisciplinario::hechosYaGenerados([], ['hechos' => 'Texto generado por IA.']));
    }

    public function test_hechos_ya_generados_es_false_sin_ningun_texto(): void
    {
        $this->assertFalse(CreateProcesoDisciplinario::hechosYaGenerados([], []));
        $this->assertFalse(CreateProcesoDisciplinario::hechosYaGenerados(['hechos_ia' => '   '], []));
    }

    public function test_la_pagina_de_crear_renderiza_sin_error(): void
    {
        // Mismo criterio que CrearSolicitudContratoWizardTest.php: confirma
        // que el Wizard completo (con el nuevo Placeholder/Checkbox) sigue
        // renderizando sin romper el HTML inicial - no se navega entre pasos.
        Permission::findOrCreate('create_proceso::disciplinario', 'web');
        Permission::findOrCreate('view_any_proceso::disciplinario', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_proceso::disciplinario', 'view_any_proceso::disciplinario']);
        $this->actingAs($user);

        $this->get(ProcesoDisciplinarioResource::getUrl('create'))
            ->assertSuccessful()
            ->assertSee('Recomendado');
    }
}
