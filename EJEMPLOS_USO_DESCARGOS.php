<?php

/**
 * EJEMPLOS DE USO DEL SISTEMA DE DESCARGOS DINÁMICOS CON IA
 *
 * Este archivo contiene ejemplos prácticos de cómo usar el sistema.
 * NO ejecutes este archivo directamente, copia el código que necesites.
 */

use App\Models\DiligenciaDescargo;
use App\Models\ProcesoDisciplinario;
use App\Models\PreguntaDescargo;
use App\Services\IADescargoService;
use Illuminate\Support\Facades\Mail;

// ============================================================================
// EJEMPLO 1: Crear una diligencia y generar preguntas iniciales con IA
// ============================================================================

function ejemplo1_crear_diligencia_con_preguntas_ia()
{
    // 1. Obtener el proceso disciplinario
    $proceso = ProcesoDisciplinario::where('codigo', 'PD-2025-001')->first();

    // 2. Crear la diligencia de descargo
    $diligencia = DiligenciaDescargo::create([
        'proceso_id' => $proceso->id,
        'fecha_diligencia' => now()->addDays(7), // En 7 días
        'lugar_diligencia' => 'Sala de Audiencias - Oficina Principal',
    ]);

    // 3. Generar token de acceso temporal
    $token = $diligencia->generarTokenAcceso();

    // 4. Configurar acceso para el día de la diligencia
    $diligencia->fecha_acceso_permitida = $diligencia->fecha_diligencia->toDateString();
    $diligencia->acceso_habilitado = true;
    $diligencia->save();

    // 5. Generar preguntas iniciales con IA
    $iaService = new IADescargoService();
    $preguntas = $iaService->generarPreguntasIniciales($diligencia, 5);

    // 6. Generar URL de acceso
    $url = route('descargos.acceso', ['token' => $token]);

    echo "Diligencia creada con ID: {$diligencia->id}\n";
    echo "Token de acceso: {$token}\n";
    echo "URL de acceso: {$url}\n";
    echo "Preguntas generadas: " . count($preguntas) . "\n";

    return $diligencia;
}

// ============================================================================
// EJEMPLO 2: Generar preguntas manualmente (sin IA)
// ============================================================================

function ejemplo2_generar_preguntas_manualmente()
{
    $diligencia = DiligenciaDescargo::find(1);

    $preguntasTexto = [
        '¿Puede explicar detalladamente los hechos ocurridos el día mencionado?',
        '¿Qué circunstancias rodearon el incidente?',
        '¿Hubo testigos presentes? En caso afirmativo, ¿quiénes fueron?',
        '¿Desea aportar alguna prueba en su defensa?',
        '¿Tiene algo más que agregar a su descargo?',
    ];

    foreach ($preguntasTexto as $index => $preguntaTexto) {
        PreguntaDescargo::create([
            'diligencia_descargo_id' => $diligencia->id,
            'pregunta' => $preguntaTexto,
            'orden' => $index + 1,
            'es_generada_por_ia' => false,
            'estado' => 'activa',
        ]);
    }

    echo "5 preguntas manuales creadas para la diligencia #{$diligencia->id}\n";
}

// ============================================================================
// EJEMPLO 3: Enviar email con link de acceso al trabajador
// ============================================================================

function ejemplo3_enviar_email_con_link()
{
    $diligencia = DiligenciaDescargo::find(1);
    $proceso = $diligencia->proceso;
    $trabajador = $proceso->trabajador;

    // Generar o regenerar token
    if (!$diligencia->token_acceso) {
        $token = $diligencia->generarTokenAcceso();
    } else {
        $token = $diligencia->token_acceso;
    }

    $url = route('descargos.acceso', ['token' => $token]);

    // Datos del email
    $subject = "Citación a Descargos - Proceso {$proceso->codigo}";
    $fechaDescargos = $diligencia->fecha_diligencia->format('d/m/Y H:i');

    $mensaje = <<<HTML
    <h2>Citación a Audiencia de Descargos</h2>
    <p>Estimado(a) <strong>{$trabajador->nombre_completo}</strong>,</p>

    <p>Por medio del presente se le informa que debe presentar sus descargos
    correspondientes al proceso disciplinario <strong>{$proceso->codigo}</strong>.</p>

    <p><strong>Fecha de la audiencia:</strong> {$fechaDescargos}</p>
    <p><strong>Lugar:</strong> {$diligencia->lugar_diligencia}</p>

    <p>Podrá acceder al formulario de descargos en línea el día de la audiencia
    a través del siguiente enlace:</p>

    <p><a href="{$url}" style="display: inline-block; padding: 10px 20px;
    background-color: #3B82F6; color: white; text-decoration: none;
    border-radius: 5px;">Acceder a Descargos</a></p>

    <p><strong>Importante:</strong> Este enlace solo estará disponible el día
    {$diligencia->fecha_acceso_permitida->format('d/m/Y')} y expirará en
    {$diligencia->token_expira_en->format('d/m/Y')}.</p>

    <p>Atentamente,<br>Departamento de Recursos Humanos</p>
HTML;

    // Enviar email (asume que tienes configurado el mail)
    Mail::raw($mensaje, function ($mail) use ($trabajador, $subject) {
        $mail->to($trabajador->email)
            ->subject($subject)
            ->html(true);
    });

    echo "Email enviado a: {$trabajador->email}\n";
    echo "URL de acceso: {$url}\n";
}

// ============================================================================
// EJEMPLO 4: Consultar el estado de los descargos de una diligencia
// ============================================================================

function ejemplo4_consultar_estado_descargos()
{
    $diligencia = DiligenciaDescargo::with([
        'preguntas.respuesta',
        'proceso.trabajador'
    ])->find(1);

    $totalPreguntas = $diligencia->preguntas->count();
    $preguntasRespondidas = $diligencia->preguntas()
        ->whereHas('respuesta')
        ->count();
    $preguntasIA = $diligencia->preguntas()
        ->where('es_generada_por_ia', true)
        ->count();

    echo "=== Estado de Descargos ===\n";
    echo "Trabajador: {$diligencia->proceso->trabajador->nombre_completo}\n";
    echo "Proceso: {$diligencia->proceso->codigo}\n";
    echo "Total de preguntas: {$totalPreguntas}\n";
    echo "Preguntas respondidas: {$preguntasRespondidas}\n";
    echo "Preguntas generadas por IA: {$preguntasIA}\n";
    echo "Progreso: " . ($totalPreguntas > 0 ? round(($preguntasRespondidas / $totalPreguntas) * 100) : 0) . "%\n";

    if ($diligencia->trabajador_accedio_en) {
        echo "Trabajador accedió: {$diligencia->trabajador_accedio_en->format('d/m/Y H:i')}\n";
        echo "IP de acceso: {$diligencia->ip_acceso}\n";
    } else {
        echo "Trabajador no ha accedido aún\n";
    }

    echo "\n=== Detalle de Preguntas ===\n";
    foreach ($diligencia->preguntas as $index => $pregunta) {
        $tipo = $pregunta->es_generada_por_ia ? '[IA]' : '[Manual]';
        $estado = $pregunta->respuesta ? '✓' : '✗';

        echo "{$estado} {$tipo} P{$index + 1}: " . substr($pregunta->pregunta, 0, 60) . "...\n";

        if ($pregunta->respuesta) {
            echo "   R: " . substr($pregunta->respuesta->respuesta, 0, 80) . "...\n";
        }
        echo "\n";
    }
}

// ============================================================================
// EJEMPLO 5: Ver trazabilidad de llamadas a la IA
// ============================================================================

function ejemplo5_ver_trazabilidad_ia()
{
    $diligencia = DiligenciaDescargo::find(1);

    $trazabilidad = $diligencia->trazabilidadIA()
        ->orderBy('created_at', 'desc')
        ->get();

    echo "=== Trazabilidad de IA ===\n";
    echo "Total de llamadas: {$trazabilidad->count()}\n\n";

    foreach ($trazabilidad as $index => $registro) {
        echo "--- Llamada #{$index + 1} ---\n";
        echo "Fecha: {$registro->created_at->format('d/m/Y H:i:s')}\n";
        echo "Tipo: {$registro->tipo}\n";
        echo "Proveedor: {$registro->metadata['provider']}\n";
        echo "Modelo: {$registro->metadata['model']}\n";
        echo "\nPrompt enviado:\n";
        echo substr($registro->prompt_enviado, 0, 200) . "...\n\n";
        echo "Respuesta recibida:\n";
        echo substr($registro->respuesta_recibida, 0, 200) . "...\n\n";
        echo str_repeat('-', 50) . "\n\n";
    }
}

// ============================================================================
// EJEMPLO 6: Ver árbol de preguntas (pregunta padre e hijas)
// ============================================================================

function ejemplo6_ver_arbol_preguntas()
{
    $diligencia = DiligenciaDescargo::find(1);

    // Obtener preguntas raíz (sin padre)
    $preguntasRaiz = $diligencia->preguntas()
        ->whereNull('pregunta_padre_id')
        ->with(['preguntasHijas.respuesta', 'respuesta'])
        ->get();

    echo "=== Árbol de Preguntas ===\n\n";

    foreach ($preguntasRaiz as $raiz) {
        echo "• {$raiz->pregunta}\n";

        if ($raiz->respuesta) {
            echo "  ↳ Respuesta: " . substr($raiz->respuesta->respuesta, 0, 80) . "...\n";
        }

        // Mostrar preguntas hijas (generadas a partir de esta)
        foreach ($raiz->preguntasHijas as $hija) {
            echo "\n  ├─ [IA] {$hija->pregunta}\n";

            if ($hija->respuesta) {
                echo "     ↳ Respuesta: " . substr($hija->respuesta->respuesta, 0, 70) . "...\n";
            }
        }

        echo "\n" . str_repeat('-', 70) . "\n\n";
    }
}

// ============================================================================
// EJEMPLO 7: Deshabilitar acceso después de completar descargos
// ============================================================================

function ejemplo7_deshabilitar_acceso()
{
    $diligencia = DiligenciaDescargo::find(1);

    // Verificar que todos respondieron
    $preguntasSinResponder = $diligencia->preguntas()
        ->activas()
        ->whereDoesntHave('respuesta')
        ->count();

    if ($preguntasSinResponder === 0) {
        $diligencia->acceso_habilitado = false;
        $diligencia->save();

        echo "Acceso deshabilitado. Todos los descargos fueron completados.\n";
    } else {
        echo "No se puede deshabilitar. Quedan {$preguntasSinResponder} preguntas sin responder.\n";
    }
}

// ============================================================================
// EJEMPLO 8: Regenerar token si expiró
// ============================================================================

function ejemplo8_regenerar_token()
{
    $diligencia = DiligenciaDescargo::find(1);

    if (!$diligencia->tokenEsValido()) {
        $nuevoToken = $diligencia->generarTokenAcceso();
        $nuevaUrl = route('descargos.acceso', ['token' => $nuevoToken]);

        echo "Token anterior expirado. Nuevo token generado.\n";
        echo "Nuevo token: {$nuevoToken}\n";
        echo "Nueva URL: {$nuevaUrl}\n";
        echo "Expira en: {$diligencia->token_expira_en->format('d/m/Y H:i')}\n";
    } else {
        echo "Token actual es válido. No es necesario regenerar.\n";
    }
}

// ============================================================================
// EJEMPLO 9: Generar preguntas dinámicas manualmente (sin esperar respuesta)
// ============================================================================

function ejemplo9_generar_preguntas_dinamicas_manual()
{
    $diligencia = DiligenciaDescargo::find(1);

    // Supongamos que ya hay una pregunta y respuesta
    $pregunta = $diligencia->preguntas()->first();
    $respuesta = $pregunta->respuesta;

    if (!$respuesta) {
        echo "Esta pregunta no tiene respuesta aún.\n";
        return;
    }

    // Generar nuevas preguntas basadas en esta respuesta
    $iaService = new IADescargoService();
    $nuevasPreguntas = $iaService->generarPreguntasDinamicas($pregunta, $respuesta);

    echo "Se generaron " . count($nuevasPreguntas) . " nuevas preguntas:\n\n";

    foreach ($nuevasPreguntas as $nueva) {
        echo "• {$nueva->pregunta}\n";
    }
}

// ============================================================================
// EJEMPLO 10: Obtener estadísticas generales del sistema
// ============================================================================

function ejemplo10_estadisticas_generales()
{
    $totalDiligencias = DiligenciaDescargo::whereNotNull('token_acceso')->count();
    $diligenciasAccedidas = DiligenciaDescargo::whereNotNull('trabajador_accedio_en')->count();
    $totalPreguntas = PreguntaDescargo::count();
    $preguntasIA = PreguntaDescargo::where('es_generada_por_ia', true)->count();
    $totalRespuestas = \App\Models\RespuestaDescargo::count();

    echo "=== Estadísticas Generales ===\n";
    echo "Total diligencias con acceso web: {$totalDiligencias}\n";
    echo "Trabajadores que accedieron: {$diligenciasAccedidas}\n";
    echo "Total de preguntas: {$totalPreguntas}\n";
    echo "Preguntas generadas por IA: {$preguntasIA} (" . round(($preguntasIA / $totalPreguntas) * 100) . "%)\n";
    echo "Total de respuestas: {$totalRespuestas}\n";
    echo "Promedio respuestas por pregunta: " . round($totalRespuestas / $totalPreguntas, 2) . "\n";
}

// ============================================================================
// CÓMO USAR ESTOS EJEMPLOS
// ============================================================================

/*
 * 1. Copia el código del ejemplo que necesites
 * 2. Pégalo en tu controlador, comando artisan, o seeder
 * 3. Adapta las IDs y datos según tu caso
 * 4. Ejecuta el código
 *
 * Por ejemplo, en un comando artisan:
 *
 * php artisan make:command GenerarPreguntasIA
 *
 * Luego en el método handle():
 *
 * public function handle()
 * {
 *     $diligencia = DiligenciaDescargo::find($this->argument('id'));
 *     $iaService = new IADescargoService();
 *     $preguntas = $iaService->generarPreguntasIniciales($diligencia, 5);
 *
 *     $this->info('Preguntas generadas: ' . count($preguntas));
 * }
 */
