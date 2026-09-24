<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Celebracion diferida del logro "Reglamento 100% aceptado" (mejora
 * "excentrica" pedida por el usuario, 2026-09-23): el logro se otorga desde
 * la sesion PUBLICA anonima del trabajador, sin ningun usuario del panel
 * viendo la pantalla - por eso NO puede usar el mecanismo en vivo de
 * LogroDescargosService::celebrar(). Se marca un campo persistente en
 * Empresa al otorgarlo, y Dashboard::mount() lo limpia en la primera
 * visita de CUALQUIER usuario del panel de esa empresa despues.
 */
class DashboardCelebraLogroSocializacionRitTest extends TestCase
{
    use RefreshDatabase;

    public function test_limpia_el_flag_de_celebracion_pendiente_al_visitar_el_dashboard(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'logro_socializacion_rit_pendiente_celebrar' => now()]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class);

        $this->assertNull($empresa->fresh()->logro_socializacion_rit_pendiente_celebrar);
    }

    public function test_no_falla_si_no_hay_nada_pendiente_de_celebrar(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'logro_socializacion_rit_pendiente_celebrar' => null]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)->assertOk();
    }
}
