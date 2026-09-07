<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BibliotecaLegalResource\Pages\EditBibliotecaLegal;
use App\Filament\Admin\Resources\BibliotecaLegalResource\Pages\ListBibliotecaLegals;
use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Trazabilidad de impacto (2026-09-07): si un documento genera sugerencias
 * ya aprobadas/aplicadas y luego resulta ser el equivocado, el abogado
 * necesita ver de un vistazo a qué empresas afectó - sin esto, la única
 * forma de saberlo era recordarlo de memoria. No revierte nada, solo hace
 * visible el radio de impacto.
 */
class BibliotecaLegalTrazabilidadImpactoTest extends TestCase
{
    use RefreshDatabase;

    protected function usuario(): User
    {
        Permission::findOrCreate('view_any_biblioteca::legal', 'web');
        Permission::findOrCreate('update_biblioteca::legal', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_any_biblioteca::legal', 'update_biblioteca::legal']);
        $this->actingAs($user);
        return $user;
    }

    private function crearSugerencia(DocumentoLegal $documento, string $empresaNombre, string $estado): SugerenciaActualizacionRit
    {
        $empresa = Empresa::factory()->create(['active' => true, 'razon_social' => $empresaNombre]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Artículo único.',
        ]);

        return SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Artículo único.',
            'texto_propuesto' => 'Artículo único modificado.',
            'justificacion_ia' => 'Prueba.',
            'estado' => $estado,
        ]);
    }

    public function test_boton_ver_impacto_no_aparece_sin_sugerencias(): void
    {
        $this->usuario();
        $documento = DocumentoLegal::create(['titulo' => 'Sin impacto', 'tipo' => 'otro', 'estado' => 'procesado', 'activo' => true]);

        Livewire::test(ListBibliotecaLegals::class)
            ->assertTableActionHidden('ver_impacto', $documento);
    }

    public function test_ver_impacto_aparece_visible_cuando_hay_sugerencias(): void
    {
        $this->usuario();
        $documento = DocumentoLegal::create(['titulo' => 'Política con impacto', 'tipo' => 'otro', 'estado' => 'procesado', 'activo' => true]);
        $this->crearSugerencia($documento, 'RENBEL', 'aprobada');

        Livewire::test(ListBibliotecaLegals::class)
            ->assertTableActionVisible('ver_impacto', $documento);

        Livewire::test(EditBibliotecaLegal::class, ['record' => $documento->id])
            ->assertActionVisible('ver_impacto');
    }

    /**
     * El contenido real del modal (view biblioteca-legal-impacto.blade.php)
     * se prueba renderizando la vista directamente - Filament difiere la
     * salida HTML de ->modalContent() hasta que el modal se abre del lado
     * del cliente (Alpine x-show), por lo que mountTableAction()/
     * callTableAction() no la exponen de forma confiable en las
     * aserciones de Livewire::test().
     */
    public function test_la_vista_de_impacto_agrupa_por_empresa_y_muestra_el_estado(): void
    {
        $documento = DocumentoLegal::create(['titulo' => 'Política con impacto', 'tipo' => 'otro', 'estado' => 'procesado', 'activo' => true]);
        $this->crearSugerencia($documento, 'RENBEL', 'aprobada');
        $this->crearSugerencia($documento, 'ACME', 'pendiente');

        $html = view('filament.components.biblioteca-legal-impacto', ['documento' => $documento])->render();

        $this->assertStringContainsString('RENBEL', $html);
        $this->assertStringContainsString('ACME', $html);
        $this->assertStringContainsString('Aprobada', $html);
        $this->assertStringContainsString('Pendiente', $html);
    }

    public function test_la_vista_de_impacto_muestra_el_intro_cuando_se_pasa(): void
    {
        $documento = DocumentoLegal::create(['titulo' => 'Política con impacto', 'tipo' => 'otro', 'estado' => 'procesado', 'activo' => true]);
        $this->crearSugerencia($documento, 'RENBEL', 'aprobada');

        $html = view('filament.components.biblioteca-legal-impacto', [
            'documento' => $documento,
            'intro' => 'No se puede eliminar sin perder la trazabilidad legal de estos cambios.',
        ])->render();

        $this->assertStringContainsString('RENBEL', $html);
        $this->assertStringContainsString('No se puede eliminar sin perder la trazabilidad legal', $html);
    }

    public function test_la_vista_de_impacto_sin_sugerencias_muestra_mensaje_vacio(): void
    {
        $documento = DocumentoLegal::create(['titulo' => 'Sin sugerencias', 'tipo' => 'otro', 'estado' => 'procesado', 'activo' => true]);

        $html = view('filament.components.biblioteca-legal-impacto', ['documento' => $documento])->render();

        $this->assertStringContainsString('todavía no generó ninguna sugerencia', $html);
    }

    /**
     * Pedido explícito del usuario (2026-09-07): tras desactivar, "Activar"
     * solo aparecía dentro de Editar (toggle manual) - faltaba el botón
     * simétrico en la tabla y en el header de Editar.
     */
    public function test_boton_activar_aparece_en_la_tabla_cuando_esta_inactivo_y_reactiva(): void
    {
        $this->usuario();
        $documento = DocumentoLegal::create(['titulo' => 'Documento inactivo', 'tipo' => 'otro', 'estado' => 'procesado', 'activo' => false]);

        Livewire::test(ListBibliotecaLegals::class)
            ->assertTableActionHidden('desactivar_bloqueado', $documento)
            ->assertTableActionVisible('activar', $documento)
            ->callTableAction('activar', $documento);

        $this->assertTrue($documento->fresh()->activo);
    }

    public function test_boton_activar_no_aparece_si_ya_esta_activo(): void
    {
        $this->usuario();
        $documento = DocumentoLegal::create(['titulo' => 'Documento activo', 'tipo' => 'otro', 'estado' => 'procesado', 'activo' => true]);

        Livewire::test(ListBibliotecaLegals::class)
            ->assertTableActionHidden('activar', $documento);
    }

    public function test_boton_activar_tambien_esta_en_la_pagina_editar(): void
    {
        $this->usuario();
        $documento = DocumentoLegal::create(['titulo' => 'Documento inactivo', 'tipo' => 'otro', 'estado' => 'procesado', 'activo' => false]);

        Livewire::test(EditBibliotecaLegal::class, ['record' => $documento->id])
            ->assertActionVisible('activar')
            ->assertActionHidden('desactivar_bloqueado')
            ->callAction('activar');

        $this->assertTrue($documento->fresh()->activo);
    }
}
