<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ArticuloLegalResource\Pages\ListArticuloLegals;
use App\Filament\Admin\Resources\ArticuloLegalResource\Widgets\ProgresoScraperCstWidget;
use App\Jobs\ActualizarArticulosCstJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Bug real (2026-09-24): wire:poll.2000ms en el div raíz del widget SIN
 * condición hacía que cualquier visita a "Artículos Legales" disparara
 * /livewire/update cada 2 segundos para siempre, sin importar si había o no
 * un scraper corriendo - contribuía al 429 intermitente en admin.ceslegal.co.
 * Ver memoria incidente-cloudflare-522-520-saturacion-phpfpm.
 */
class ProgresoScraperCstWidgetPollCondicionalTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarConPermiso(): User
    {
        Permission::findOrCreate('view_any_articulo::legal', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo('view_any_articulo::legal');
        $this->actingAs($user);

        return $user;
    }

    public function test_no_hay_wire_poll_si_no_hay_ninguna_corrida_registrada(): void
    {
        $this->autenticarConPermiso();
        Cache::forget(ActualizarArticulosCstJob::CACHE_KEY);

        $html = Livewire::test(ProgresoScraperCstWidget::class)->html();

        $this->assertStringNotContainsString('wire:poll', $html);
    }

    public function test_no_hay_wire_poll_si_el_scraper_ya_completo(): void
    {
        $this->autenticarConPermiso();
        Cache::put(ActualizarArticulosCstJob::CACHE_KEY, ['estado' => 'completado', 'ok' => 10, 'skip' => 0, 'errores' => 0]);

        $html = Livewire::test(ProgresoScraperCstWidget::class)->html();

        $this->assertStringNotContainsString('wire:poll', $html);
    }

    public function test_si_hay_wire_poll_mientras_el_scraper_esta_procesando(): void
    {
        $this->autenticarConPermiso();
        Cache::put(ActualizarArticulosCstJob::CACHE_KEY, ['estado' => 'procesando', 'actual' => 5, 'total' => 490]);

        $html = Livewire::test(ProgresoScraperCstWidget::class)->html();

        $this->assertStringContainsString('wire:poll', $html);
    }

    public function test_el_boton_actualizar_dispara_el_evento_que_despierta_el_widget(): void
    {
        Queue::fake();
        $this->autenticarConPermiso();
        Cache::forget(ActualizarArticulosCstJob::CACHE_KEY);

        Livewire::test(ListArticuloLegals::class)
            ->callAction('actualizarArticulosCst')
            ->assertDispatched('scraper-cst-iniciado');

        Queue::assertPushed(ActualizarArticulosCstJob::class);
    }
}
