<?php

namespace App\Services;

use App\Models\SolicitudContrato;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * IA para el flujo de SolicitudContrato (contratos por obra o labor,
 * gestión interna de CES Legal - "Análisis Jurídico" en
 * SolicitudContratoResource): redacta un borrador del objeto jurídico
 * anclado en el CST (mismo principio que LUPE - la IA no razona desde su
 * propio criterio, cita solo artículos realmente provistos) y genera el
 * PDF final del contrato. No usa DocumentoService (framework de plantillas
 * sin ninguna referencia real en el proyecto) - usa el mismo patrón
 * HTML+Dompdf de DocumentGeneratorService, que sí es el que se usa en todo
 * el sistema.
 */
class SolicitudContratoIAService
{
    public function __construct(
        private readonly RITGeneratorService $ritGeneratorService,
    ) {}

    /**
     * Redacta un borrador del objeto jurídico del contrato. NO lo persiste -
     * el llamador decide (el abogado revisa/edita el borrador en el
     * RichEditor antes de guardar el formulario).
     */
    public function redactarObjetoJuridico(SolicitudContrato $solicitud): string
    {
        $articulosCst = $this->ritGeneratorService->buscarArticulosPorTema(
            'contrato trabajo obra labor determinada duración',
            limite: 6,
        );

        $prompt = $this->construirPromptObjeto($solicitud, $articulosCst);

        return $this->llamarGemini($prompt, $solicitud->empresa_id);
    }

    private function construirPromptObjeto(SolicitudContrato $solicitud, string $articulosCst): string
    {
        $nombreTrabajador = trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}");
        $fechaInicio       = $solicitud->fecha_inicio_propuesta?->format('Y-m-d') ?? 'No especificada';
        $salario           = $solicitud->salario_propuesto ?? 'No especificado';

        return <<<PROMPT
        Eres un abogado laboralista colombiano redactando el OBJETO JURÍDICO
        de un contrato de trabajo por obra o labor determinada, con base
        ÚNICAMENTE en los datos provistos y los artículos del Código
        Sustantivo del Trabajo (CST) listados abajo.

        PROHIBICIÓN ABSOLUTA: Solo puedes citar artículos del CST que
        aparezcan en la sección "ARTÍCULOS DEL CST DISPONIBLES" de abajo. Si
        ninguno aplica exactamente, redacta sin citar número de artículo en
        vez de inventar uno.

        PROHIBICIÓN ABSOLUTA: No inventes cargo, funciones, salario, fechas
        ni ningún dato que no esté explícitamente en "DATOS DE LA SOLICITUD"
        abajo.

        DATOS DE LA SOLICITUD:
        - Trabajador: {$nombreTrabajador}, cargo: {$solicitud->cargo_contrato}
        - Responsabilidades: {$solicitud->responsabilidades}
        - Objeto comercial (contexto del negocio que RRHH describió): {$solicitud->objeto_comercial}
        - Manual de funciones: {$solicitud->manual_funciones}
        - Salario propuesto: {$salario}
        - Fecha de inicio propuesta: {$fechaInicio}

        ARTÍCULOS DEL CST DISPONIBLES:
        {$articulosCst}

        Redacta el objeto jurídico del contrato de trabajo por obra o labor
        determinada en 1-3 párrafos de prosa jurídica formal, en tercera
        persona, describiendo con precisión la obra o labor que se
        contrata (a partir del objeto comercial y el manual de funciones
        provistos), sin markdown ni asteriscos. No repitas los datos en
        formato de lista - redáctalos como un objeto contractual coherente.
        PROMPT;
    }

    public function generarContratoPDF(SolicitudContrato $solicitud): string
    {
        $html = $this->generarHTML($solicitud);

        $directorioRelativo = "solicitudes-contrato/{$solicitud->empresa_id}";
        Storage::disk('local')->makeDirectory($directorioRelativo);

        $rutaRelativa = "{$directorioRelativo}/contrato_{$solicitud->id}.pdf";
        $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        file_put_contents($rutaAbsoluta, $dompdf->output());

        $solicitud->update([
            'ruta_contrato'             => $rutaRelativa,
            'fecha_generacion_contrato' => now(),
            'estado'                    => 'contrato_generado',
        ]);

        return $rutaRelativa;
    }

    private function generarHTML(SolicitudContrato $solicitud): string
    {
        $empresa          = $solicitud->empresa;
        $nombreEmpresa    = e($empresa?->nombre_completo ?? '');
        $nit              = e($empresa?->nit ?? '');
        $nombreTrabajador = e(trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}"));
        $tipoDocumento    = e($solicitud->trabajador_documento_tipo);
        $numeroDocumento  = e($solicitud->trabajador_documento_numero);
        $cargo            = e($solicitud->cargo_contrato);
        $salario          = e((string) ($solicitud->salario_propuesto ?? ''));
        $fechaInicio      = e($solicitud->fecha_inicio_propuesta?->format('d/m/Y') ?? 'No especificada');
        $objetoJuridico   = nl2br(e(strip_tags($solicitud->objeto_juridico_redactado ?? '')));

        return <<<HTML
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.6; }
            h1 { font-size: 16px; text-align: center; }
            .datos { margin: 20px 0; }
            .datos p { margin: 4px 0; }
        </style></head>
        <body>
            <h1>CONTRATO INDIVIDUAL DE TRABAJO POR OBRA O LABOR DETERMINADA</h1>
            <div class="datos">
                <p><strong>Empresa:</strong> {$nombreEmpresa}</p>
                <p><strong>NIT:</strong> {$nit}</p>
                <p><strong>Trabajador:</strong> {$nombreTrabajador}</p>
                <p><strong>Documento:</strong> {$tipoDocumento} {$numeroDocumento}</p>
                <p><strong>Cargo:</strong> {$cargo}</p>
                <p><strong>Salario:</strong> \${$salario}</p>
                <p><strong>Fecha de inicio:</strong> {$fechaInicio}</p>
            </div>
            <div class="objeto">{$objetoJuridico}</div>
        </body>
        </html>
        HTML;
    }

    /**
     * Copiado del mismo patrón usado en RITGeneratorService::llamarGemini()
     * (y otros servicios de este proyecto) - sin trait compartido, es la
     * convención ya establecida en el repo.
     */
    private function llamarGemini(string $prompt, ?int $empresaId = 0): string
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? '';

        $modelosCascada = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];

        $prompt = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $prompt
        ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 8192,
                'topP'            => 0.95,
            ],
        ];

        $lastError = null;

        foreach ($modelosCascada as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            for ($intento = 1; $intento <= 2; $intento++) {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post($url, $payload);

                if ($response->successful()) {
                    $data  = $response->json();
                    $parts = $data['candidates'][0]['content']['parts'] ?? [];
                    $texto = $parts[0]['text'] ?? '';

                    if (!empty($texto)) {
                        return trim($texto);
                    }
                }

                $status = $response->status();
                Log::warning('SolicitudContratoIAService: fallo en intento', [
                    'empresa_id' => $empresaId, 'model' => $model,
                    'intento' => $intento, 'status' => $status,
                ]);
                $lastError = $response->body();

                if (in_array($status, [429, 503], true) && $intento < 2) {
                    sleep(10);
                }
            }
        }

        throw new \RuntimeException('No se pudo redactar el objeto jurídico con IA: ' . $lastError);
    }
}
