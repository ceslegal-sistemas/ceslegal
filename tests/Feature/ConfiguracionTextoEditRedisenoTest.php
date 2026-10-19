<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ConfiguracionTextoResource\Pages\EditConfiguracionTexto;
use App\Models\ConfiguracionTexto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pedido del usuario (2026-10-16): el panel de "Textos Configurables" tenía
 * 3 Section genéricas de Filament sin identidad de marca, y algunas claves
 * (disclaimer_rit_publicacion, disclaimer_rit_socializacion,
 * disclaimer_culminacion_socializacion) ya resolvían :empresa en el código
 * pero no aparecían en VARIABLES, así que la sección "Variables que puede
 * usar" quedaba oculta para ellas.
 */
class ConfiguracionTextoEditRedisenoTest extends TestCase
{
    use RefreshDatabase;

    private function actuarComoSuperAdmin(): void
    {
        Permission::findOrCreate('view_any_configuracion::texto', 'web');
        Permission::findOrCreate('update_configuracion::texto', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_any_configuracion::texto', 'update_configuracion::texto']);
        $this->actingAs($user);
    }

    public function test_el_formulario_de_edicion_muestra_el_encabezado_con_la_clave_y_el_grupo(): void
    {
        $this->actuarComoSuperAdmin();
        $registro = ConfiguracionTexto::where('clave', 'disclaimer_descargos')->first();

        Livewire::test(EditConfiguracionTexto::class, ['record' => $registro->getKey()])
            ->assertSuccessful()
            ->assertSee('disclaimer_descargos')
            ->assertSee('descargos');
    }

    public function test_disclaimer_rit_publicacion_ahora_muestra_la_variable_empresa(): void
    {
        $this->actuarComoSuperAdmin();
        $registro = ConfiguracionTexto::where('clave', 'disclaimer_rit_publicacion')->first();

        Livewire::test(EditConfiguracionTexto::class, ['record' => $registro->getKey()])
            ->assertSuccessful()
            ->assertSee('Variables que puede usar')
            ->assertSee(':empresa');
    }

    public function test_disclaimer_descargos_ofrece_el_marcador_url_politica(): void
    {
        $this->actuarComoSuperAdmin();
        $registro = ConfiguracionTexto::where('clave', 'disclaimer_descargos')->first();

        Livewire::test(EditConfiguracionTexto::class, ['record' => $registro->getKey()])
            ->assertSuccessful()
            ->assertSee(':url_politica');
    }

    public function test_guardar_el_texto_no_borra_la_clave_ni_el_grupo(): void
    {
        $this->actuarComoSuperAdmin();
        $registro = ConfiguracionTexto::where('clave', 'disclaimer_descargos')->first();
        $grupoOriginal = $registro->grupo;

        Livewire::test(EditConfiguracionTexto::class, ['record' => $registro->getKey()])
            ->fillForm(['valor' => 'Texto nuevo de prueba.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $registro->refresh();
        $this->assertSame('Texto nuevo de prueba.', $registro->valor);
        $this->assertSame('disclaimer_descargos', $registro->clave);
        $this->assertSame($grupoOriginal, $registro->grupo);
    }

    /**
     * Pedido del usuario (2026-10-16): "que pueda ser segmentado por
     * nosotros como rol super_admin" - el gate es por ROL (hasRole), no
     * solo por el permiso de Shield, justamente para que ASIGNAR el
     * permiso a bufete/abogado por error en Roles no abra este panel. Por
     * eso esta prueba le da el permiso completo a un bufete y aun así
     * debe quedar bloqueado.
     */
    public function test_bufete_con_el_permiso_de_shield_no_puede_acceder_aunque_lo_tenga(): void
    {
        Permission::findOrCreate('view_any_configuracion::texto', 'web');
        Permission::findOrCreate('update_configuracion::texto', 'web');
        $bufete = User::factory()->create(['role' => 'bufete', 'active' => true]);
        $bufete->givePermissionTo(['view_any_configuracion::texto', 'update_configuracion::texto']);
        $this->actingAs($bufete);

        $registro = ConfiguracionTexto::where('clave', 'disclaimer_descargos')->first();

        Livewire::test(EditConfiguracionTexto::class, ['record' => $registro->getKey()])
            ->assertForbidden();
    }

    public function test_super_admin_si_puede_acceder(): void
    {
        $this->actuarComoSuperAdmin();
        $registro = ConfiguracionTexto::where('clave', 'disclaimer_descargos')->first();

        Livewire::test(EditConfiguracionTexto::class, ['record' => $registro->getKey()])
            ->assertSuccessful();
    }
}
