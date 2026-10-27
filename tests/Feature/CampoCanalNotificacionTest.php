<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido del usuario (2026-10-26): selector de canal (correo/WhatsApp) en
 * los puntos de notificación del proceso disciplinario, vía
 * ProcesoDisciplinarioResource::camposCanalNotificacion() (protected, se
 * invoca por reflexión). Pedido explícito del usuario, 2026-10-27: "cambia
 * el checkbox por el toggle de activar o desactivar" - dos Toggle nativos
 * de Filament ('canal_email'/'canal_whatsapp') en vez de un CheckboxList.
 * Cubre la lógica: campos según si el trabajador tiene teléfono, y
 * WhatsApp deshabilitado cuando aún no hay plantilla aprobada por Meta -
 * caso real de "Enlace de impugnación".
 */
class CampoCanalNotificacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function proceso(array $trabajadorOverrides = []): ProcesoDisciplinario
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '1007568732',
            'genero' => 'masculino',
            'nombres' => 'Carlos',
            'apellidos' => 'Ruiz',
            'cargo' => 'Vendedor',
            'email' => 'carlos@test.com',
            'telefono' => '3001112233',
            'active' => true,
        ], $trabajadorOverrides));

        return ProcesoDisciplinario::create([
            'codigo' => 'PD-DISENO-' . uniqid(),
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
    }

    /** @return array<\Filament\Forms\Components\Toggle> */
    private function invocarHelper(ProcesoDisciplinario $proceso, string $label = 'Enviar por', ?string $whatsappPendienteMensaje = null): array
    {
        $metodo = new \ReflectionMethod(ProcesoDisciplinarioResource::class, 'camposCanalNotificacion');
        $metodo->setAccessible(true);

        return $metodo->invoke(null, $proceso, $label, $whatsappPendienteMensaje);
    }

    public function test_helper_arma_los_dos_toggles(): void
    {
        $proceso = $this->proceso();

        $campos = $this->invocarHelper($proceso, 'Enviar por');

        $this->assertCount(2, $campos);
        $this->assertSame('canal_email', $campos[0]->getName());
        $this->assertSame('canal_whatsapp', $campos[1]->getName());
        $this->assertStringContainsString('carlos@test.com', $campos[0]->getHelperText());
        $this->assertStringContainsString('3001112233', $campos[1]->getHelperText());
        $this->assertTrue($campos[0]->getDefaultState());
    }

    public function test_helper_omite_whatsapp_si_el_trabajador_no_tiene_telefono(): void
    {
        $proceso = $this->proceso(['telefono' => null]);

        $campos = $this->invocarHelper($proceso);

        $this->assertCount(1, $campos);
        $this->assertSame('canal_email', $campos[0]->getName());
    }

    public function test_whatsapp_pendiente_queda_visible_pero_deshabilitado(): void
    {
        $proceso = $this->proceso();

        $campos = $this->invocarHelper($proceso, 'Enviar por', 'Pendiente de aprobación en Meta');

        $this->assertCount(2, $campos);
        $this->assertStringContainsString('Pendiente de aprobación en Meta', $campos[1]->getHelperText());
        // No se marca activado por defecto un canal que el usuario no puede usar.
        $this->assertFalse($campos[1]->getDefaultState());
    }
}
