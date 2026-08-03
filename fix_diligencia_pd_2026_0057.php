<?php
// TEMPORAL - script de reparación puntual para PD-2026-0057 (diligencia
// completada por el trabajador pero nunca cerrada por el bug de preguntas
// repetidas del motor de corroboración). BORRAR este archivo apenas se
// confirme que funcionó.

$diligencia = \App\Models\DiligenciaDescargo::whereHas('proceso', fn($q) => $q->where('codigo', 'PD-2026-0057'))->firstOrFail();
$proceso = $diligencia->proceso;

echo "Antes: trabajador_asistio=" . var_export($diligencia->trabajador_asistio, true) . " acta_generada=" . var_export($diligencia->acta_generada, true) . " estado_proceso=" . $proceso->estado . PHP_EOL;

if ($diligencia->trabajador_asistio) {
    echo "ABORTADO: ya estaba marcado como asistido, no se toca nada." . PHP_EOL;
    return;
}

$diligencia->update([
    'trabajador_asistio' => true,
    'fecha_diligencia' => $diligencia->preguntas_completadas_en,
]);
app(\App\Services\EstadoProcesoService::class)->alCompletarDescargos($proceso);

$actaService = new \App\Services\ActaDescargosService();
$resultado = $actaService->generarActaDescargos($diligencia);
echo "Resultado acta: " . json_encode(['success' => $resultado['success'] ?? null, 'error' => $resultado['error'] ?? null]) . PHP_EOL;

$actaPath = null;
if ($resultado['success'] ?? false) {
    $actaPath = $resultado['path'];
    $diligencia->update(['acta_generada' => true, 'ruta_acta' => $actaPath]);
}

$docService = app(\App\Services\DocumentGeneratorService::class);
try {
    $docService->enviarNotificacionEstadoDescargos($proceso, 'descargos_realizados', $actaPath);
    echo "Notificacion al trabajador: OK" . PHP_EOL;
} catch (\Exception $e) {
    echo "Notificacion al trabajador FALLO: " . $e->getMessage() . PHP_EOL;
}
try {
    $docService->enviarNotificacionDescargosAlCliente($proceso, 'descargos_realizados');
    echo "Notificacion al cliente: OK" . PHP_EOL;
} catch (\Exception $e) {
    echo "Notificacion al cliente FALLO: " . $e->getMessage() . PHP_EOL;
}

$diligencia->refresh();
$proceso->refresh();
echo "Despues: trabajador_asistio=" . var_export($diligencia->trabajador_asistio, true) . " acta_generada=" . var_export($diligencia->acta_generada, true) . " ruta_acta=" . ($diligencia->ruta_acta ?? 'NULL') . " estado_proceso=" . $proceso->estado . PHP_EOL;
