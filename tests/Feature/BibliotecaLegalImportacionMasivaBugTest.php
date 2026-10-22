<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BibliotecaLegalResource\Pages\ListBibliotecaLegals;
use App\Jobs\ProcesarBibliotecaLegalMasivo;
use App\Models\DocumentoLegal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Bug real reportado en producción (2026-10-22): el modal "Importar varios
 * documentos" mostraba los archivos como subidos, pero al pulsar "Importar"
 * salía "No se importó ningún documento" - ver el comentario en
 * ListBibliotecaLegals::getHeaderActions() para la causa raíz
 * (FileUpload guardaba los archivos y los convertía a rutas de texto ANTES
 * de que corriera la acción, así que el `instanceof TemporaryUploadedFile`
 * del loop fallaba siempre).
 */
class BibliotecaLegalImportacionMasivaBugTest extends TestCase
{
    use RefreshDatabase;

    protected function usuario(): \App\Models\User
    {
        Permission::findOrCreate('view_any_biblioteca::legal', 'web');
        $user = \App\Models\User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_any_biblioteca::legal']);
        $this->actingAs($user);
        return $user;
    }

    public function test_importar_varios_documentos_crea_un_registro_por_archivo(): void
    {
        $this->usuario();
        Queue::fake();

        Livewire::test(ListBibliotecaLegals::class)
            ->mountAction('importar_masivo')
            ->setActionData([
                'archivos' => [
                    UploadedFile::fake()->create('ley-2466.pdf', 10),
                    UploadedFile::fake()->create('sentencia-t239.pdf', 10),
                ],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(2, DocumentoLegal::count(), 'Debieron crearse 2 documentos, uno por archivo subido.');

        $titulos = DocumentoLegal::pluck('titulo')->sort()->values()->all();
        $this->assertSame(['ley-2466', 'sentencia-t239'], $titulos);

        DocumentoLegal::each(function (DocumentoLegal $documento) {
            $this->assertSame('otro', $documento->tipo);
            $this->assertSame('pendiente', $documento->estado);
            $this->assertNotEmpty($documento->archivo_path);
        });

        Queue::assertPushed(ProcesarBibliotecaLegalMasivo::class, 2);
    }
}
