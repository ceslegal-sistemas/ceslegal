<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\EmpresaResource\Pages\EditEmpresa;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2 de WhatsApp (ver docs/superpowers/specs/2026-10-19-whatsapp-fase1-otp-descargos-design.md):
 * al activar el toggle de WhatsApp en Mi Empresa, debe quedar constancia de
 * fecha/IP - mismo patrón de evidencia que el opt-in del trabajador en
 * Descargos. Prueba unitaria vía reflexión: el método es protected y el
 * Wizard completo de EditEmpresa tiene demasiados campos requeridos en
 * otros pasos como para montarlo entero solo para esta lógica aislada.
 */
class EmpresaOptInWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function invocarMutate(EditEmpresa $page, array $data): array
    {
        $metodo = new \ReflectionMethod($page, 'mutateFormDataBeforeSave');
        $metodo->setAccessible(true);

        return $metodo->invoke($page, $data);
    }

    public function test_activar_el_toggle_registra_consentimiento_con_fecha_e_ip(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'notificar_por_whatsapp' => false]);

        $page = new EditEmpresa();
        $page->record = $empresa;

        $resultado = $this->invocarMutate($page, ['notificar_por_whatsapp' => true]);

        $this->assertArrayHasKey('whatsapp_consentimiento_en', $resultado);
        $this->assertArrayHasKey('whatsapp_consentimiento_ip', $resultado);
        $this->assertNotNull($resultado['whatsapp_consentimiento_en']);
    }

    public function test_dejarlo_activo_sin_tocarlo_no_vuelve_a_escribir_el_consentimiento(): void
    {
        $empresa = Empresa::factory()->create([
            'active' => true,
            'notificar_por_whatsapp' => true,
            'whatsapp_consentimiento_en' => now()->subDays(10),
            'whatsapp_consentimiento_ip' => '1.2.3.4',
        ]);

        $page = new EditEmpresa();
        $page->record = $empresa;

        $resultado = $this->invocarMutate($page, ['notificar_por_whatsapp' => true]);

        $this->assertArrayNotHasKey('whatsapp_consentimiento_en', $resultado);
        $this->assertArrayNotHasKey('whatsapp_consentimiento_ip', $resultado);
    }

    public function test_desactivarlo_no_escribe_consentimiento_nuevo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'notificar_por_whatsapp' => true]);

        $page = new EditEmpresa();
        $page->record = $empresa;

        $resultado = $this->invocarMutate($page, ['notificar_por_whatsapp' => false]);

        $this->assertArrayNotHasKey('whatsapp_consentimiento_en', $resultado);
    }
}
