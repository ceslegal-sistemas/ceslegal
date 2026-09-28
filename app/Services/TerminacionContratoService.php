<?php

namespace App\Services;

use App\Models\ArticuloLegal;
use App\Models\Configuracion;
use App\Models\SolicitudContrato;
use App\Models\TerminacionContrato;
use App\Support\PdfProteccion;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/**
 * Cálculo y registro de la Terminación de Contrato (justa causa / sin justa
 * causa + indemnización). 100% determinístico - sin llamadas a IA: el monto
 * que se le debe a un trabajador no debe depender de un LLM probabilístico,
 * y además es más barato (pedido explícito del usuario: buscar siempre
 * oportunidades de optimizar costos, no solo UX).
 */
class TerminacionContratoService
{
    // Reutiliza la tabla genérica clave/valor ya existente (Configuracion,
    // usada hoy por ConfiguracionWhatsapp) en vez de crear una tabla nueva
    // solo para el SMLMV - el mecanismo ya existe en este proyecto.
    public const CLAVE_SMLMV_VIGENTE = 'smlmv_vigente';

    /**
     * Días de salario para el cálculo del Art. 64 CST (modificado por el
     * Art. 28 de la Ley 789 de 2002) en Contrato a Término Indefinido.
     */
    private const DIAS_BASE_MENOR_10_SMLMV = 30;
    private const DIAS_ADICIONALES_POR_ANIO_MENOR_10_SMLMV = 20;
    private const DIAS_BASE_MAYOR_IGUAL_10_SMLMV = 20;
    private const DIAS_ADICIONALES_POR_ANIO_MAYOR_IGUAL_10_SMLMV = 15;

    // Convención "año comercial" de 30 días por mes - misma usada
    // implícitamente en el resto del sistema para el salario diario
    // (salario mensual / 30).
    private const DIAS_POR_MES = 30;

    public function __construct(private readonly SolicitudContratoIAService $solicitudContratoIAService)
    {
    }

    /**
     * @return array{dias: int, monto: float, detalle: string}|null null si
     *         es con justa causa (nunca hay indemnización) o si el tipo de
     *         contrato no llegó a generar días pendientes.
     *
     * @throws \RuntimeException si el contrato es a Término Indefinido y no
     *         hay un SMLMV configurado en Parámetros Legales.
     */
    public function calcularIndemnizacion(SolicitudContrato $solicitud, string $tipo, Carbon $fechaTerminacion): ?array
    {
        if ($tipo === 'con_justa_causa') {
            return null;
        }

        $salarioMensual = (float) $solicitud->salario_propuesto;
        $salarioDiario = $salarioMensual / self::DIAS_POR_MES;

        if ($solicitud->tipo_contrato === 'Contrato a Término Indefinido') {
            return $this->calcularIndemnizacionIndefinido($solicitud, $salarioMensual, $salarioDiario, $fechaTerminacion);
        }

        return $this->calcularIndemnizacionPlazoFijo($solicitud, $salarioDiario, $fechaTerminacion);
    }

    /**
     * Fijo / Obra o Labor / Ocasional: la indemnización es lo que falte del
     * plazo pactado (o de la obra) - Art. 64 CST.
     */
    private function calcularIndemnizacionPlazoFijo(SolicitudContrato $solicitud, float $salarioDiario, Carbon $fechaTerminacion): array
    {
        $fechaFin = Carbon::parse($solicitud->fecha_fin_contrato);
        $dias = (int) max(0, $fechaTerminacion->diffInDays($fechaFin, false));

        $monto = round($dias * $salarioDiario, 2);

        $detalle = $dias > 0
            ? "Quedaban {$dias} días para el vencimiento pactado ({$fechaFin->locale('es')->isoFormat('D [de] MMMM [de] YYYY')}). Indemnización = {$dias} días x salario diario ($" . number_format($salarioDiario, 2) . ')'
            : 'La fecha de terminación coincide con el vencimiento del plazo pactado o es posterior - no quedan días pendientes por indemnizar.';

        return ['dias' => $dias, 'monto' => $monto, 'detalle' => $detalle];
    }

    /**
     * Indefinido: fórmula escalonada del Art. 64 CST (modificado por el
     * Art. 28 de la Ley 789 de 2002) según el salario frente a 10 SMLMV y la
     * antigüedad (siempre desde fecha_inicio_propuesta - la fecha de ingreso
     * ORIGINAL, inmutable entre prórrogas, a diferencia de
     * fecha_inicio_periodo_actual que se resetea en cada renovación).
     */
    private function calcularIndemnizacionIndefinido(SolicitudContrato $solicitud, float $salarioMensual, float $salarioDiario, Carbon $fechaTerminacion): array
    {
        $smlmv = (float) Configuracion::obtener(self::CLAVE_SMLMV_VIGENTE, 0);
        if ($smlmv <= 0) {
            throw new \RuntimeException('Configure el SMLMV vigente en Parámetros Legales antes de calcular una indemnización de contrato a término indefinido.');
        }

        $aniosServicio = $this->calcularAniosDeServicio($solicitud, $fechaTerminacion);

        $superaDiezSmlmv = $salarioMensual >= ($smlmv * 10);
        $diasBase = $superaDiezSmlmv ? self::DIAS_BASE_MAYOR_IGUAL_10_SMLMV : self::DIAS_BASE_MENOR_10_SMLMV;
        $diasPorAnioAdicional = $superaDiezSmlmv ? self::DIAS_ADICIONALES_POR_ANIO_MAYOR_IGUAL_10_SMLMV : self::DIAS_ADICIONALES_POR_ANIO_MENOR_10_SMLMV;

        $dias = $diasBase;
        $aniosAdicionales = 0.0;
        if ($aniosServicio > 1) {
            $aniosAdicionales = $aniosServicio - 1;
            $dias += $diasPorAnioAdicional * $aniosAdicionales;
        }
        $dias = (int) round($dias);

        $monto = round($dias * $salarioDiario, 2);

        $detalle = sprintf(
            'Salario %s 10 SMLMV ($%s). Antigüedad: %.2f años. Base: %d días%s. Total: %d días x salario diario ($%s).',
            $superaDiezSmlmv ? 'igual o mayor a' : 'menor a',
            number_format($smlmv * 10, 2),
            $aniosServicio,
            $diasBase,
            $aniosAdicionales > 0 ? sprintf(' + %.2f años adicionales x %d días', $aniosAdicionales, $diasPorAnioAdicional) : '',
            $dias,
            number_format($salarioDiario, 2)
        );

        return ['dias' => $dias, 'monto' => $monto, 'detalle' => $detalle];
    }

    /**
     * Años completos de servicio + fracción proporcional, usando años
     * calendario reales (respeta años bisiestos vía Carbon::diffInYears) -
     * NO la convención comercial de 360 días que sí se usa para el salario
     * diario. La antigüedad para efectos de esta indemnización se cuenta por
     * aniversarios reales, no por una división aritmética de días.
     */
    private function calcularAniosDeServicio(SolicitudContrato $solicitud, Carbon $fechaTerminacion): float
    {
        $fechaIngreso = Carbon::parse($solicitud->fecha_inicio_propuesta);
        if ($fechaTerminacion->lessThanOrEqualTo($fechaIngreso)) {
            return 0.0;
        }

        $aniosCompletos = $fechaIngreso->diffInYears($fechaTerminacion);
        $aniversario = $fechaIngreso->copy()->addYears($aniosCompletos);
        $diasFraccion = $aniversario->diffInDays($fechaTerminacion);

        return $aniosCompletos + ($diasFraccion / 365);
    }

    /**
     * Orquesta todo el flujo: calcula (si aplica), crea el registro,
     * actualiza el contrato y al trabajador, y genera el documento formal.
     *
     * @param array{tipo: string, motivo?: ?string, fecha_terminacion: string|Carbon, proceso_disciplinario_id?: ?int} $datos
     */
    public function terminar(SolicitudContrato $solicitud, array $datos): TerminacionContrato
    {
        $fechaTerminacion = Carbon::parse($datos['fecha_terminacion']);
        $tipo = $datos['tipo'];

        $calculo = $this->calcularIndemnizacion($solicitud, $tipo, $fechaTerminacion);

        $terminacion = TerminacionContrato::create([
            'solicitud_contrato_id' => $solicitud->id,
            'abogado_id' => auth()->id(),
            'proceso_disciplinario_id' => $datos['proceso_disciplinario_id'] ?? null,
            'tipo' => $tipo,
            'motivo' => $datos['motivo'] ?? null,
            'fecha_terminacion' => $fechaTerminacion,
            'salario_base_calculo' => $solicitud->salario_propuesto,
            'dias_indemnizacion' => $calculo['dias'] ?? null,
            'monto_indemnizacion' => $calculo['monto'] ?? null,
            'detalle_calculo' => $calculo['detalle'] ?? null,
        ]);

        $solicitud->update(['estado' => 'terminado']);
        $solicitud->trabajador?->update(['active' => false]);

        $this->generarDocumentoPDF($terminacion);

        return $terminacion->refresh();
    }

    public function generarDocumentoPDF(TerminacionContrato $terminacion): string
    {
        $solicitud = $terminacion->solicitudContrato;
        $empresa = $solicitud->empresa;

        $codigoArticulo = $terminacion->tipo === 'con_justa_causa' ? 'Art. 62 CST' : 'Art. 64 CST';
        $articulo = ArticuloLegal::activos()->where('codigo', $codigoArticulo)->first();

        $html = view('pdfs.contratos.terminacion', [
            'empresa' => $empresa,
            'municipioEmpresa' => $empresa?->ciudad ?? '',
            'departamentoEmpresa' => $empresa?->departamento ?? '',
            'fechaCarta' => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'nombreTrabajador' => trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}"),
            'numeroDocumento' => $solicitud->trabajador_documento_numero,
            'nombreEmpresa' => $empresa?->nombre_completo ?? '',
            'nit' => $empresa?->nit ?? '',
            'representanteLegal' => $empresa?->representante_legal ?? '',
            'terminacion' => $terminacion,
            'fechaTerminacionTexto' => $terminacion->fecha_terminacion->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'codigoArticulo' => $codigoArticulo,
            'textoArticuloVerbatim' => trim((string) $articulo?->texto_completo),
        ])->render();

        $directorioRelativo = "solicitudes-contrato/{$solicitud->empresa_id}/terminaciones";
        Storage::disk('local')->makeDirectory($directorioRelativo);

        $rutaRelativa = "{$directorioRelativo}/terminacion_{$terminacion->id}.pdf";
        $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        PdfProteccion::proteger($dompdf, PdfProteccion::ownerPassword($solicitud->empresa_id, 'terminacion'));

        file_put_contents($rutaAbsoluta, $dompdf->output());

        $terminacion->update([
            'ruta_documento' => $rutaRelativa,
            'fecha_generacion_documento' => now(),
        ]);

        return $rutaRelativa;
    }

    /**
     * Botón opcional "Redactar con IA" del campo Motivo (solo con justa
     * causa) - convierte una nota breve del abogado en un párrafo formal.
     * Puramente asistivo: el abogado puede ignorarlo y escribir el motivo a
     * mano, por eso NO es parte del cálculo ni bloquea nada si falla.
     */
    public function redactarMotivo(string $notaBreve, SolicitudContrato $solicitud): string
    {
        return $this->solicitudContratoIAService->redactarMotivoTerminacion($notaBreve, $solicitud);
    }
}
