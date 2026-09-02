<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario con captura de pantalla de producción:
 * un cliente (panel "empresa") seleccionó suspensión + días y al confirmar
 * la sanción, el sistema lo redirigía a `/admin/proceso-disciplinarios` -
 * un panel al que el rol "cliente" no tiene acceso - resultando en un 403.
 *
 * Causa raíz: `ProcesoDisciplinarioResource` se descubre en AMBOS paneles
 * (admin y empresa, mismo directorio de recursos), pero 6 sitios distintos
 * de este archivo redirigían con `redirect()->route('filament.admin.
 * resources.proceso-disciplinarios.index')` - el nombre de ruta del panel
 * "admin" a secas, sin importar en qué panel estaba realmente el usuario.
 * El propio archivo ya usaba el patrón correcto en otro lado
 * (`static::getUrl('index')`, panel-agnóstico - resuelve al panel activo
 * de Filament::getCurrentPanel()) - se unificó a ese patrón.
 */
class ProcesoDisciplinarioRedirigeAlPanelActualTest extends TestCase
{
    public function test_getUrl_resuelve_al_panel_empresa_cuando_ese_es_el_panel_activo(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('empresa'));

        $url = ProcesoDisciplinarioResource::getUrl('index');

        $this->assertStringContainsString('/empresa/', $url);
        $this->assertStringNotContainsString('/admin/', $url);
    }

    public function test_getUrl_resuelve_al_panel_admin_cuando_ese_es_el_panel_activo(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $url = ProcesoDisciplinarioResource::getUrl('index');

        $this->assertStringContainsString('/admin/', $url);
    }

    /**
     * Ningún redirect de esta Resource debe volver a hardcodear el nombre
     * de ruta del panel "admin" - si alguien copia y pega el patrón viejo,
     * este test lo atrapa.
     */
    public function test_no_hay_redirects_hardcodeados_al_panel_admin(): void
    {
        $contenido = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        $this->assertStringNotContainsString(
            "route('filament.admin.resources.proceso-disciplinarios.index')",
            $contenido
        );
    }
}
