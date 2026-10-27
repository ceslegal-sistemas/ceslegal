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
 * Pedido del usuario (2026-10-27): "hazle diseño, ponlos en checkbox" - el
 * CheckboxList plano de canal (correo/WhatsApp) se reemplaza por tarjetas
 * con icono vía ProcesoDisciplinarioResource::campoCanalNotificacion()
 * (protected, se invoca por reflexión). Cubre: el partial Blade compila sin
 * error, el helper arma las opciones correctas, y WhatsApp se puede marcar
 * "Próximamente" (deshabilitado) cuando aún no hay plantilla aprobada por
 * Meta - caso real de "Enlace de impugnación".
 */
class CanalNotificacionDisenoTest extends TestCase
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

    private function invocarHelper(ProcesoDisciplinario $proceso, string $label = 'Enviar por', ?string $whatsappPendienteMensaje = null)
    {
        $metodo = new \ReflectionMethod(ProcesoDisciplinarioResource::class, 'campoCanalNotificacion');
        $metodo->setAccessible(true);

        return $metodo->invoke(null, $proceso, $label, $whatsappPendienteMensaje);
    }

    public function test_el_partial_de_opcion_compila_y_muestra_icono_y_detalle(): void
    {
        $html = view('filament.components.canal-opcion-checkbox', [
            'canal' => 'whatsapp',
            'titulo' => 'WhatsApp',
            'detalle' => '3001112233',
        ])->render();

        $this->assertStringContainsString('canal-opcion', $html);
        $this->assertStringContainsString('WhatsApp', $html);
        $this->assertStringContainsString('3001112233', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_el_partial_muestra_aviso_proximamente_cuando_esta_pendiente(): void
    {
        $html = view('filament.components.canal-opcion-checkbox', [
            'canal' => 'whatsapp',
            'titulo' => 'WhatsApp',
            'detalle' => 'Pendiente de aprobación en Meta',
            'pendiente' => true,
        ])->render();

        $this->assertStringContainsString('Próximamente', $html);
    }

    public function test_helper_arma_las_dos_opciones_con_clase_de_diseno(): void
    {
        $proceso = $this->proceso();

        $campo = $this->invocarHelper($proceso, 'Enviar por');

        $this->assertSame(['email', 'whatsapp'], array_keys($campo->getOptions()));
        $this->assertSame('canal-selector', $campo->getExtraAttributes()['class'] ?? null);
    }

    public function test_helper_omite_whatsapp_si_el_trabajador_no_tiene_telefono(): void
    {
        $proceso = $this->proceso(['telefono' => null]);

        $campo = $this->invocarHelper($proceso);

        $this->assertSame(['email'], array_keys($campo->getOptions()));
    }

    public function test_whatsapp_pendiente_queda_visible_pero_deshabilitado(): void
    {
        $proceso = $this->proceso();

        $campo = $this->invocarHelper($proceso, 'Enviar por', 'Pendiente de aprobación en Meta');

        $this->assertSame(['email', 'whatsapp'], array_keys($campo->getOptions()));
        $this->assertTrue($campo->isOptionDisabled('whatsapp', 'WhatsApp'));
        $this->assertFalse($campo->isOptionDisabled('email', 'Correo'));
        // No se marca por defecto una opción que el usuario no puede usar.
        $this->assertSame(['email'], array_values($campo->getDefaultState() ?? []));
    }
}
