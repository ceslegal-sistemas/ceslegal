<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\InformeJuridico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class InformeJuridicoExportService
{
    // Datos de CES LEGAL (proveedor de servicios jurídicos)
    protected const CES_LEGAL = [
        'razon_social' => 'CES LEGAL S.A.S.',
        'nit' => '901.258.505-4',
        'direccion' => 'Carrera 2 #10-53',
        'telefono' => '+57 3196777103',
    ];

    protected const MESES = [
        'enero' => 'Enero',
        'febrero' => 'Febrero',
        'marzo' => 'Marzo',
        'abril' => 'Abril',
        'mayo' => 'Mayo',
        'junio' => 'Junio',
        'julio' => 'Julio',
        'agosto' => 'Agosto',
        'septiembre' => 'Septiembre',
        'octubre' => 'Octubre',
        'noviembre' => 'Noviembre',
        'diciembre' => 'Diciembre',
    ];

    protected const COLORES_GRAFICA = [
        '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed',
        '#0891b2', '#65a30d', '#ea580c', '#be185d', '#4f46e5'
    ];

    public function getInformes(int $empresaId, int $anio, ?string $mes = null): Collection
    {
        $query = InformeJuridico::with(['areaPractica', 'tipoGestion', 'subtipo', 'creador'])
            ->where('empresa_id', $empresaId)
            ->where('anio', $anio);

        if ($mes && $mes !== 'todos') {
            $query->where('mes', $mes);
        }

        return $query->orderBy('mes')->orderBy('created_at')->get();
    }

    public function getResumenPorArea(Collection $informes): array
    {
        return $informes->groupBy('area_practica_texto')
            ->map(function ($items, $area) {
                return [
                    'area' => $area,
                    'cantidad' => $items->count(),
                    'tiempo_total' => $items->sum('tiempo_minutos'),
                    'tiempo_formateado' => $this->formatearTiempo($items->sum('tiempo_minutos')),
                ];
            })
            ->sortByDesc('cantidad')
            ->values()
            ->toArray();
    }

    public function getResumenPorTipo(Collection $informes): array
    {
        return $informes->groupBy('tipo_gestion_texto')
            ->map(function ($items, $tipo) {
                return [
                    'tipo' => $tipo,
                    'cantidad' => $items->count(),
                    'tiempo_total' => $items->sum('tiempo_minutos'),
                    'tiempo_formateado' => $this->formatearTiempo($items->sum('tiempo_minutos')),
                ];
            })
            ->sortByDesc('cantidad')
            ->values()
            ->toArray();
    }

    public function getResumenPorEstado(Collection $informes): array
    {
        return $informes->groupBy('estado')
            ->map(function ($items, $estado) {
                return [
                    'estado' => $items->first()->estado_texto,
                    'estado_key' => $estado,
                    'cantidad' => $items->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    public function getResumenPorMes(Collection $informes): array
    {
        $mesesOrden = array_keys(self::MESES);

        return $informes->groupBy('mes')
            ->map(function ($items, $mes) {
                return [
                    'mes' => self::MESES[$mes] ?? $mes,
                    'mes_key' => $mes,
                    'cantidad' => $items->count(),
                    'tiempo_total' => $items->sum('tiempo_minutos'),
                    'tiempo_formateado' => $this->formatearTiempo($items->sum('tiempo_minutos')),
                ];
            })
            ->sortBy(function ($item, $key) use ($mesesOrden) {
                return array_search($key, $mesesOrden);
            })
            ->values()
            ->toArray();
    }

    protected function formatearTiempo(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $mins = $minutos % 60;

        if ($horas > 0) {
            return "{$horas}h {$mins}m";
        }

        return "{$mins} min";
    }

    /**
     * Genera el PDF del informe con formato profesional y análisis de BI
     */
    public function generarPDF(int $empresaId, int $anio, ?string $mes = null): string
    {
        $empresa = Empresa::findOrFail($empresaId);
        $informes = $this->getInformes($empresaId, $anio, $mes);
        $abogado = auth()->user();

        $resumenPorArea = $this->getResumenPorArea($informes);
        $resumenPorTipo = $this->getResumenPorTipo($informes);
        $resumenPorEstado = $this->getResumenPorEstado($informes);
        $resumenPorMes = $mes === 'todos' ? $this->getResumenPorMes($informes) : null;

        // Calcular métricas de BI
        $metricas = $this->calcularMetricas($informes, $resumenPorArea, $resumenPorTipo, $resumenPorEstado);

        // Generar gráficas SVG
        $graficaAreas = $this->generarGraficaBarrasHorizontales($resumenPorArea, 'area', 'cantidad', 'Gestiones por Área de Práctica');
        $graficaTipos = $this->generarGraficaPastel($resumenPorTipo, 'tipo', 'cantidad', 'Distribución por Tipo de Gestión');
        $graficaEstados = $this->generarGraficaBarrasEstados($resumenPorEstado);
        $graficaMeses = $resumenPorMes ? $this->generarGraficaLinea($resumenPorMes, 'Evolución Mensual de Gestiones') : '';

        // Generar análisis ejecutivo con IA
        $analisisIA = $this->generarAnalisisConIA(
            $empresa,
            $anio,
            $mes,
            $informes,
            $resumenPorArea,
            $resumenPorTipo,
            $resumenPorEstado,
            $resumenPorMes,
            $metricas
        );

        // Generar HTML del documento
        $html = $this->generarHTMLInforme(
            $empresa,
            $abogado,
            $anio,
            $mes,
            $informes,
            $resumenPorArea,
            $resumenPorTipo,
            $resumenPorEstado,
            $resumenPorMes,
            $metricas,
            $analisisIA,
            $graficaAreas,
            $graficaTipos,
            $graficaEstados,
            $graficaMeses
        );

        // Convertir a PDF
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('letter', 'portrait');

        $filename = 'informe-juridico-' . $empresa->id . '-' . $anio . '-' . ($mes ?? 'anual') . '.pdf';
        $path = 'exports/' . $filename;

        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Calcula métricas de Business Intelligence
     */
    protected function calcularMetricas(
        Collection $informes,
        array $resumenPorArea,
        array $resumenPorTipo,
        array $resumenPorEstado
    ): array {
        $totalGestiones = $informes->count();
        $tiempoTotalMinutos = $informes->sum('tiempo_minutos');

        $tiempoPromedio = $totalGestiones > 0 ? round($tiempoTotalMinutos / $totalGestiones) : 0;

        $entregadas = collect($resumenPorEstado)->firstWhere('estado_key', 'entregado');
        $tasaCumplimiento = $totalGestiones > 0 && $entregadas
            ? round(($entregadas['cantidad'] / $totalGestiones) * 100)
            : 0;

        $pendientes = collect($resumenPorEstado)->firstWhere('estado_key', 'pendiente');
        $enProceso = collect($resumenPorEstado)->firstWhere('estado_key', 'en_proceso');

        $areaMasDemandante = collect($resumenPorArea)->first();
        $tipoMasFrecuente = collect($resumenPorTipo)->first();

        return [
            'total_gestiones' => $totalGestiones,
            'tiempo_total' => $this->formatearTiempo($tiempoTotalMinutos),
            'tiempo_total_minutos' => $tiempoTotalMinutos,
            'tiempo_promedio' => $this->formatearTiempo($tiempoPromedio),
            'tiempo_promedio_minutos' => $tiempoPromedio,
            'tasa_cumplimiento' => $tasaCumplimiento,
            'gestiones_pendientes' => $pendientes['cantidad'] ?? 0,
            'gestiones_en_proceso' => $enProceso['cantidad'] ?? 0,
            'gestiones_entregadas' => $entregadas['cantidad'] ?? 0,
            'area_mas_demandante' => $areaMasDemandante['area'] ?? 'N/A',
            'area_mas_demandante_cantidad' => $areaMasDemandante['cantidad'] ?? 0,
            'tipo_mas_frecuente' => $tipoMasFrecuente['tipo'] ?? 'N/A',
            'tipo_mas_frecuente_cantidad' => $tipoMasFrecuente['cantidad'] ?? 0,
            'horas_invertidas' => round($tiempoTotalMinutos / 60, 1),
            'total_areas' => count($resumenPorArea),
            'total_tipos' => count($resumenPorTipo),
        ];
    }

    /**
     * Genera gráfica de barras horizontales como tabla HTML (compatible con DomPDF)
     */
    protected function generarGraficaBarrasHorizontales(array $datos, string $labelKey, string $valueKey, string $titulo): string
    {
        if (empty($datos)) return '';

        $datos = array_slice($datos, 0, 6);
        $maxValue = max(array_column($datos, $valueKey));
        $total = array_sum(array_column($datos, $valueKey));
        if ($maxValue === 0) return '';

        $html = '<div style="text-align: center; margin-bottom: 10px;"><strong>' . htmlspecialchars($titulo) . '</strong></div>';
        $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 9pt;">';

        foreach ($datos as $index => $item) {
            $label = mb_substr($item[$labelKey], 0, 25);
            $value = $item[$valueKey];
            $porcentaje = $total > 0 ? round(($value / $total) * 100) : 0;
            $barWidth = $maxValue > 0 ? round(($value / $maxValue) * 100) : 0;
            $color = self::COLORES_GRAFICA[$index % count(self::COLORES_GRAFICA)];

            $html .= '<tr>';
            $html .= '<td style="width: 35%; padding: 4px; text-align: right; color: #374151;">' . htmlspecialchars($label) . '</td>';
            $html .= '<td style="width: 50%; padding: 4px;">';
            $html .= '<div style="background-color: #e5e7eb; border-radius: 4px; height: 20px; width: 100%;">';
            $html .= '<div style="background-color: ' . $color . '; border-radius: 4px; height: 20px; width: ' . $barWidth . '%;"></div>';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '<td style="width: 15%; padding: 4px; text-align: center; font-weight: bold; color: #1f2937;">' . $value . ' (' . $porcentaje . '%)</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Genera gráfica de distribución como leyenda HTML (compatible con DomPDF)
     */
    protected function generarGraficaPastel(array $datos, string $labelKey, string $valueKey, string $titulo): string
    {
        if (empty($datos)) return '';

        $datos = array_slice($datos, 0, 6);
        $total = array_sum(array_column($datos, $valueKey));
        if ($total === 0) return '';

        $html = '<div style="text-align: center; margin-bottom: 10px;"><strong>' . htmlspecialchars($titulo) . '</strong></div>';
        $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 9pt;">';

        foreach ($datos as $index => $item) {
            $label = mb_substr($item[$labelKey], 0, 25);
            $value = $item[$valueKey];
            $porcentaje = $total > 0 ? round(($value / $total) * 100) : 0;
            $color = self::COLORES_GRAFICA[$index % count(self::COLORES_GRAFICA)];

            $html .= '<tr>';
            $html .= '<td style="width: 15px; padding: 5px;">';
            $html .= '<div style="width: 12px; height: 12px; background-color: ' . $color . '; border-radius: 3px;"></div>';
            $html .= '</td>';
            $html .= '<td style="padding: 5px; color: #374151;">' . htmlspecialchars($label) . '</td>';
            $html .= '<td style="padding: 5px; text-align: right; font-weight: bold;">' . $value . '</td>';
            $html .= '<td style="padding: 5px; text-align: right; color: #6b7280;">(' . $porcentaje . '%)</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Genera gráfica de barras para estados (compatible con DomPDF)
     */
    protected function generarGraficaBarrasEstados(array $datos): string
    {
        if (empty($datos)) return '';

        $total = array_sum(array_column($datos, 'cantidad'));
        if ($total === 0) return '';

        $coloresEstado = [
            'entregado' => '#059669',
            'en_proceso' => '#2563eb',
            'pendiente' => '#d97706',
        ];

        $html = '<div style="text-align: center; margin-bottom: 10px;"><strong>Estado de las Gestiones</strong></div>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr>';

        foreach ($datos as $item) {
            $value = $item['cantidad'];
            $color = $coloresEstado[$item['estado_key']] ?? '#6b7280';
            $percentage = round(($value / $total) * 100);

            $html .= '<td style="width: ' . (100 / count($datos)) . '%; text-align: center; padding: 10px; vertical-align: bottom;">';
            $html .= '<div style="font-size: 24pt; font-weight: bold; color: ' . $color . ';">' . $value . '</div>';
            $html .= '<div style="font-size: 9pt; color: #374151; margin-top: 5px;">' . htmlspecialchars($item['estado']) . '</div>';
            $html .= '<div style="font-size: 8pt; color: #6b7280;">(' . $percentage . '%)</div>';
            $html .= '</td>';
        }

        $html .= '</tr>';
        $html .= '</table>';
        return $html;
    }

    /**
     * Genera gráfica de evolución mensual (compatible con DomPDF)
     */
    protected function generarGraficaLinea(array $datos, string $titulo): string
    {
        if (empty($datos)) return '';

        $maxValue = max(array_column($datos, 'cantidad'));
        if ($maxValue === 0) return '';

        $html = '<div style="text-align: center; margin-bottom: 10px;"><strong>' . htmlspecialchars($titulo) . '</strong></div>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';

        // Fila de valores (barras verticales simuladas)
        $html .= '<tr>';
        foreach ($datos as $item) {
            $value = $item['cantidad'];
            $heightPercent = $maxValue > 0 ? round(($value / $maxValue) * 100) : 0;

            $html .= '<td style="width: ' . (100 / count($datos)) . '%; text-align: center; vertical-align: bottom; height: 80px; padding: 0 5px;">';
            $html .= '<div style="display: inline-block; width: 40px; height: ' . max($heightPercent * 0.7, 10) . 'px; background: linear-gradient(to top, #2563eb, #60a5fa); border-radius: 4px 4px 0 0;"></div>';
            $html .= '<div style="font-size: 11pt; font-weight: bold; color: #1f2937; margin-top: 3px;">' . $value . '</div>';
            $html .= '</td>';
        }
        $html .= '</tr>';

        // Fila de etiquetas
        $html .= '<tr>';
        foreach ($datos as $item) {
            $html .= '<td style="text-align: center; padding: 5px; font-size: 8pt; color: #6b7280; border-top: 1px solid #e5e7eb;">';
            $html .= htmlspecialchars(mb_substr($item['mes'], 0, 3));
            $html .= '</td>';
        }
        $html .= '</tr>';

        $html .= '</table>';
        return $html;
    }

    /**
     * Genera análisis ejecutivo usando IA con lenguaje claro
     */
    protected function generarAnalisisConIA(
        Empresa $empresa,
        int $anio,
        ?string $mes,
        Collection $informes,
        array $resumenPorArea,
        array $resumenPorTipo,
        array $resumenPorEstado,
        ?array $resumenPorMes,
        array $metricas
    ): string {
        if ($informes->isEmpty()) {
            return 'No se registraron gestiones jurídicas durante este periodo.';
        }

        try {
            $provider = config('services.ia.provider', 'google');
            $config = config("services.ia.{$provider}", []);
            $apiKey = $config['api_key'] ?? null;
            $model = $config['model'] ?? 'gemini-2.0-flash';

            if (!$apiKey) {
                Log::warning('API key de IA no configurada para informe jurídico');
                return $this->generarAnalisisFallback($empresa, $metricas, $resumenPorArea, $resumenPorTipo);
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $prompt = $this->construirPromptInformeLenguajeClaro(
                $empresa,
                $anio,
                $mes,
                $informes,
                $resumenPorArea,
                $resumenPorTipo,
                $resumenPorEstado,
                $resumenPorMes,
                $metricas
            );

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(180)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 16384, // Tokens suficientes para informe completo y detallado
                    'topP' => 0.9,
                ],
            ]);

            if (!$response->successful()) {
                Log::error('Error en API de IA para informe jurídico', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);
                return $this->generarAnalisisFallback($empresa, $metricas, $resumenPorArea, $resumenPorTipo);
            }

            $responseData = $response->json();
            $contenido = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $contenido = preg_replace('/```html\s*/', '', $contenido);
            $contenido = preg_replace('/```\s*/', '', $contenido);

            return trim($contenido);

        } catch (\Exception $e) {
            Log::error('Excepción al generar análisis con IA', ['error' => $e->getMessage()]);
            return $this->generarAnalisisFallback($empresa, $metricas, $resumenPorArea, $resumenPorTipo);
        }
    }

    /**
     * Construye el prompt para el análisis con lenguaje muy claro
     */
    protected function construirPromptInformeLenguajeClaro(
        Empresa $empresa,
        int $anio,
        ?string $mes,
        Collection $informes,
        array $resumenPorArea,
        array $resumenPorTipo,
        array $resumenPorEstado,
        ?array $resumenPorMes,
        array $metricas
    ): string {
        $periodoTexto = $mes && $mes !== 'todos' ? (self::MESES[$mes] ?? $mes) . " de {$anio}" : "el año {$anio}";
        $fechaActual = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        $datosAreas = collect($resumenPorArea)->map(fn($a) => "- {$a['area']}: {$a['cantidad']} gestiones ({$a['tiempo_formateado']})")->implode("\n");
        $datosTipos = collect($resumenPorTipo)->map(fn($t) => "- {$t['tipo']}: {$t['cantidad']} gestiones ({$t['tiempo_formateado']})")->implode("\n");
        $datosEstados = collect($resumenPorEstado)->map(fn($e) => "- {$e['estado']}: {$e['cantidad']} gestiones")->implode("\n");

        $datosMeses = '';
        if ($resumenPorMes) {
            $datosMeses = "\nEVOLUCIÓN MENSUAL:\n" . collect($resumenPorMes)->map(fn($m) => "- {$m['mes']}: {$m['cantidad']} gestiones ({$m['tiempo_formateado']})")->implode("\n");
        }

        // Limpiar HTML de las descripciones y mostrar completas
        $gestionesDetalle = $informes->map(function($i) {
            $descripcionLimpia = strip_tags(html_entity_decode($i->descripcion ?? ''));
            $descripcionLimpia = trim(preg_replace('/\s+/', ' ', $descripcionLimpia));
            return "- [{$i->estado_texto}] {$i->area_practica_texto} / {$i->tipo_gestion_texto}: {$descripcionLimpia}";
        })->implode("\n");

        return <<<PROMPT
Eres un abogado senior con 15 años de experiencia redactando informes de gestión jurídica. Trabajas para CES LEGAL S.A.S. y estás escribiendo un informe profesional para tu cliente {$empresa->razon_social}.

Tu objetivo es explicar de forma clara y completa:
1. Qué trabajos realizamos para el cliente
2. Cómo cada trabajo benefició a su empresa
3. Qué áreas puede mejorar el cliente
4. Cuál es el valor que CES LEGAL aporta

INFORMACIÓN DEL INFORME:
- Firma proveedora: CES LEGAL S.A.S. (nosotros)
- Cliente: {$empresa->razon_social}
- Periodo del informe: {$periodoTexto}
- Fecha de emisión: {$fechaActual}

MÉTRICAS DEL PERIODO:
- Gestiones totales realizadas: {$metricas['total_gestiones']}
- Tiempo total invertido: {$metricas['tiempo_total']} ({$metricas['horas_invertidas']} horas de trabajo)
- Tiempo promedio por gestión: {$metricas['tiempo_promedio']}
- Gestiones entregadas: {$metricas['gestiones_entregadas']} ({$metricas['tasa_cumplimiento']}% de cumplimiento)
- Gestiones en proceso: {$metricas['gestiones_en_proceso']}
- Gestiones pendientes: {$metricas['gestiones_pendientes']}

DISTRIBUCIÓN POR ÁREA DE PRÁCTICA:
{$datosAreas}

DISTRIBUCIÓN POR TIPO DE GESTIÓN:
{$datosTipos}

ESTADO DE LAS GESTIONES:
{$datosEstados}
{$datosMeses}

DETALLE COMPLETO DE CADA GESTIÓN REALIZADA:
{$gestionesDetalle}

=== INSTRUCCIONES DE REDACCIÓN ===

ESTILO DE ESCRITURA:
- Lenguaje profesional pero accesible (que cualquier empresario entienda)
- Oraciones claras de máximo 25-30 palabras
- Evita jerga legal innecesaria; si usas términos técnicos, explícalos brevemente
- Tono de proveedor informando a su cliente: cercano pero profesional
- Usa primera persona del plural: "Trabajamos...", "Realizamos...", "Asesoramos..."

ENFOQUE EN VALOR Y BENEFICIOS:
- Por cada gestión mencionada, explica el BENEFICIO CONCRETO para la empresa
- Ejemplo: "Elaboramos el contrato de trabajo por obra o labor. Esto permite a la empresa contratar personal para proyectos específicos sin compromisos indefinidos, reduciendo riesgos laborales."
- Conecta siempre el trabajo realizado con protección, ahorro, cumplimiento o mejora para el cliente

=== ESTRUCTURA DEL INFORME ===

Escribe el informe siguiendo EXACTAMENTE esta estructura. Los títulos deben ir en una línea aparte, numerados y en MAYÚSCULAS:

1. CARTA DE PRESENTACIÓN
Escribe 2-3 párrafos:
- Saludo cordial al cliente
- Resumen de lo más destacado del periodo
- Agradecimiento por la confianza depositada
- Menciona el compromiso de CES LEGAL con su empresa

2. RESUMEN EJECUTIVO
Escribe un párrafo sustancioso de 6-8 oraciones que incluya:
- Total de gestiones completadas y horas de trabajo
- Porcentaje de cumplimiento
- Áreas principales atendidas
- Logro más significativo del periodo
- Beneficio general para la empresa

3. DETALLE DE GESTIONES REALIZADAS
Para CADA gestión del listado proporcionado, escribe un párrafo completo que incluya:
- Qué se hizo exactamente (describe la gestión completa)
- Para quién o qué área de la empresa
- Por qué era necesario o importante
- Qué beneficio o protección brinda a la empresa
- El tiempo invertido en esta gestión

IMPORTANTE: No resumas ni omitas gestiones. Describe TODAS las gestiones proporcionadas con detalle suficiente.

4. RESULTADOS Y LOGROS DEL PERIODO
Lista 4-6 logros específicos y medibles:
- Cada logro debe ser concreto (con números cuando sea posible)
- Explica qué significa cada logro para la empresa
- Destaca cómo estos logros protegen o benefician al cliente

5. ANÁLISIS Y OBSERVACIONES
Incluye:
- Patrones observados en las necesidades legales del cliente
- Áreas que requirieron más atención y por qué
- Tendencias que el cliente debe conocer
- Fortalezas identificadas en la gestión del cliente

6. RECOMENDACIONES ESTRATÉGICAS
Proporciona 3-5 recomendaciones concretas y accionables:
- Cada recomendación debe basarse en lo observado durante el periodo
- Explica el por qué de cada recomendación
- Indica qué riesgo previene o qué oportunidad aprovecha
- Las recomendaciones deben ser prácticas y realizables

7. PLAN DE TRABAJO FUTURO
Describe:
- Gestiones pendientes y su estado actual
- Próximas acciones planificadas
- Qué necesitamos del cliente para continuar
- Compromiso de seguimiento

8. CONCLUSIÓN
Escribe 3-4 oraciones finales:
- Reafirma el compromiso de CES LEGAL
- Destaca el valor de la relación con el cliente
- Invita a contactar para cualquier duda o necesidad adicional
- Cierre profesional y cordial

=== REGLAS DE FORMATO ===

OBLIGATORIO:
- Los títulos de sección van en línea aparte, numerados: "1. CARTA DE PRESENTACIÓN"
- Después del título, deja una línea en blanco antes del contenido
- Usa guiones (-) para listas de elementos
- Escribe párrafos completos, no fragmentos
- Mínimo 1200 palabras en total (el informe debe ser completo y detallado)

PROHIBIDO:
- NO uses emojis ni símbolos decorativos
- NO uses formato Markdown (nada de **, *, #, etc.)
- NO uses líneas separadoras (---, ==, etc.)
- NO abrevies ni resumas el contenido
- NO omitas ninguna gestión del listado proporcionado
PROMPT;
    }

    /**
     * Genera análisis básico sin IA como fallback
     */
    protected function generarAnalisisFallback(Empresa $empresa, array $metricas, array $resumenPorArea, array $resumenPorTipo): string
    {
        $areaPrincipal = $resumenPorArea[0] ?? ['area' => 'N/A', 'cantidad' => 0, 'tiempo_formateado' => '0h'];
        $tipoPrincipal = $resumenPorTipo[0] ?? ['tipo' => 'N/A', 'cantidad' => 0];
        $fechaActual = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        $areasTexto = '';
        foreach ($resumenPorArea as $area) {
            $areasTexto .= "- {$area['area']}: {$area['cantidad']} gestiones en {$area['tiempo_formateado']}\n";
        }

        return <<<TEXT
1. CARTA DE PRESENTACIÓN

Estimados directivos de {$empresa->razon_social}:

Es un placer presentarles el informe de gestión jurídica correspondiente al periodo actual. En CES LEGAL S.A.S. nos enorgullece trabajar como su aliado estratégico en materia legal, protegiendo los intereses de su empresa y brindando soluciones oportunas a sus necesidades jurídicas.

Durante este periodo, nuestro equipo de abogados atendió {$metricas['total_gestiones']} gestiones jurídicas, dedicando {$metricas['tiempo_total']} de trabajo especializado a sus asuntos. Agradecemos profundamente la confianza depositada en nuestros servicios.

2. RESUMEN EJECUTIVO

En el periodo reportado, completamos exitosamente {$metricas['gestiones_entregadas']} gestiones, alcanzando una tasa de cumplimiento del {$metricas['tasa_cumplimiento']}%. El área con mayor actividad fue {$areaPrincipal['area']} con {$areaPrincipal['cantidad']} gestiones. El tipo de trabajo más frecuente correspondió a {$tipoPrincipal['tipo']}. Nuestro equipo invirtió un total de {$metricas['horas_invertidas']} horas de trabajo profesional, con un promedio de {$metricas['tiempo_promedio']} por gestión. Estos números reflejan nuestro compromiso con la atención oportuna y eficiente de sus necesidades legales.

3. DETALLE DE GESTIONES REALIZADAS

Durante este periodo trabajamos en las siguientes áreas de práctica legal:

{$areasTexto}
Cada gestión fue realizada con el objetivo de proteger los intereses de su empresa, garantizar el cumplimiento normativo y prevenir contingencias legales que pudieran afectar su operación.

4. RESULTADOS Y LOGROS DEL PERIODO

- Completamos {$metricas['gestiones_entregadas']} gestiones exitosamente con {$metricas['tasa_cumplimiento']}% de cumplimiento.
- Dedicamos {$metricas['horas_invertidas']} horas de trabajo especializado a sus asuntos.
- Atendimos {$metricas['total_areas']} áreas diferentes de práctica legal.
- Mantuvimos una comunicación constante para resolver dudas y avanzar en los procesos.

5. ANÁLISIS Y OBSERVACIONES

El área de {$areaPrincipal['area']} concentró la mayor parte de las gestiones del periodo, lo cual indica que esta es un área prioritaria para la operación de su empresa. Recomendamos mantener especial atención en esta área para prevenir contingencias futuras.

6. RECOMENDACIONES ESTRATÉGICAS

- Mantener actualizada la documentación legal de la empresa para facilitar futuros trámites.
- Consultar con nuestro equipo antes de firmar contratos importantes o tomar decisiones con implicaciones legales.
- Revisar periódicamente el cumplimiento de obligaciones laborales y contractuales.
- Considerar capacitaciones preventivas para el personal en temas de su competencia.

7. PLAN DE TRABAJO FUTURO

Actualmente hay {$metricas['gestiones_pendientes']} gestiones pendientes y {$metricas['gestiones_en_proceso']} en proceso. Estamos trabajando para completarlas en el menor tiempo posible. Continuaremos disponibles para atender nuevas necesidades que surjan y programaremos seguimientos periódicos para mantenerlos informados del avance.

8. CONCLUSIÓN

Agradecemos su confianza en CES LEGAL S.A.S. Estamos comprometidos con la protección legal de su empresa y seguiremos trabajando para brindarle el mejor servicio. No dude en contactarnos para cualquier consulta o necesidad adicional. Quedamos a su entera disposición.
TEXT;
    }

    /**
     * Genera el HTML del informe profesional
     */
    protected function generarHTMLInforme(
        Empresa $empresa,
        $abogado,
        int $anio,
        ?string $mes,
        Collection $informes,
        array $resumenPorArea,
        array $resumenPorTipo,
        array $resumenPorEstado,
        ?array $resumenPorMes,
        array $metricas,
        string $analisisIA,
        string $graficaAreas,
        string $graficaTipos,
        string $graficaEstados,
        string $graficaMeses
    ): string {
        $periodoTexto = $mes && $mes !== 'todos' ? (self::MESES[$mes] ?? $mes) . " de {$anio}" : "Año {$anio}";
        $fechaActual = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        $nombreAbogado = $abogado->name ?? 'Equipo CES LEGAL';

        $tablaAreas = $this->construirTablaHTML($resumenPorArea, ['Área de Práctica', 'Cantidad', 'Tiempo Dedicado'], ['area', 'cantidad', 'tiempo_formateado']);
        $tablaTipos = $this->construirTablaHTML($resumenPorTipo, ['Tipo de Gestión', 'Cantidad', 'Tiempo Dedicado'], ['tipo', 'cantidad', 'tiempo_formateado']);
        $tablaEstados = $this->construirTablaHTML($resumenPorEstado, ['Estado', 'Cantidad'], ['estado', 'cantidad']);
        $tablaMeses = $resumenPorMes ? $this->construirTablaHTML($resumenPorMes, ['Mes', 'Gestiones', 'Tiempo'], ['mes', 'cantidad', 'tiempo_formateado']) : '';
        $tablaDetalle = $this->construirTablaDetalleHTML($informes);

        $analisisFormateado = $this->formatearAnalisisParaHTML($analisisIA);

        $seccionMeses = '';
        if ($resumenPorMes && $graficaMeses) {
            $seccionMeses = <<<HTML
            <div class="section-header">
                <div class="section-title">Evolución Mensual</div>
                <div class="section-subtitle">Tendencia de gestiones a lo largo del año</div>
            </div>
            <div class="grafica-container-full">
                {$graficaMeses}
            </div>
            {$tablaMeses}
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Informe de Gestión Jurídica — CES LEGAL</title>
    <style>
        /* ── Página ───────────────────────────────────────────────── */
        @page {
            margin: 0 0 2.2cm 0;
        }
        @page :first {
            margin: 0 0 2.2cm 0;
        }

        /* ── Base ─────────────────────────────────────────────────── */
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.55;
            color: #1A202C;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        /* ── Variables de color ───────────────────────────────────── */
        /* Navy:  #0F2541  |  Gold: #B8960C  |  Light navy: #1E3A5F  */
        /* Slate: #F7F9FC  |  Border: #DDE3ED                        */

        /* ── Portada / header ─────────────────────────────────────── */
        .doc-header {
            background: #0F2541;
            padding: 28px 36px 0 36px;
            margin-bottom: 0;
        }
        .doc-header-top {
            display: table;
            width: 100%;
            margin-bottom: 18px;
        }
        .doc-header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 60%;
        }
        .firm-name {
            font-size: 17pt;
            font-weight: bold;
            color: #fff;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .firm-tagline {
            font-size: 7.5pt;
            color: #B8960C;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .doc-header-meta {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 40%;
        }
        .doc-header-meta-text {
            font-size: 7.5pt;
            color: #94A3B8;
            line-height: 1.7;
        }
        .doc-title-band {
            background: #0B1D35;
            margin: 0 -36px;
            padding: 14px 36px;
        }
        .doc-title {
            font-size: 13pt;
            font-weight: bold;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #B8960C;
            margin: 3px 0 0 0;
        }
        .doc-client-band {
            background: #132B47;
            margin: 0 -36px;
            padding: 12px 36px;
        }
        .doc-client-table {
            width: 100%;
            border-collapse: collapse;
        }
        .doc-client-table td {
            padding: 2px 12px 2px 0;
            font-size: 8.5pt;
            color: #CBD5E1;
            vertical-align: top;
        }
        .doc-client-table td.lbl {
            color: #94A3B8;
            width: 80px;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-client-table td.val {
            color: #F1F5F9;
            font-weight: bold;
        }
        .gold-bar {
            background: #B8960C;
            height: 4px;
            margin: 0 -36px;
        }

        /* ── Cuerpo del documento ─────────────────────────────────── */
        .doc-body {
            padding: 22px 36px 10px 36px;
        }

        /* ── Badge confidencial ───────────────────────────────────── */
        .badge-confidencial {
            display: table;
            margin: 0 auto 20px auto;
        }
        .badge-confidencial-inner {
            border: 1px solid #DDE3ED;
            border-left: 3px solid #B8960C;
            padding: 5px 14px;
            font-size: 7.5pt;
            color: #64748B;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── KPI Cards ────────────────────────────────────────────── */
        .kpi-section {
            margin: 0 0 22px 0;
        }
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
        }
        .kpi-cell {
            width: 25%;
            background: #0F2541;
            padding: 14px 10px 12px 10px;
            text-align: center;
            vertical-align: middle;
            border-bottom: 3px solid #B8960C;
        }
        .kpi-valor {
            font-size: 20pt;
            font-weight: bold;
            color: #fff;
            line-height: 1.1;
        }
        .kpi-label {
            font-size: 7pt;
            color: #94A3B8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 5px;
        }

        /* ── Encabezados de sección ───────────────────────────────── */
        .section-header {
            border-left: 4px solid #B8960C;
            padding: 6px 0 6px 12px;
            margin: 24px 0 12px 0;
            background: #F7F9FC;
        }
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #0F2541;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 0;
        }
        .section-subtitle {
            font-size: 7.5pt;
            color: #64748B;
            margin: 2px 0 0 0;
        }

        /* ── h4 para análisis IA ──────────────────────────────────── */
        h4 {
            font-size: 9.5pt;
            font-weight: bold;
            color: #0F2541;
            margin: 14px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #DDE3ED;
        }

        /* ── Párrafos ─────────────────────────────────────────────── */
        p { margin: 7px 0; text-align: justify; }
        ul { margin: 6px 0 6px 18px; padding: 0; }
        li { margin: 4px 0; }

        /* ── Tablas de datos ──────────────────────────────────────── */
        table.datos {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 8.5pt;
        }
        table.datos th {
            background: #0F2541;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }
        table.datos th:first-child { border-left: 3px solid #B8960C; }
        table.datos td {
            border-bottom: 1px solid #EEF0F5;
            border-right: none;
            border-left: none;
            padding: 7px 10px;
            color: #2D3748;
            vertical-align: top;
        }
        table.datos tr:nth-child(even) td { background: #F7F9FC; }
        table.datos tr:hover td { background: #EEF3FA; }
        .numero { text-align: center; font-weight: bold; color: #0F2541; }
        .tag {
            display: inline-block;
            background: #EEF3FA;
            color: #1E3A5F;
            padding: 1px 7px;
            font-size: 7.5pt;
            border-radius: 10px;
        }

        /* ── Gráficas ─────────────────────────────────────────────── */
        .graficas-row {
            display: table;
            width: 100%;
            margin: 10px 0;
            border-spacing: 8px;
        }
        .grafica-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 6px;
        }
        .grafica-cell:last-child { padding-right: 0; padding-left: 6px; }
        .grafica-container {
            background: #F7F9FC;
            border: 1px solid #DDE3ED;
            border-top: 3px solid #0F2541;
            padding: 14px;
            margin: 0 0 10px 0;
        }
        .grafica-container-full {
            background: #F7F9FC;
            border: 1px solid #DDE3ED;
            border-top: 3px solid #B8960C;
            padding: 14px;
            margin: 0 0 10px 0;
        }

        /* ── Firma ────────────────────────────────────────────────── */
        .firma-section {
            margin-top: 32px;
            page-break-inside: avoid;
            border-top: 1px solid #DDE3ED;
            padding-top: 18px;
        }
        .firma-table { width: 100%; border-collapse: collapse; }
        .firma-table td { vertical-align: bottom; padding: 0 20px 0 0; }
        .firma-box { margin-top: 44px; }
        .firma-linea {
            border-top: 1.5px solid #0F2541;
            padding-top: 8px;
            width: 220px;
            font-size: 8.5pt;
            color: #2D3748;
        }
        .firma-nombre { font-weight: bold; font-size: 9pt; color: #0F2541; }
        .firma-cargo { color: #64748B; font-size: 8pt; }

        /* ── Footer ───────────────────────────────────────────────── */
        .doc-footer {
            background: #0F2541;
            padding: 10px 36px;
            margin-top: 28px;
        }
        .doc-footer-table { width: 100%; border-collapse: collapse; }
        .doc-footer-table td {
            font-size: 7.5pt;
            color: #94A3B8;
            vertical-align: middle;
            padding: 2px 0;
        }
        .doc-footer-table td.right { text-align: right; }
        .doc-footer-table td strong { color: #CBD5E1; }

        /* ── Salto de página ──────────────────────────────────────── */
        .page-break { page-break-after: always; }

        /* ── Análisis IA ──────────────────────────────────────────── */
        .analisis-section { margin: 4px 0 20px 0; }
        .analisis-section p { margin: 8px 0; }
        .analisis-section ul { margin: 8px 0 8px 20px; }
        .analisis-section li { margin: 5px 0; }

        /* ── Separador ────────────────────────────────────────────── */
        .divider {
            border: none;
            border-top: 1px solid #DDE3ED;
            margin: 18px 0;
        }
    </style>
</head>
<body>

    <!-- ══════════════════════════════════════════
         CABECERA DEL DOCUMENTO
    ══════════════════════════════════════════ -->
    <div class="doc-header">
        <div class="doc-header-top">
            <div class="doc-header-logo">
                <div class="firm-name">CES LEGAL</div>
                <div class="firm-tagline">Asesoría Jurídica Empresarial</div>
            </div>
            <div class="doc-header-meta">
                <div class="doc-header-meta-text">
                    NIT: 901.258.505-4<br>
                    Carrera 2 #10-53<br>
                    Tel: +57 319 677 7103
                </div>
            </div>
        </div>

        <div class="doc-title-band">
            <div class="doc-title">Informe de Gestión Jurídica</div>
            <div class="doc-subtitle">Período: {$periodoTexto}</div>
        </div>

        <div class="doc-client-band">
            <table class="doc-client-table">
                <tr>
                    <td class="lbl">Cliente</td>
                    <td class="val">{$empresa->razon_social}</td>
                    <td class="lbl">NIT</td>
                    <td class="val">{$empresa->nit}</td>
                    <td class="lbl">Fecha</td>
                    <td class="val">{$fechaActual}</td>
                    <td class="lbl">Elaborado por</td>
                    <td class="val">{$nombreAbogado}</td>
                </tr>
            </table>
        </div>

        <div class="gold-bar"></div>
    </div>

    <!-- ══════════════════════════════════════════
         CUERPO
    ══════════════════════════════════════════ -->
    <div class="doc-body">

        <!-- Badge confidencial -->
        <div class="badge-confidencial">
            <div class="badge-confidencial-inner">
                &#9632;&nbsp; Documento Confidencial &nbsp;&#9632;&nbsp; Uso exclusivo de {$empresa->razon_social}
            </div>
        </div>

        <!-- KPIs -->
        <div class="kpi-section">
            <table class="kpi-table">
                <tr>
                    <td class="kpi-cell">
                        <div class="kpi-valor">{$metricas['total_gestiones']}</div>
                        <div class="kpi-label">Gestiones realizadas</div>
                    </td>
                    <td class="kpi-cell">
                        <div class="kpi-valor">{$metricas['tiempo_total']}</div>
                        <div class="kpi-label">Tiempo dedicado</div>
                    </td>
                    <td class="kpi-cell">
                        <div class="kpi-valor">{$metricas['tasa_cumplimiento']}%</div>
                        <div class="kpi-label">Tasa de cumplimiento</div>
                    </td>
                    <td class="kpi-cell">
                        <div class="kpi-valor">{$metricas['horas_invertidas']}h</div>
                        <div class="kpi-label">Horas de trabajo</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Análisis ejecutivo IA -->
        <div class="section-header">
            <div class="section-title">Análisis Ejecutivo</div>
            <div class="section-subtitle">Generado con inteligencia artificial</div>
        </div>
        <div class="analisis-section">
            {$analisisFormateado}
        </div>

    </div><!-- /doc-body -->

    <!-- Footer página 1 -->
    <div class="doc-footer">
        <table class="doc-footer-table">
            <tr>
                <td><strong>CES LEGAL S.A.S.</strong> &nbsp;|&nbsp; Asesoría Jurídica Empresarial</td>
                <td class="right">Documento generado el {$fechaActual}</td>
            </tr>
        </table>
    </div>

    <div class="page-break"></div>

    <!-- ══════════════════════════════════════════
         PÁGINA 2 — ANÁLISIS GRÁFICO
    ══════════════════════════════════════════ -->
    <div class="doc-body">

        <div class="section-header">
            <div class="section-title">Análisis Gráfico</div>
            <div class="section-subtitle">Distribución visual de las gestiones del período</div>
        </div>

        <div class="graficas-row">
            <div class="grafica-cell">
                <div class="grafica-container">
                    {$graficaAreas}
                </div>
            </div>
            <div class="grafica-cell">
                <div class="grafica-container">
                    {$graficaTipos}
                </div>
            </div>
        </div>

        <div class="grafica-container-full">
            {$graficaEstados}
        </div>

        {$seccionMeses}

        <div class="section-header">
            <div class="section-title">Distribución por Área de Práctica</div>
        </div>
        {$tablaAreas}

        <div class="section-header">
            <div class="section-title">Distribución por Tipo de Gestión</div>
        </div>
        {$tablaTipos}

    </div>

    <div class="doc-footer">
        <table class="doc-footer-table">
            <tr>
                <td><strong>CES LEGAL S.A.S.</strong> &nbsp;|&nbsp; Asesoría Jurídica Empresarial</td>
                <td class="right">Documento confidencial — {$empresa->razon_social}</td>
            </tr>
        </table>
    </div>

    <div class="page-break"></div>

    <!-- ══════════════════════════════════════════
         PÁGINA 3 — DETALLE DE GESTIONES
    ══════════════════════════════════════════ -->
    <div class="doc-body">

        <div class="section-header">
            <div class="section-title">Detalle de Gestiones Realizadas</div>
            <div class="section-subtitle">Registro completo de todas las actuaciones del período</div>
        </div>

        {$tablaDetalle}

        <!-- Firma -->
        <div class="firma-section">
            <p style="color:#64748B; font-size:8.5pt;">
                Quedamos a su disposición para ampliar cualquier información contenida en este informe.
            </p>
            <table class="firma-table">
                <tr>
                    <td style="width:260px;">
                        <div class="firma-box">
                            <div class="firma-linea">
                                <div class="firma-nombre">{$nombreAbogado}</div>
                                <div class="firma-cargo">Abogado — CES LEGAL S.A.S.</div>
                                <div class="firma-cargo">NIT: 901.258.505-4</div>
                            </div>
                        </div>
                    </td>
                    <td></td>
                </tr>
            </table>
        </div>

    </div>

    <!-- Footer final -->
    <div class="doc-footer">
        <table class="doc-footer-table">
            <tr>
                <td><strong>CES LEGAL S.A.S.</strong> &nbsp;|&nbsp; Carrera 2 #10-53 &nbsp;|&nbsp; Tel: +57 319 677 7103</td>
                <td class="right">Documento generado el {$fechaActual}</td>
            </tr>
        </table>
    </div>

</body>
</html>
HTML;
    }

    /**
     * Formatea el análisis de IA para HTML
     */
    protected function formatearAnalisisParaHTML(string $analisis): string
    {
        // Primero convertir Markdown a HTML si la IA lo usó a pesar de las instrucciones
        // Convertir **texto** a <strong>texto</strong>
        $analisis = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $analisis);

        // Convertir *texto* a <em>texto</em> (solo asteriscos simples que no son parte de lista)
        $analisis = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $analisis);

        // Eliminar líneas que solo son "--" o "---" o "==="
        $analisis = preg_replace('/^[-=]{2,}$/m', '', $analisis);

        $lineas = explode("\n", $analisis);
        $html = '';
        $enLista = false;

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (empty($linea)) {
                if ($enLista) {
                    $html .= '</ul>';
                    $enLista = false;
                }
                continue;
            }

            // Detectar títulos de sección numerados (ej: "1. CARTA DE PRESENTACIÓN" o "1. Carta de Presentación")
            if (preg_match('/^(\d+)\.\s+([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑa-záéíóúñ\s]+)$/u', $linea, $matches)) {
                if ($enLista) {
                    $html .= '</ul>';
                    $enLista = false;
                }
                // Convertir título a mayúsculas para consistencia
                $tituloFormateado = $matches[1] . '. ' . mb_strtoupper($matches[2]);
                $html .= '<h4>' . htmlspecialchars($tituloFormateado) . '</h4>';
            }
            // Detectar subtítulos en mayúsculas sin número (ej: "MÉTRICAS DEL PERIODO:")
            elseif (preg_match('/^([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]{5,}):?$/u', $linea)) {
                if ($enLista) {
                    $html .= '</ul>';
                    $enLista = false;
                }
                $html .= '<h4>' . htmlspecialchars(rtrim($linea, ':')) . '</h4>';
            }
            // Lista con asterisco *
            elseif (preg_match('/^\*\s+(.+)$/', $linea, $matches)) {
                if (!$enLista) {
                    $html .= '<ul>';
                    $enLista = true;
                }
                $html .= '<li>' . $matches[1] . '</li>';
            }
            // Lista con guión -
            elseif (preg_match('/^-\s+(.+)$/', $linea, $matches)) {
                if (!$enLista) {
                    $html .= '<ul>';
                    $enLista = true;
                }
                $html .= '<li>' . $matches[1] . '</li>';
            }
            // Lista numerada (ej: "1. Item", "a) Item", "a. Item")
            elseif (preg_match('/^(?:\d+[\.\)]\s+|[a-z][\.\)]\s+)(.+)$/i', $linea, $matches)) {
                // Solo si no es un título de sección (verificar que no sea todo mayúsculas)
                if (!preg_match('/^[A-ZÁÉÍÓÚÑ\s]+$/', $matches[1])) {
                    if (!$enLista) {
                        $html .= '<ul>';
                        $enLista = true;
                    }
                    $html .= '<li>' . $matches[1] . '</li>';
                } else {
                    // Es un título, no una lista
                    if ($enLista) {
                        $html .= '</ul>';
                        $enLista = false;
                    }
                    $html .= '<h4>' . htmlspecialchars($linea) . '</h4>';
                }
            }
            // Párrafo normal
            else {
                if ($enLista) {
                    $html .= '</ul>';
                    $enLista = false;
                }
                $html .= '<p>' . $linea . '</p>';
            }
        }

        if ($enLista) {
            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * Construye una tabla HTML genérica
     */
    protected function construirTablaHTML(array $datos, array $encabezados, array $campos): string
    {
        if (empty($datos)) {
            return '<p>No hay datos disponibles para este periodo.</p>';
        }

        $html = '<table class="datos"><thead><tr>';
        foreach ($encabezados as $encabezado) {
            $html .= '<th>' . $encabezado . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($datos as $fila) {
            $html .= '<tr>';
            foreach ($campos as $index => $campo) {
                $valor = $fila[$campo] ?? '-';
                $clase = is_numeric($valor) ? ' class="numero"' : '';
                $html .= '<td' . $clase . '>' . htmlspecialchars($valor) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Construye la tabla de detalle de gestiones
     */
    protected function construirTablaDetalleHTML(Collection $informes): string
    {
        if ($informes->isEmpty()) {
            return '<p>No se registraron gestiones durante este periodo.</p>';
        }

        $html = '<table class="datos"><thead><tr>';
        $html .= '<th style="width: 5%;">#</th>';
        $html .= '<th style="width: 8%;">Mes</th>';
        $html .= '<th style="width: 15%;">Área</th>';
        $html .= '<th style="width: 15%;">Tipo</th>';
        $html .= '<th style="width: 37%;">Descripción</th>';
        $html .= '<th style="width: 10%;">Estado</th>';
        $html .= '<th style="width: 10%;">Tiempo</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($informes as $index => $informe) {
            // Limpiar HTML de la descripción y decodificar entidades
            $descripcionLimpia = strip_tags(html_entity_decode($informe->descripcion ?? ''));
            $descripcionLimpia = trim(preg_replace('/\s+/', ' ', $descripcionLimpia));

            $html .= '<tr>';
            $html .= '<td class="numero">' . ($index + 1) . '</td>';
            $html .= '<td>' . htmlspecialchars($informe->mes_texto) . '</td>';
            $html .= '<td>' . htmlspecialchars($informe->area_practica_texto) . '</td>';
            $html .= '<td>' . htmlspecialchars($informe->tipo_gestion_texto) . '</td>';
            $html .= '<td>' . htmlspecialchars(\Str::limit($descripcionLimpia, 70)) . '</td>';
            $html .= '<td>' . htmlspecialchars($informe->estado_texto) . '</td>';
            $html .= '<td class="numero">' . htmlspecialchars($informe->tiempo_formateado) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Genera el Excel del informe
     */
    public function generarExcel(int $empresaId, int $anio, ?string $mes = null): string
    {
        $empresa = Empresa::findOrFail($empresaId);
        $informes = $this->getInformes($empresaId, $anio, $mes);

        $resumenPorArea = $this->getResumenPorArea($informes);
        $resumenPorTipo = $this->getResumenPorTipo($informes);
        $resumenPorEstado = $this->getResumenPorEstado($informes);
        $resumenPorMes = $mes === 'todos' ? $this->getResumenPorMes($informes) : null;

        $spreadsheet = new Spreadsheet();

        $this->crearHojaResumen($spreadsheet, $empresa, $anio, $mes, $informes, $resumenPorArea, $resumenPorTipo, $resumenPorEstado, $resumenPorMes);
        $this->crearHojaDetalle($spreadsheet, $informes);

        $filename = 'informe-juridico-' . $empresa->id . '-' . $anio . '-' . ($mes ?? 'anual') . '.xlsx';
        $path = 'exports/' . $filename;
        $fullPath = Storage::disk('public')->path($path);

        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($fullPath);

        return $path;
    }

    protected function crearHojaResumen(Spreadsheet $spreadsheet, Empresa $empresa, int $anio, ?string $mes, Collection $informes, array $resumenPorArea, array $resumenPorTipo, array $resumenPorEstado, ?array $resumenPorMes): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $subHeaderStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
        ];

        $tableHeaderStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $cellStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        // Encabezado
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'INFORME DE GESTIÓN JURÍDICA - CES LEGAL S.A.S.');
        $sheet->getStyle('A1')->applyFromArray($headerStyle);

        $sheet->setCellValue('A3', 'Presentado a:');
        $sheet->setCellValue('B3', $empresa->razon_social);
        $sheet->setCellValue('A4', 'NIT Cliente:');
        $sheet->setCellValue('B4', $empresa->nit);
        $sheet->setCellValue('A5', 'Periodo:');
        $sheet->setCellValue('B5', $anio . ' - ' . ($mes && $mes !== 'todos' ? (self::MESES[$mes] ?? $mes) : 'Anual'));
        $sheet->setCellValue('A6', 'Generado:');
        $sheet->setCellValue('B6', now()->format('d/m/Y H:i'));
        $sheet->getStyle('A3:A6')->applyFromArray(['font' => ['bold' => true]]);

        $sheet->setCellValue('A8', 'Total Gestiones:');
        $sheet->setCellValue('B8', $informes->count());
        $sheet->setCellValue('A9', 'Tiempo Total:');
        $sheet->setCellValue('B9', $this->formatearTiempo($informes->sum('tiempo_minutos')));
        $sheet->getStyle('A8:A9')->applyFromArray(['font' => ['bold' => true]]);

        $row = 11;

        if (!empty($resumenPorArea)) {
            $sheet->setCellValue("A{$row}", 'GESTIONES POR ÁREA');
            $sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", 'Área');
            $sheet->setCellValue("B{$row}", 'Cantidad');
            $sheet->setCellValue("C{$row}", 'Tiempo');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($tableHeaderStyle);
            $row++;

            foreach ($resumenPorArea as $item) {
                $sheet->setCellValue("A{$row}", $item['area']);
                $sheet->setCellValue("B{$row}", $item['cantidad']);
                $sheet->setCellValue("C{$row}", $item['tiempo_formateado']);
                $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($cellStyle);
                $row++;
            }
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(20);
    }

    protected function crearHojaDetalle(Spreadsheet $spreadsheet, Collection $informes): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detalle');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $cellStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $headers = ['#', 'Mes', 'Área', 'Tipo', 'Descripción', 'Estado', 'Tiempo', 'Fecha'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($informes as $index => $informe) {
            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $informe->mes_texto);
            $sheet->setCellValue("C{$row}", $informe->area_practica_texto);
            $sheet->setCellValue("D{$row}", $informe->tipo_gestion_texto);
            $sheet->setCellValue("E{$row}", $informe->descripcion);
            $sheet->setCellValue("F{$row}", $informe->estado_texto);
            $sheet->setCellValue("G{$row}", $informe->tiempo_formateado);
            $sheet->setCellValue("H{$row}", $informe->created_at->format('d/m/Y'));
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($cellStyle);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(50);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(12);

        $sheet->freezePane('A2');
    }

    public static function getMeses(): array
    {
        return self::MESES;
    }

    public static function getAniosDisponibles(): array
    {
        $anioActual = now()->year;
        $anios = [];

        for ($i = $anioActual; $i >= $anioActual - 5; $i--) {
            $anios[$i] = (string) $i;
        }

        return $anios;
    }
}
