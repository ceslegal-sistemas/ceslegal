<?php

namespace App\Jobs;

use App\Models\ReglamentoInterno;
use App\Models\User;
use App\Services\RITGeneratorService;
use Filament\Notifications\Actions\Action as NotifAction;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerarTextoRITJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Generación capítulo por capítulo: 16 caps × ~30s = hasta 480s. */
    public int $timeout = 600;

    /** Sin reintentos automáticos: el cascade de modelos maneja la redundancia. */
    public int $tries = 1;

    public function __construct(
        public readonly ReglamentoInterno $rit,
        public readonly int               $userId,
    ) {
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(RITGeneratorService $service): void
    {
        $rit = $this->rit->fresh();
        if (!$rit) return;

        $empresa = $rit->empresa;
        $data    = $rit->respuestas_cuestionario ?? [];

        // Generar capítulo por capítulo, actualizando el progreso en BD
        $textoRIT = $service->generarCapitulosRIT(
            $data,
            $empresa,
            function (int $cap, int $total, string $titulo) use ($rit): void {
                $rit->update(['progreso_generacion' => "Capítulo {$cap}/{$total}: {$titulo}"]);
            }
        );

        // Post-procesar: eliminar placeholders que Gemini pueda haber dejado
        $representante = $empresa->representante_legal ?? '_______________';
        $textoRIT = str_replace(
            [
                '[DÍA]', '[MES]', '[AÑO]',
                '[NOMBRE DEL REPRESENTANTE LEGAL]', '[NOMBRE REPRESENTANTE LEGAL]',
                '[NÚMERO DE CÉDULA]', '[NÚMERO CÉDULA]',
                '[NIT]', '[RAZÓN SOCIAL]', '[DOMICILIO]',
            ],
            [
                now()->day, now()->locale('es')->translatedFormat('F'), now()->year,
                $representante, $representante,
                '_______________', '_______________',
                $empresa->nit           ?? '_______________',
                $empresa->razon_social  ?? '_______________',
                trim(($empresa->direccion ?? '') . ' ' . ($empresa->ciudad ?? '')),
            ],
            $textoRIT
        );

        // Persistir texto y activar el reglamento
        $rit->update([
            'nombre'               => 'Reglamento Interno generado con IA — ' . now()->format('d/m/Y'),
            'texto_completo'       => $textoRIT,
            'activo'               => true,
            'estado_generacion'    => 'completado',
            'mensaje_error_ia'     => null,
            'progreso_generacion'  => null,
        ]);

        // Guardar DOCX en disco público (no fatal si falla)
        $rutaDocx = $service->guardarDocxPublico($textoRIT, $empresa);
        if ($rutaDocx) {
            $rit->update(['ruta_docx' => $rutaDocx]);
        }

        Log::info('GenerarTextoRITJob: completado', [
            'rit_id'     => $rit->id,
            'empresa_id' => $empresa->id,
            'modelo'     => $service->modeloUsado,
            'fallback'   => $service->esFallbackLite,
        ]);

        // Notificar al usuario por base de datos
        $user = User::find($this->userId);
        if (!$user) return;

        $title = '¡Reglamento Interno generado!';
        $body  = 'Su RIT fue redactado con IA y está listo para descargar.';

        if ($service->esFallbackLite) {
            $title = 'Reglamento generado con modelo alternativo';
            $body  = 'Los modelos principales estaban con alta demanda. Le recomendamos ejecutar la auditoría antes de presentar el documento.';
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->actions([
                NotifAction::make('ver')
                    ->label('Ver Reglamento')
                    ->url(route('filament.admin.pages.mi-reglamento-interno'))
                    ->button(),
            ])
            ->sendToDatabase($user);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerarTextoRITJob: falló', [
            'rit_id' => $this->rit->id,
            'error'  => $e->getMessage(),
        ]);

        $rit = $this->rit->fresh();
        if ($rit) {
            $rit->update([
                'estado_generacion' => 'error',
                'mensaje_error_ia'  => $e->getMessage(),
            ]);
        }

        $user = User::find($this->userId);
        if (!$user) return;

        Notification::make()
            ->title('Error al generar el Reglamento Interno')
            ->body('No se pudo generar el texto del RIT. Puede reintentarlo desde "Mi Reglamento Interno".')
            ->danger()
            ->actions([
                NotifAction::make('reintentar')
                    ->label('Ir a reintentar')
                    ->url(route('filament.admin.pages.mi-reglamento-interno'))
                    ->button(),
            ])
            ->sendToDatabase($user);
    }
}
