<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BibliotecaLegalResource;
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
 * Bug real en producción (2026-09-07): eliminar un DocumentoLegal con
 * sugerencias de actualización del RIT asociadas tiraba un 500 (Integrity
 * constraint violation 1451, FK RESTRICT en documento_legal_id). Primer fix:
 * bloquear el borrado con un mensaje. El usuario pidió ir más allá: si de
 * todas formas no se puede eliminar, el botón "Eliminar" no debería ni
 * aparecer - se reemplaza por un botón "Desactivar" real (un clic, sin
 * pasar por el formulario de Editar) cuando el documento tiene sugerencias
 * asociadas.
 */
class BibliotecaLegalNoBorraConSugerenciasTest extends TestCase
{
    use RefreshDatabase;

    protected function usuario(): User
    {
        Permission::findOrCreate('view_any_biblioteca::legal', 'web');
        Permission::findOrCreate('delete_biblioteca::legal', 'web');
        Permission::findOrCreate('delete_any_biblioteca::legal', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_any_biblioteca::legal', 'delete_biblioteca::legal', 'delete_any_biblioteca::legal']);
        $this->actingAs($user);
        return $user;
    }

    private function crearDocumentoConSugerencia(): DocumentoLegal
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Artículo único.',
        ]);
        $documento = DocumentoLegal::create([
            'titulo' => 'Ley 2466 de 2025',
            'tipo' => 'ley',
            'estado' => 'procesado',
            'activo' => true,
        ]);
        SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Artículo único.',
            'texto_propuesto' => 'Artículo único modificado.',
            'justificacion_ia' => 'Prueba.',
            'estado' => 'aprobada',
        ]);

        return $documento;
    }

    public function test_el_boton_eliminar_no_aparece_si_el_documento_tiene_sugerencias(): void
    {
        $this->usuario();
        $documento = $this->crearDocumentoConSugerencia();

        Livewire::test(BibliotecaLegalResource\Pages\ListBibliotecaLegals::class)
            ->assertTableActionHidden('delete', $documento)
            ->assertTableActionVisible('desactivar_bloqueado', $documento);
    }

    public function test_el_boton_desactivar_reemplaza_a_eliminar_y_funciona(): void
    {
        $this->usuario();
        $documento = $this->crearDocumentoConSugerencia();

        Livewire::test(BibliotecaLegalResource\Pages\ListBibliotecaLegals::class)
            ->callTableAction('desactivar_bloqueado', $documento);

        $this->assertNotNull(DocumentoLegal::find($documento->id), 'Desactivar no debe borrar el registro.');
        $this->assertFalse($documento->fresh()->activo);
        $this->assertDatabaseHas('sugerencias_actualizacion_rit', ['documento_legal_id' => $documento->id]);
    }

    public function test_bulk_delete_tambien_se_bloquea_si_alguno_tiene_sugerencias(): void
    {
        $this->usuario();
        $documentoConSugerencia = $this->crearDocumentoConSugerencia();
        $documentoLibre = DocumentoLegal::create([
            'titulo' => 'Documento sin sugerencias',
            'tipo' => 'otro',
            'estado' => 'procesado',
            'activo' => true,
        ]);

        Livewire::test(BibliotecaLegalResource\Pages\ListBibliotecaLegals::class)
            ->callTableBulkAction('delete', [$documentoConSugerencia, $documentoLibre]);

        $this->assertNotNull(DocumentoLegal::find($documentoConSugerencia->id));
        $this->assertNotNull(DocumentoLegal::find($documentoLibre->id), 'No debe borrar tampoco el que sí era seguro, para no dejar un borrado parcial sorpresa.');
    }

    public function test_si_puede_eliminar_un_documento_sin_sugerencias(): void
    {
        $this->usuario();
        $documento = DocumentoLegal::create([
            'titulo' => 'Documento sin sugerencias',
            'tipo' => 'otro',
            'estado' => 'procesado',
            'activo' => true,
        ]);

        Livewire::test(BibliotecaLegalResource\Pages\ListBibliotecaLegals::class)
            ->callTableAction('delete', $documento);

        $this->assertNull(DocumentoLegal::find($documento->id));
    }

    /**
     * Regresión exacta del bug real: el primer fix solo cubrió el botón
     * "Eliminar" de la tabla/listado - la página "Editar" tiene su PROPIA
     * acción de eliminar (EditBibliotecaLegal::getHeaderActions()), separada
     * por completo, que seguía sin la guarda.
     */
    public function test_en_la_pagina_editar_tambien_se_reemplaza_eliminar_por_desactivar(): void
    {
        Permission::findOrCreate('update_biblioteca::legal', 'web');
        $usuario = $this->usuario();
        $usuario->givePermissionTo('update_biblioteca::legal');
        $documento = $this->crearDocumentoConSugerencia();

        Livewire::test(BibliotecaLegalResource\Pages\EditBibliotecaLegal::class, ['record' => $documento->id])
            ->assertActionHidden('delete')
            ->assertActionVisible('desactivar_bloqueado')
            ->callAction('desactivar_bloqueado');

        $this->assertNotNull(DocumentoLegal::find($documento->id));
        $this->assertFalse($documento->fresh()->activo);
    }

    public function test_el_boton_desactivar_no_aparece_si_ya_esta_inactivo(): void
    {
        $this->usuario();
        $documento = $this->crearDocumentoConSugerencia();
        $documento->update(['activo' => false]);

        Livewire::test(BibliotecaLegalResource\Pages\ListBibliotecaLegals::class)
            ->assertTableActionHidden('delete', $documento)
            ->assertTableActionHidden('desactivar_bloqueado', $documento);
    }
}
