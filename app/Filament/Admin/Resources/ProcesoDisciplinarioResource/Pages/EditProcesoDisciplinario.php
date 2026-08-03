<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\Trabajador;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditProcesoDisciplinario extends EditRecord
{
    protected static string $resource = ProcesoDisciplinarioResource::class;

    /** Hash del último texto de "hechos" ya enviado a clasificarIncidente() -
     *  evita reclasificar con la IA si el texto no cambió desde la última vez. */
    public ?string $ultimoTextoAutoClasificado = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Versión automática de la acción "clasificarGravedadIA" del formulario
     * (ver ProcesoDisciplinarioResource::form()): se dispara sola cuando el
     * usuario deja de escribir un momento en el campo "hechos", en vez de
     * esperar a que haga clic en el botón. Silenciosa (sin notificaciones)
     * para no interrumpir cada vez que el texto se estabiliza, con longitud
     * mínima más alta que el botón manual y un hash del último texto ya
     * clasificado para no repetir la llamada si el texto no cambió.
     */
    public function autoClasificarGravedad(): void
    {
        $hechos = trim(strip_tags($this->data['hechos'] ?? ''));
        $trabajadorId = $this->data['trabajador_id'] ?? null;
        $empresaId = $this->data['empresa_id'] ?? null;

        if (!$trabajadorId || !$empresaId || mb_strlen($hechos) < 60) {
            return;
        }

        $hash = md5($hechos);
        if ($this->ultimoTextoAutoClasificado === $hash) {
            return;
        }
        $this->ultimoTextoAutoClasificado = $hash;

        $trabajador = Trabajador::find($trabajadorId);

        $motivosSeleccionados = $this->data['sanciones_laborales_ids'] ?? [];
        $motivosRit = [];
        $conductas = app(\App\Services\ReglamentoInternoService::class)
            ->conductasSancionablesDeEmpresa((int) $empresaId);
        foreach (['leve', 'grave', 'gravisima'] as $g) {
            foreach ($conductas[$g] ?? [] as $c) {
                if (!empty($c['conducta']) && in_array($c['conducta'], $motivosSeleccionados, true)) {
                    $motivosRit[] = ['conducta' => $c['conducta'], 'gravedad' => $g];
                }
            }
        }

        $fechas = [];
        if (!empty($this->data['fecha_ocurrencia'])) {
            $fechas[] = \Carbon\Carbon::parse($this->data['fecha_ocurrencia'])->format('Y-m-d');
        }
        foreach ($this->data['fechas_ocurrencia_adicionales'] ?? [] as $item) {
            if (!empty($item['fecha'])) {
                $fechas[] = \Carbon\Carbon::parse($item['fecha'])->format('Y-m-d');
            }
        }

        try {
            $resultado = app(\App\Services\IADescargoService::class)->clasificarIncidente([
                'empresa_id'        => (int) $empresaId,
                'trabajador_id'     => (int) $trabajadorId,
                'cargo'             => $trabajador?->cargo ?? 'No especificado',
                'hechos'            => $this->data['hechos'] ?? '',
                'fechas_ocurrencia' => $fechas,
                'motivos_rit'       => $motivosRit,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al auto-clasificar gravedad del incidente con IA', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $this->data['clasificacion_incidente_ia'] = json_encode($resultado);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Al cargar el formulario para editar, poblar campos temporales según modalidad

        if (in_array($data['modalidad_descargos'], ['presencial', 'telefonico'])) {
            // Para presencial/telefónico: combinar fecha + hora en hora_temp_descargos
            if (!empty($data['fecha_descargos_programada']) && !empty($data['hora_descargos_programada'])) {
                $data['hora_temp_descargos'] = $data['fecha_descargos_programada'] . ' ' . $data['hora_descargos_programada'];
            }
        }
        // Para virtual: los campos ya están separados, no necesita transformación

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Para presencial/telefónico: extraer fecha y hora de hora_temp_descargos
        if (in_array($data['modalidad_descargos'] ?? '', ['presencial', 'telefonico'])) {
            if (!empty($data['hora_temp_descargos'])) {
                $datetime = \Carbon\Carbon::parse($data['hora_temp_descargos']);
                $data['fecha_descargos_programada'] = $datetime->format('Y-m-d');
                $data['hora_descargos_programada'] = $datetime->format('H:i:s');
            }
        }

        // Debug: Log para verificar qué se está guardando
        Log::info('EDIT - Datos antes de guardar proceso disciplinario (edición):', [
            'modalidad' => $data['modalidad_descargos'] ?? 'no definido',
            'fecha_descargos_programada' => $data['fecha_descargos_programada'] ?? 'no definido',
            'hora_descargos_programada' => $data['hora_descargos_programada'] ?? 'no definido',
        ]);

        // Remover campos temporales del array de datos
        unset($data['fecha_temp_descargos']);
        unset($data['hora_temp_descargos']);

        return $data;
    }
}
