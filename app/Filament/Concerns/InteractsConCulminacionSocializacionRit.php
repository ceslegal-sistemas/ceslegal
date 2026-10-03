<?php

namespace App\Filament\Concerns;

use App\Models\ConfiguracionTexto;
use App\Models\CulminacionSocializacionRit;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Support\EmpresaActiva;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Botón manual "Culminar Socialización del RIT" (pedido de Andrés Sarmiento,
 * 2026-10-03) - compartido entre "Mi Reglamento Interno" y el Dashboard
 * (pedido del usuario, mismo día: "en el dashboard no sale el boton" - el
 * botón vive dentro de la misma tarjeta "Comparte el Reglamento" en ambas
 * páginas, así que ambas necesitan poder montar la acción).
 *
 * A diferencia de InteractsConAceptacionMejoraRIT (que exige que el host
 * exponga $empresa/$reglamento), este trait resuelve la empresa y el RIT
 * POR SU CUENTA en cada llamada - así funciona en páginas como el Dashboard
 * que no mantienen esas propiedades. Mismo criterio bufete/admin/cliente ya
 * usado en MiReglamentoInterno::mount().
 *
 * La página que use este trait debe exponer guardarFotoVerificacion() (ver
 * App\Filament\Concerns\HasVerificacionFotografica) - inclúyelo aparte, NO
 * dentro de este trait, para evitar colisión si el host ya lo trae por otro
 * lado (ej. MiReglamentoInterno lo trae vía InteractsConAceptacionMejoraRIT).
 */
trait InteractsConCulminacionSocializacionRit
{
    private function empresaParaCulminacion(): ?Empresa
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        if ($user->esAbogadoDeBufete()) {
            $activaId = EmpresaActiva::id();
            return $activaId ? Empresa::find($activaId) : null;
        }

        if ($user->hasRole('super_admin') || $user->hasRole('abogado')) {
            $activaId = EmpresaActiva::id();
            return $activaId ? Empresa::find($activaId) : Empresa::first();
        }

        return $user->empresa;
    }

    public function culminacionVigente(): ?CulminacionSocializacionRit
    {
        $empresa = $this->empresaParaCulminacion();
        $reglamento = $empresa?->reglamentoInterno;

        if (!$empresa || !$reglamento || empty($reglamento->texto_completo)) {
            return null;
        }

        return CulminacionSocializacionRit::where('empresa_id', $empresa->id)
            ->where('texto_rit_hash', hash('sha256', $reglamento->texto_completo))
            ->latest('declarado_en')
            ->first();
    }

    public function culminarSocializacionAction(): Action
    {
        return Action::make('culminarSocializacion')
            ->label('Culminar Socialización del RIT')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            // Pedido explícito del usuario (2026-10-03): solo tiene sentido
            // CULMINAR la socialización una vez se entró en Fase 2 (pasados
            // los 15 días hábiles de objeción) - durante la Fase 1
            // (publicación) el botón debe quedar invisible, sin importar
            // cuántos trabajadores haya registrados.
            ->visible(function () {
                $empresa = $this->empresaParaCulminacion();
                $reglamento = $empresa?->reglamentoInterno;

                return $reglamento
                    && !empty($reglamento->texto_completo)
                    && $reglamento->faseSocializacionActual() === 'socializacion'
                    && !$this->culminacionVigente();
            })
            ->modalHeading('Culminar Socialización del Reglamento Interno')
            ->modalDescription('Esta acción cierra el proceso de socialización bajo su responsabilidad. Antes de continuar, confirme que efectivamente notificó a todos sus trabajadores.')
            ->modalSubmitActionLabel('Confirmar y culminar')
            ->modalWidth('lg')
            ->form([
                Placeholder::make('disclaimer')
                    ->hiddenLabel()
                    ->content(function () {
                        $empresa = $this->empresaParaCulminacion();
                        $texto = str_replace(
                            ':empresa',
                            $empresa?->razon_social ?? '',
                            ConfiguracionTexto::obtener('disclaimer_culminacion_socializacion', config('ces.disclaimer_culminacion_socializacion', ''))
                        );

                        return new HtmlString(
                            preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '<strong>$1</strong>', e($texto))
                        );
                    }),
                Checkbox::make('declaracion_aceptada')
                    ->label('Declaro que lo anterior es cierto.')
                    ->accepted()
                    ->required(),

                Placeholder::make('foto_verificacion')
                    ->label('Verificación fotográfica')
                    ->helperText('Equivalencia funcional de su firma: confirma que usted, y no otra persona, culmina este proceso.')
                    ->content(function ($livewire) {
                        $indice = max(0, count($livewire->mountedActions ?? []) - 1);

                        return view('filament.components.webcam-autorizador', [
                            'wireTargetPath' => "mountedActionsData.{$indice}.foto_admin_base64",
                        ]);
                    }),
                Hidden::make('foto_admin_base64'),
            ])
            ->action(function (array $data, Action $action): void {
                $empresa = $this->empresaParaCulminacion();
                $reglamento = $empresa?->reglamentoInterno;

                if (!$empresa || !$reglamento) {
                    return;
                }

                if (empty($data['foto_admin_base64'])) {
                    Notification::make()
                        ->danger()
                        ->title('Falta la verificación fotográfica')
                        ->body('Debe tomar la foto de verificación antes de continuar.')
                        ->persistent()
                        ->send();

                    $action->halt();
                }

                $fotoPath = $this->guardarFotoVerificacion(
                    $data['foto_admin_base64'] ?? null,
                    "fotos-verificacion/culminacion-socializacion/{$empresa->id}",
                );

                CulminacionSocializacionRit::create([
                    'empresa_id' => $empresa->id,
                    'reglamento_interno_id' => $reglamento->id,
                    'user_id' => Auth::id(),
                    'texto_rit_hash' => hash('sha256', $reglamento->texto_completo),
                    'foto_admin_path' => $fotoPath,
                    'declarado_en' => now(),
                    'ip' => request()->ip(),
                    'user_agent' => (string) request()->userAgent(),
                ]);

                Notification::make()
                    ->success()
                    ->title('Socialización culminada')
                    ->body('Quedó registrado que culminó la socialización del Reglamento Interno con sus trabajadores.')
                    ->send();
            });
    }
}
