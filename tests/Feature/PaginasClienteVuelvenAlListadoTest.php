<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Filament\Admin\Resources\ModificacionContractualResource\Pages\CreateModificacionContractual;
use App\Filament\Admin\Resources\ModificacionContractualResource\Pages\EditModificacionContractual;
use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages\CreateProcesoDisciplinario;
use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages\EditProcesoDisciplinario;
use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\CreateSolicitudContrato;
use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\EditSolicitudContrato;
use App\Filament\Admin\Resources\TrabajadorResource;
use App\Filament\Admin\Resources\TrabajadorResource\Pages\CreateTrabajador;
use App\Filament\Admin\Resources\TrabajadorResource\Pages\EditTrabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-07): en las páginas de Crear/Editar
 * de los recursos que ve el cliente (panel 'empresa'), guardar debe volver
 * al listado con aviso de "Guardado" - y como la gente no entiende las
 * migajas de pan, debe haber un botón "Volver al listado" visible.
 *
 * Se invocan los métodos protegidos getRedirectUrl()/getHeaderActions() por
 * reflexión, sin montar el ciclo de vida completo de Livewire - estos 4
 * wizards son enormes (uno pasa de 1800 líneas) y su autorización/mount
 * completo ya está cubierto por otros tests de este proyecto; lo que hace
 * falta verificar aquí es solo la config de redirección y el botón nuevo.
 */
class PaginasClienteVuelvenAlListadoTest extends TestCase
{
    use RefreshDatabase;

    private function redirectUrlDe(string $claseDePagina): string
    {
        $pagina = new $claseDePagina();

        // CreateSolicitudContrato::getRedirectUrl() ya usaba $this->record->id
        // desde antes (resalta la fila recién creada en el listado, ver
        // SolicitudContratoRedireccionAlListadoTest.php) - fuera del ciclo de
        // vida real de Filament, $this->record nunca se asignó solo. Se
        // simula aquí igual que ya hace ese test dedicado.
        if ($claseDePagina === CreateSolicitudContrato::class) {
            $registroFalso = new \App\Models\SolicitudContrato();
            $registroFalso->id = 1;

            $reflProp = new \ReflectionProperty($claseDePagina, 'record');
            $reflProp->setAccessible(true);
            $reflProp->setValue($pagina, $registroFalso);
        }

        $metodo = new \ReflectionMethod($claseDePagina, 'getRedirectUrl');
        $metodo->setAccessible(true);

        return $metodo->invoke($pagina);
    }

    private function tieneBotonVolver(string $claseDePagina): bool
    {
        $pagina = new $claseDePagina();
        $metodo = new \ReflectionMethod($claseDePagina, 'getHeaderActions');
        $metodo->setAccessible(true);
        $acciones = $metodo->invoke($pagina);

        foreach ($acciones as $accion) {
            if ($accion->getName() === 'volver') {
                return true;
            }
        }

        return false;
    }

    public static function paginasProvider(): array
    {
        return [
            'Crear Citación de Descargos' => [CreateProcesoDisciplinario::class, ProcesoDisciplinarioResource::class],
            'Editar Proceso Disciplinario' => [EditProcesoDisciplinario::class, ProcesoDisciplinarioResource::class],
            'Crear Trabajador' => [CreateTrabajador::class, TrabajadorResource::class],
            'Editar Trabajador' => [EditTrabajador::class, TrabajadorResource::class],
            'Crear Solicitud de Contrato' => [CreateSolicitudContrato::class, SolicitudContratoResource::class],
            'Editar Solicitud de Contrato' => [EditSolicitudContrato::class, SolicitudContratoResource::class],
            'Crear Otrosí de Contrato' => [CreateModificacionContractual::class, ModificacionContractualResource::class],
            'Editar Otrosí de Contrato' => [EditModificacionContractual::class, ModificacionContractualResource::class],
        ];
    }

    /** @dataProvider paginasProvider */
    public function test_redirige_al_listado_al_guardar(string $claseDePagina, string $claseDeResource): void
    {
        $this->assertSame($claseDeResource::getUrl('index'), $this->redirectUrlDe($claseDePagina));
    }

    /** @dataProvider paginasProvider */
    public function test_tiene_boton_volver_al_listado(string $claseDePagina): void
    {
        $this->assertTrue($this->tieneBotonVolver($claseDePagina), "{$claseDePagina} no tiene el botón 'volver'.");
    }
}
