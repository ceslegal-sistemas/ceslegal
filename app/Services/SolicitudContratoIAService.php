<?php

namespace App\Services;

use App\Models\ModificacionContractual;
use App\Models\ReglamentoInterno;
use App\Models\SolicitudContrato;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * IA para el flujo de SolicitudContrato (los 6 tipos de contrato de
 * SolicitudContratoResource, gestión interna de CES Legal - "Análisis
 * Jurídico"): redacta un borrador del objeto jurídico anclado en el CST
 * (mismo principio que LUPE Legal - la IA no razona desde su propio criterio,
 * cita solo artículos realmente provistos) y genera el PDF final del
 * contrato. No usa DocumentoService (framework de plantillas sin ninguna
 * referencia real en el proyecto) - usa el mismo patrón HTML+Dompdf de
 * DocumentGeneratorService, que sí es el que se usa en todo el sistema.
 */
class SolicitudContratoIAService
{
    public function __construct(
        private readonly RITGeneratorService $ritGeneratorService,
        private readonly ReglamentoInternoService $reglamentoInternoService,
        private readonly TimelineService $timelineService,
    ) {}

    /**
     * Redacta un borrador del objeto jurídico del contrato. NO lo persiste -
     * el llamador decide (el abogado revisa/edita el borrador en el
     * RichEditor antes de guardar el formulario).
     */
    /**
     * Descripción legal + tema de búsqueda CST por tipo_contrato. Antes este
     * prompt tenía "obra o labor determinada" HARDCODEADO sin importar el
     * tipo_contrato real de la solicitud (el servicio nació pensado solo
     * para ese tipo, según su propio docblock de clase) - producía un
     * objeto jurídico legalmente INCORRECTO para cualquiera de los otros 5
     * tipos (ej. describía "obra o labor" en un contrato a Término Fijo).
     * Bug real encontrado con Livewire::test() usando IA real, no teórico.
     */
    private const DESCRIPCION_TIPO_CONTRATO = [
        'Contrato a Término Fijo'             => ['descripcion' => 'a término fijo', 'tema_cst' => 'contrato trabajo término fijo duración renovación', 'regimen' => 'laboral'],
        'Contrato a Término Indefinido'       => ['descripcion' => 'a término indefinido', 'tema_cst' => 'contrato trabajo término indefinido', 'regimen' => 'laboral'],
        'Contrato de Obra o Labor'            => ['descripcion' => 'por obra o labor determinada', 'tema_cst' => 'contrato trabajo obra labor determinada duración', 'regimen' => 'laboral'],
        // 'civil', no 'laboral': un contrato de prestación de servicios NO es
        // una relación laboral (no hay subordinación) - se rige por el Código
        // Civil/Comercio, no por el CST. La tabla articulos_legales (1472
        // registros verificados) SOLO tiene CST + Ley 1010 + Res. 652/2012,
        // ningún artículo civil/comercial real - buscar en el CST para este
        // tipo no solo es inútil, es contraproducente: citar subordinación en
        // un contrato que por definición no la tiene es el argumento típico
        // que usan los jueces para reclasificarlo como "contrato realidad"
        // (relación laboral disfrazada), con toda la carga prestacional
        // retroactiva que eso implica para el cliente. Pregunta real del
        // usuario la que hizo notar este gap - la prueba empírica de hoy no
        // citó CST por suerte (el buscador no encontró coincidencias), no
        // por diseño; sin este fix el riesgo seguía latente.
        'Contrato de Prestación de Servicios' => ['descripcion' => 'de prestación de servicios (civil/comercial, independiente, sin subordinación)', 'tema_cst' => null, 'regimen' => 'civil'],
        'Contrato de Aprendizaje'             => ['descripcion' => 'de aprendizaje', 'tema_cst' => 'contrato aprendizaje SENA estudiante', 'regimen' => 'laboral'],
        'Contrato Ocasional o Transitorio'    => ['descripcion' => 'ocasional o transitorio (máximo 30 días)', 'tema_cst' => 'contrato trabajo ocasional accidental transitorio', 'regimen' => 'laboral'],
    ];

    public function redactarObjetoJuridico(SolicitudContrato $solicitud): string
    {
        $infoTipo = self::DESCRIPCION_TIPO_CONTRATO[$solicitud->tipo_contrato]
            ?? ['descripcion' => 'de trabajo', 'tema_cst' => 'contrato trabajo', 'regimen' => 'laboral'];

        $articulosCst = $infoTipo['tema_cst']
            ? $this->ritGeneratorService->buscarArticulosPorTema($infoTipo['tema_cst'], limite: 6)
            : '';

        $prompt = $this->construirPromptObjeto($solicitud, $articulosCst, $infoTipo['descripcion'], $infoTipo['regimen']);

        return $this->llamarGemini($prompt, $solicitud->empresa_id);
    }

    /**
     * Redacta las cláusulas de DURACIÓN y TERMINACIÓN para un Contrato de
     * Obra o Labor - en el documento real ambas están literalmente en
     * blanco ("DILIGENCIAR"), porque dependen de CUÁL obra/labor específica
     * se contrata. Mismo patrón anclado que redactarObjetoJuridico(): la IA
     * NO razona desde su propio criterio, solo redacta a partir de la
     * descripción provista + artículos del CST realmente recuperados.
     */
    public function redactarDuracionTerminacionObraLabor(SolicitudContrato $solicitud): string
    {
        $articulosCst = $this->ritGeneratorService->buscarArticulosPorTema(
            'contrato trabajo obra labor determinada duración terminación',
            limite: 6
        );

        $prompt = $this->construirPromptDuracionTerminacionObraLabor($solicitud, $articulosCst);

        return $this->llamarGemini($prompt, $solicitud->empresa_id);
    }

    private function construirPromptDuracionTerminacionObraLabor(SolicitudContrato $solicitud, string $articulosCst): string
    {
        $empresa = $solicitud->empresa()->with(['actividadEconomica', 'actividadesSecundarias'])->first();

        $actividadPrincipal = $empresa?->actividadEconomica?->nombre;
        $actividadesSecundarias = $empresa?->actividadesSecundarias?->pluck('nombre')->filter()->implode('; ');

        $datosEmpresa = collect([
            'Nombre'                              => $empresa?->nombre_completo,
            'NIT'                                 => $empresa?->nit,
            'Ciudad/Departamento'                 => trim(collect([$empresa?->ciudad, $empresa?->departamento])->filter()->implode(', '), ', ') ?: null,
            'Representante legal'                 => $empresa?->representante_legal,
            'Actividad económica principal'       => $actividadPrincipal,
            'Actividades económicas secundarias'  => $actividadesSecundarias ?: null,
        ])->filter()->map(fn($valor, $campo) => "- {$campo}: {$valor}")->implode("\n");

        if ($datosEmpresa === '') {
            $datosEmpresa = '(Sin datos de perfil de la empresa registrados en el sistema.)';
        }

        $descripcionObra = trim((string) $solicitud->descripcion_obra_labor);
        $fechaInicio      = $solicitud->fecha_inicio_propuesta?->format('Y-m-d') ?? 'No especificada';

        return <<<PROMPT
        Eres un abogado laboral colombiano redactando las cláusulas de DURACIÓN y
        TERMINACIÓN de un contrato de trabajo POR OBRA O LABOR DETERMINADA,
        con base ÚNICAMENTE en los datos provistos abajo.

        DATOS DE LA EMPRESA:
        {$datosEmpresa}

        PROHIBICIÓN ABSOLUTA: No inventes una fecha de finalización fija,
        un plazo en días/meses/años, ni ningún dato que no esté
        explícitamente en "DATOS DE LA SOLICITUD" abajo. La duración de un
        contrato por obra o labor NUNCA es una fecha calendario fija -
        está atada a la finalización de la obra/labor descrita.

        PROHIBICIÓN ABSOLUTA: Solo puedes citar artículos del CST que
        aparezcan en la sección "ARTÍCULOS DEL CST DISPONIBLES" abajo. Si
        ninguno aplica exactamente a lo que estás redactando, redacta sin
        citar ningún número de artículo en vez de inventar uno.

        DATOS DE LA SOLICITUD:
        - Obra o labor contratada: {$descripcionObra}
        - Fecha de inicio de labores: {$fechaInicio}

        ARTÍCULOS DEL CST DISPONIBLES:
        {$articulosCst}

        Redacta el texto de 2 cláusulas, EXACTAMENTE en este formato (dos
        párrafos HTML, cada uno con su número de cláusula real - el
        sistema NO agrega ningún título, tu salida se inserta tal cual en
        el documento final):

        <p class="clausula"><span class="clausula-titulo">NOVENA: DURACIÓN DEL CONTRATO.</span> [texto que ate la duración a la finalización de la obra/labor descrita arriba, mencionando la fecha de inicio de labores si fue provista]</p>

        <p class="clausula"><span class="clausula-titulo">DÉCIMA: TERMINACIÓN DEL CONTRATO.</span> [texto que establezca que el contrato termina a la finalización de la obra/labor descrita, sin fecha fija]</p>

        No agregues ningún comentario, explicación ni texto fuera de estas
        2 cláusulas.
        PROMPT;
    }

    private function construirPromptObjeto(SolicitudContrato $solicitud, string $articulosCst, string $descripcionTipo, string $regimen): string
    {
        $nombreTrabajador = trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}");
        $fechaInicio       = $solicitud->fecha_inicio_propuesta?->format('Y-m-d') ?? 'No especificada';
        $salario           = $solicitud->salario_propuesto ?? 'No especificado';

        if ($regimen === 'civil') {
            // Sin sección de artículos del CST a propósito: este sistema no
            // tiene ningún artículo del Código Civil/Comercio cargado, y
            // buscar en el CST (pensado para relaciones laborales) es
            // legalmente contraproducente para un contrato que por
            // definición no tiene subordinación - ver comentario en
            // DESCRIPCION_TIPO_CONTRATO arriba.
            return <<<PROMPT
            Eres un abogado laboralista colombiano redactando el OBJETO JURÍDICO de un
            contrato {$descripcionTipo}, con base ÚNICAMENTE en los datos
            provistos abajo.

            PROHIBICIÓN ABSOLUTA: No inventes cargo, funciones, honorarios,
            fechas ni ningún dato que no esté explícitamente en "DATOS DE LA
            SOLICITUD" abajo.

            PROHIBICIÓN ABSOLUTA: No cites artículos del Código Sustantivo del
            Trabajo ni ninguna otra norma - este es un contrato civil/
            comercial, no una relación laboral, y no hay una fuente jurídica
            civil verificada disponible para citar con precisión.

            OBLIGATORIO: El texto debe dejar explícito que la relación es
            independiente, sin subordinación ni relación laboral, sin horario
            fijo impuesto por el contratante más allá de los entregables
            acordados - es la característica que distingue este contrato de
            uno laboral, y omitirla es un riesgo real de que la relación se
            reclasifique como "contrato realidad" (relación laboral
            disfrazada).

            DATOS DE LA SOLICITUD:
            - Contratista: {$nombreTrabajador}, calidad: {$solicitud->cargo_contrato}
            - Responsabilidades/servicios: {$solicitud->responsabilidades}
            - Objeto comercial (contexto del negocio que RRHH describió): {$solicitud->objeto_comercial}
            - Alcance detallado: {$solicitud->manual_funciones}
            - Honorarios propuestos: {$salario}
            - Fecha de inicio propuesta: {$fechaInicio}

            Redacta el objeto jurídico del contrato {$descripcionTipo} en 1-3
            párrafos de prosa jurídica formal, en tercera persona, describiendo
            con precisión el servicio que se contrata (a partir del objeto
            comercial y el alcance provistos), sin markdown ni asteriscos. No
            repitas los datos en formato de lista - redáctalos como un objeto
            contractual coherente.
            PROMPT;
        }

        return <<<PROMPT
        Eres un abogado laboralista colombiano redactando el OBJETO JURÍDICO
        de un contrato de trabajo {$descripcionTipo}, con base ÚNICAMENTE en
        los datos provistos y los artículos del Código Sustantivo del
        Trabajo (CST) listados abajo.

        PROHIBICIÓN ABSOLUTA: Solo puedes citar artículos del CST que
        aparezcan en la sección "ARTÍCULOS DEL CST DISPONIBLES" de abajo. Si
        ninguno aplica exactamente, redacta sin citar número de artículo en
        vez de inventar uno.

        PROHIBICIÓN ABSOLUTA: No inventes cargo, funciones, salario, fechas
        ni ningún dato que no esté explícitamente en "DATOS DE LA SOLICITUD"
        abajo.

        DATOS DE LA SOLICITUD:
        - Tipo de contrato: {$solicitud->tipo_contrato}
        - Trabajador: {$nombreTrabajador}, cargo: {$solicitud->cargo_contrato}
        - Responsabilidades: {$solicitud->responsabilidades}
        - Objeto comercial (contexto del negocio que RRHH describió): {$solicitud->objeto_comercial}
        - Manual de funciones: {$solicitud->manual_funciones}
        - Salario propuesto: {$salario}
        - Fecha de inicio propuesta: {$fechaInicio}

        ARTÍCULOS DEL CST DISPONIBLES:
        {$articulosCst}

        Redacta el objeto jurídico del contrato de trabajo {$descripcionTipo}
        en 1-3 párrafos de prosa jurídica formal, en tercera persona,
        describiendo con precisión la labor que se contrata (a partir del
        objeto comercial y el manual de funciones provistos), sin markdown
        ni asteriscos. No repitas los datos en formato de lista - redáctalos
        como un objeto contractual coherente. El texto debe ser consistente
        con el tipo de contrato indicado arriba - nunca describir una
        modalidad distinta a la señalada.
        PROMPT;
    }

    /**
     * Genera un borrador de los 3 campos del paso "Detalles del Cargo"
     * (responsabilidades, objeto comercial, manual de funciones) a partir
     * del cargo, los datos del trabajador y el RIT vigente de la empresa -
     * NO existe ningún archivo de "manual de funciones" subido en ningún
     * punto del sistema (al crear el RIT solo hay un toggle sí/no, sin
     * adjunto real), así que este método lo REDACTA, no lo copia de
     * ninguna parte. No persiste - el llamador decide (funciona igual en
     * creación, antes de que exista el registro, que en edición).
     *
     * @return array{responsabilidades: string, objeto_comercial: string, manual_funciones: string}
     */
    public function completarDetallesCargo(SolicitudContrato $solicitud): array
    {
        // orderByDesc('updated_at') como defensa adicional: 'activo' debería
        // ser único por empresa, pero no hay constraint de BD que lo
        // garantice - sin este orden, un invariante roto haría que ->value()
        // devolviera un resultado no determinístico en vez de fallar de
        // forma predecible con el RIT más reciente.
        $textoRit = ReglamentoInterno::where('empresa_id', $solicitud->empresa_id)
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->value('texto_completo') ?? '(La empresa no tiene un Reglamento Interno de Trabajo cargado)';

        $empresa = $solicitud->empresa()->with(['actividadEconomica', 'actividadesSecundarias'])->first();

        $prompt = $this->construirPromptDetallesCargo($solicitud, $textoRit, $empresa);
        $respuesta = $this->llamarGemini($prompt, $solicitud->empresa_id);

        return $this->parsearDetallesCargo($respuesta);
    }

    private function construirPromptDetallesCargo(SolicitudContrato $solicitud, string $textoRit, $empresa): string
    {
        $nombreTrabajador = trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}") ?: 'No especificado';
        $cargo            = $solicitud->cargo_contrato ?: 'No especificado';

        // Contexto REAL y completo de la empresa - antes el prompt solo
        // tenía el RIT, y el RIT normalmente no repite datos de perfil de
        // la empresa (actividad económica, tamaño, ubicación). Bug real
        // encontrado empíricamente (caso RENBEL S.A.S.): sin estos datos,
        // la IA seguía al pie de la letra la PROHIBICIÓN ABSOLUTA de no
        // inventar, pero en vez de omitir la parte que no sabía, escribía
        // corchetes tipo "[Actividad Económica Principal de RENBEL S.A.S.]"
        // - técnicamente "no inventado", pero inutilizable en un documento
        // legal final. Se profundiza el contexto a TODO lo real disponible
        // de empresa/trabajador/contrato (pedido explícito del usuario tras
        // ver el resultado), no solo el dato puntual que causó el bug.
        $actividadPrincipal = $empresa?->actividadEconomica?->nombre;
        $actividadesSecundarias = $empresa?->actividadesSecundarias?->pluck('nombre')->filter()->implode('; ');

        $datosEmpresa = collect([
            'Nombre'               => $empresa?->nombre_completo,
            'NIT'                  => $empresa?->nit,
            'Ciudad/Departamento'  => trim(collect([$empresa?->ciudad, $empresa?->departamento])->filter()->implode(', '), ', ') ?: null,
            'Número de empleados'  => $empresa?->numero_empleados,
            'Representante legal'  => $empresa?->representante_legal,
            'Actividad económica principal'  => $actividadPrincipal,
            'Actividades económicas secundarias' => $actividadesSecundarias ?: null,
        ])->filter()->map(fn($valor, $campo) => "- {$campo}: {$valor}")->implode("\n");

        if ($datosEmpresa === '') {
            $datosEmpresa = '(Sin datos de perfil de la empresa registrados en el sistema.)';
        }

        $datosContrato = collect([
            'Trabajador'                  => $nombreTrabajador,
            'Cargo'                       => $cargo,
            'Tipo de contrato'            => $solicitud->tipo_contrato,
            'Jornada'                     => $solicitud->jornada,
            'Lugar de labores'            => $solicitud->lugar_labores,
            'Salario mensual propuesto'   => $solicitud->salario_propuesto ? ('$' . number_format((float) $solicitud->salario_propuesto, 0, ',', '.') . ' COP') : null,
            'Período de pago'             => $solicitud->periodo_pago,
            'Fecha de inicio propuesta'   => $solicitud->fecha_inicio_propuesta?->format('Y-m-d'),
        ])->filter()->map(fn($valor, $campo) => "- {$campo}: {$valor}")->implode("\n");

        return <<<PROMPT
        Eres un analista de RRHH colombiano redactando un BORRADOR (que el
        abogado revisará y editará) de 3 campos de una solicitud de contrato,
        con base ÚNICAMENTE en los datos de la empresa, del trabajador/
        contrato, y el Reglamento Interno de Trabajo (RIT) provistos abajo.

        PROHIBICIÓN ABSOLUTA: No inventes funciones, sanciones, cifras ni
        cláusulas que no se deriven razonablemente de los datos provistos o
        del RIT.

        PROHIBICIÓN ABSOLUTA: Nunca escribas placeholders ni texto entre
        corchetes (ej. "[Actividad Económica Principal de la empresa]") para
        un dato que no tengas - si un dato no está disponible en el contexto
        provisto, omite esa parte de la frase por completo o redacta de
        forma genérica sin necesitar ese dato específico. Un documento legal
        final no puede contener texto sin llenar.

        DATOS DE LA EMPRESA:
        {$datosEmpresa}

        DATOS DEL TRABAJADOR Y DEL CONTRATO:
        {$datosContrato}

        REGLAMENTO INTERNO DE TRABAJO DE LA EMPRESA:
        {$textoRit}

        Redacta los 3 campos siguientes, cada uno en HTML simple (solo
        <p>, <ul>, <li>, <strong> - sin markdown, sin asteriscos), separados
        EXACTAMENTE por los marcadores indicados (una línea con el marcador
        solo, nada más en esa línea):

        ###RESPONSABILIDADES###
        (Lista de 4-8 responsabilidades y funciones típicas del cargo
        "{$cargo}", en una lista <ul><li>, coherentes con las obligaciones
        del trabajador que ya aparecen en el RIT si aplica, y con la
        jornada/lugar de labores provistos si aplica - ej. si el lugar de
        labores es remoto, reflejarlo donde sea relevante.)

        ###OBJETO_COMERCIAL###
        (1 párrafo <p> describiendo el objeto comercial/alcance del
        contrato para este cargo dentro del giro ordinario de la empresa,
        usando el nombre real de la empresa y su actividad económica real
        provistos arriba - si algún dato de empresa no está disponible,
        redacta esa parte sin mencionarlo, de forma genérica a partir del
        cargo y el RIT.)

        ###MANUAL_FUNCIONES###
        (Descripción detallada en <ul><li> de las funciones específicas
        del puesto "{$cargo}", más extensa y concreta que las
        responsabilidades generales de arriba, coherente con el tamaño y
        la actividad real de la empresa si esos datos están disponibles.)
        PROMPT;
    }

    /** @return array{responsabilidades: string, objeto_comercial: string, manual_funciones: string} */
    private function parsearDetallesCargo(string $respuesta): array
    {
        $extraer = function (string $marcador, string $siguienteMarcadorORegexFin) use ($respuesta): string {
            if (!preg_match('/' . preg_quote($marcador, '/') . '(.*?)' . $siguienteMarcadorORegexFin . '/s', $respuesta, $m)) {
                return '';
            }
            return trim($m[1]);
        };

        return [
            'responsabilidades' => $extraer('###RESPONSABILIDADES###', '(?=###OBJETO_COMERCIAL###)'),
            'objeto_comercial'  => $extraer('###OBJETO_COMERCIAL###', '(?=###MANUAL_FUNCIONES###)'),
            'manual_funciones'  => $extraer('###MANUAL_FUNCIONES###', '$'),
        ];
    }

    /**
     * Redacta el otrosí (documento que modifica el contrato original sin
     * reemplazarlo) para una modificación contractual, anclado en el RIT
     * vigente de la empresa y en los artículos del CST relevantes al tipo de
     * cambio. NO persiste - el llamador decide (el abogado/bufete revisa el
     * borrador antes de generar el PDF final).
     */
    public function redactarOtrosi(ModificacionContractual $modificacion): string
    {
        // Prórroga de plazo: plantilla LITERAL con variables, sin IA de por
        // medio (decisión explícita del usuario) - a diferencia de los otros
        // 4 tipos, que sí redactan en texto libre.
        if ($modificacion->tipo_modificacion === 'plazo') {
            return $this->renderizarOtrosiPlazoLiteral($modificacion);
        }

        $solicitud = $modificacion->solicitudContrato;

        $temasPorTipo = [
            'salario'       => 'modificación salario remuneración contrato trabajo',
            'cargo'         => 'cambio cargo funciones contrato trabajo',
            'jornada'       => 'jornada laboral horario trabajo modalidad',
            'tipo_contrato' => 'término fijo indefinido duración contrato trabajo',
        ];

        $articulosCst = $this->ritGeneratorService->buscarArticulosPorTema(
            $temasPorTipo[$modificacion->tipo_modificacion] ?? 'contrato trabajo',
            limite: 6,
        );

        $textoRit = ReglamentoInterno::where('empresa_id', $modificacion->empresa_id)
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->value('texto_completo') ?? '(La empresa no tiene un Reglamento Interno de Trabajo cargado)';

        $prompt = $this->construirPromptOtrosi($modificacion, $solicitud, $articulosCst, $textoRit);

        return $this->llamarGemini($prompt, $modificacion->empresa_id);
    }

    /**
     * Renderiza la plantilla LITERAL del Otrosí de Plazo (sin IA), ya
     * completa como documento HTML autocontenido - generarHTMLOtrosi() la
     * usa tal cual, sin envolverla en la caja genérica de los otros 4 tipos.
     */
    private function renderizarOtrosiPlazoLiteral(ModificacionContractual $modificacion): string
    {
        $solicitud = $modificacion->solicitudContrato;
        $empresa   = $solicitud->empresa;

        $fechaContratoOriginal = $solicitud->fecha_inicio_propuesta
            ? Carbon::parse($solicitud->fecha_inicio_propuesta)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
            : 'la fecha de suscripción del contrato';

        $fechaFinAnterior = Carbon::parse($solicitud->fecha_fin_contrato);
        $fechaFinNueva    = Carbon::parse($modificacion->valor_nuevo);
        $inicioPeriodoActual = $solicitud->fecha_inicio_periodo_actual
            ? Carbon::parse($solicitud->fecha_inicio_periodo_actual)
            : Carbon::parse($solicitud->fecha_inicio_propuesta ?? $fechaFinAnterior);

        return view('pdfs.contratos.otrosi-plazo', [
            'empresa'                    => $empresa,
            'numeroOtrosi'               => $solicitud->veces_prorrogado + 1,
            'nombreEmpresa'              => $empresa?->nombre_completo ?? '',
            'nit'                        => $empresa?->nit ?? '',
            'representanteLegal'         => $empresa?->representante_legal ?? '',
            'municipioEmpresa'           => $empresa?->ciudad ?? '',
            'departamentoEmpresa'        => $empresa?->departamento ?? '',
            'nombreTrabajador'           => trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}"),
            'tipoDocumentoLabel'         => self::DOCUMENTO_LABEL[$solicitud->trabajador_documento_tipo] ?? 'documento de identidad',
            'numeroDocumento'            => $solicitud->trabajador_documento_numero,
            'fechaContratoOriginalTexto' => $fechaContratoOriginal,
            'duracionInicialTexto'       => $this->formatearDuracion($inicioPeriodoActual, $fechaFinAnterior),
            'duracionProrrogaTexto'      => $this->formatearDuracion($fechaFinAnterior->copy()->addDay(), $fechaFinNueva),
            'fechaFinAnteriorTexto'      => $fechaFinAnterior->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'fechaFinNuevaTexto'         => $fechaFinNueva->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'fechaFirma'                 => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
        ])->render();
    }

    /**
     * Expresa una duración como meses completos si calza exacto (caso
     * habitual en contratos laborales), o en días si no - nunca se inventa
     * una cifra redondeada que no sea la real.
     */
    private function formatearDuracion(Carbon $inicio, Carbon $fin): string
    {
        $meses = $inicio->diffInMonths($fin->copy()->addDay());

        if ($meses > 0 && $inicio->copy()->addMonths($meses)->subDay()->isSameDay($fin)) {
            return $meses . ' mes' . ($meses !== 1 ? 'es' : '');
        }

        $dias = $inicio->diffInDays($fin) + 1;
        return $dias . ' día' . ($dias !== 1 ? 's' : '');
    }

    private function construirPromptOtrosi(
        ModificacionContractual $modificacion,
        SolicitudContrato $solicitud,
        string $articulosCst,
        string $textoRit,
    ): string {
        $nombreTrabajador = trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}");
        $tipoLabel        = ModificacionContractual::TIPOS[$modificacion->tipo_modificacion] ?? $modificacion->tipo_modificacion;
        $valorAnterior    = $modificacion->valor_anterior ?? 'No especificado';
        $fechaEfectiva    = $modificacion->fecha_efectiva?->format('Y-m-d') ?? 'No especificada';
        $justificacion    = $modificacion->justificacion ?? 'No especificada';

        return <<<PROMPT
        Eres un abogado laboralista colombiano redactando un OTROSÍ (documento
        que modifica un contrato de trabajo existente sin reemplazarlo), con
        base ÚNICAMENTE en los datos provistos y los artículos del Código
        Sustantivo del Trabajo (CST) listados abajo.

        PROHIBICIÓN ABSOLUTA: Solo puedes citar artículos del CST que
        aparezcan en la sección "ARTÍCULOS DEL CST DISPONIBLES" de abajo. Si
        ninguno aplica exactamente, redacta sin citar número de artículo en
        vez de inventar uno.

        PROHIBICIÓN ABSOLUTA: No inventes datos que no estén explícitamente
        provistos abajo.

        DATOS DEL CONTRATO ORIGINAL:
        - Trabajador: {$nombreTrabajador}
        - Contrato: {$solicitud->codigo} ({$solicitud->tipo_contrato})

        MODIFICACIÓN A FORMALIZAR:
        - Tipo de cambio: {$tipoLabel}
        - Valor anterior: {$valorAnterior}
        - Valor nuevo: {$modificacion->valor_nuevo}
        - Fecha efectiva: {$fechaEfectiva}
        - Justificación: {$justificacion}

        REGLAMENTO INTERNO DE TRABAJO DE LA EMPRESA (contexto):
        {$textoRit}

        ARTÍCULOS DEL CST DISPONIBLES:
        {$articulosCst}

        Redacta el otrosí en HTML simple (solo <p>, <strong> - sin markdown,
        sin asteriscos), con: (1) un párrafo identificando el contrato
        original que se modifica, (2) una cláusula formalizando el cambio
        específico (valor anterior → valor nuevo), (3) un párrafo aclarando
        que el resto de las cláusulas del contrato original permanecen
        vigentes sin modificación.
        PROMPT;
    }

    /**
     * Resuelve las faltas graves/gravísimas específicas del RIT vigente de
     * la empresa, para la cláusula "FALTAS GRAVES" del contrato - pedido
     * explícito del cliente: esa cláusula no debe ser genérica cuando la
     * empresa ya tiene su propio reglamento con conductas propias.
     *
     * Reutiliza ReglamentoInternoService::generarConductasSancionables()
     * (el MISMO motor que ya corre al subir un RIT o desde el botón
     * "Generar conductas con IA" en Mi Reglamento Interno) - nunca redacta
     * ni parafrasea nada nuevo aquí, solo lee o dispara esa extracción ya
     * existente y usa su resultado tal cual.
     *
     * A propósito NO reutiliza
     * ReglamentoInternoService::conductasSancionablesDeEmpresa() (que cae
     * al catálogo genérico del CST si el RIT no tiene conductas) - acá
     * necesitamos distinguir "es del RIT real de esta empresa" de "es el
     * respaldo genérico", porque solo en el primer caso tiene sentido
     * agregar algo a la cláusula (agregar el mismo respaldo genérico que la
     * cláusula estática ya cubre sería duplicar texto sin ningún valor).
     *
     * @return array{origen: 'rit'|'sin_rit'|'sin_conductas', grave: string[], gravisima: string[]}
     */
    public function obtenerFaltasGravesRit(SolicitudContrato $solicitud): array
    {
        $rit = ReglamentoInterno::where('empresa_id', $solicitud->empresa_id)
            ->orderByDesc('activo')
            ->orderByDesc('updated_at')
            ->first();

        if (!$rit) {
            return ['origen' => 'sin_rit', 'grave' => [], 'gravisima' => []];
        }

        $conductas = $rit->conductas_sancionables;

        // Nunca se calcularon las conductas de este RIT (ej. subido antes de
        // que existiera esta función, o wizard "Construir RIT" sin pasar
        // por ese paso) - se extraen ahora mismo y se persisten en el RIT,
        // para que la próxima solicitud de esta empresa no vuelva a pagar
        // esta llamada a IA.
        if (empty($conductas)) {
            try {
                $conductas = $this->reglamentoInternoService->generarConductasSancionables($rit);
                $rit->update(['conductas_sancionables' => $conductas]);
            } catch (\Throwable $e) {
                Log::error('SolicitudContratoIAService: falló la extracción de conductas sancionables del RIT', [
                    'empresa_id' => $solicitud->empresa_id,
                    'rit_id' => $rit->id,
                    'error' => $e->getMessage(),
                ]);
                $conductas = null;
            }
        }

        if (!is_array($conductas) || (empty($conductas['grave']) && empty($conductas['gravisima']))) {
            return ['origen' => 'sin_conductas', 'grave' => [], 'gravisima' => []];
        }

        return [
            'origen'    => 'rit',
            'grave'     => array_column($conductas['grave'] ?? [], 'conducta'),
            'gravisima' => array_column($conductas['gravisima'] ?? [], 'conducta'),
        ];
    }

    /**
     * Marca de agua "BORRADOR" - se inyecta en el HTML ANTES de renderizar
     * con Dompdf, vía str_replace('</body>', ...). position: fixed es una
     * característica real de Dompdf que repite el elemento en TODAS las
     * páginas del PDF (importante: el contrato de Término Fijo ya ocupa
     * varias páginas con las 29 cláusulas reales) - verificado
     * empíricamente en las pruebas de esta tarea, no asumido.
     */
    private const MARCA_AGUA_BORRADOR = <<<HTML
    <div style="position: fixed; top: 45%; left: 0; width: 100%; text-align: center;
        transform: rotate(-35deg); font-size: 110px; font-weight: bold;
        color: rgba(200, 0, 0, 0.18); z-index: 9999;">BORRADOR</div>
    HTML;

    /**
     * $borrador = true: genera un PDF de revisión con marca de agua
     * "BORRADOR", SIN la protección real de solo-impresión (más fácil de
     * leer/anotar mientras se decide) - usado por afterCreate() al crear
     * la solicitud y por la Table Action "Regenerar Borrador".
     *
     * $borrador = false: genera el documento FINAL, sin marca de agua, con
     * la protección real de PdfProteccion - usado por la Table Action
     * "Aprobar". Dejar estado = 'aprobado' es responsabilidad de este
     * método (no del llamador), para que "Aprobar" no necesite un
     * ->update() de estado aparte.
     *
     * @return array{ruta: string, faltas_graves_origen: string}
     */
    public function generarContratoPDF(SolicitudContrato $solicitud, bool $borrador = false): array
    {
        $faltasGraves = $this->obtenerFaltasGravesRit($solicitud);

        $html = $this->generarHTML($solicitud, $faltasGraves);

        if ($borrador) {
            $html = str_replace('</body>', self::MARCA_AGUA_BORRADOR . '</body>', $html);
        }

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

        if (!$borrador) {
            // Protección real (no de interfaz): solo permiso de impresión,
            // mismo mecanismo ya usado para el RIT generado con IA - ver
            // App\Support\PdfProteccion. SOLO en el documento final - el
            // borrador se deja sin proteger para que sea fácil de revisar.
            \App\Support\PdfProteccion::proteger(
                $dompdf,
                \App\Support\PdfProteccion::ownerPassword($solicitud->empresa_id, 'contrato')
            );
        }

        file_put_contents($rutaAbsoluta, $dompdf->output());

        $solicitud->update([
            'ruta_contrato'             => $rutaRelativa,
            'fecha_generacion_contrato' => now(),
            'estado'                    => $borrador ? 'borrador' : 'aprobado',
            'fecha_cierre'              => $borrador ? null : now(),
        ]);

        // Único punto de generación real del PDF (creación automática,
        // "Regenerar Borrador" y "Aprobar" pasan los 3 por aquí) - se
        // registra acá para cubrir los 3 casos sin duplicar la llamada al
        // timeline en cada Table Action/página que dispare la generación.
        $this->timelineService->registrar(
            procesoTipo: 'contrato',
            procesoId: $solicitud->id,
            accion: 'Documento generado',
            descripcion: 'Se generó el documento: Contrato (' . ($borrador ? 'borrador' : 'final') . ')',
            metadata: [
                'tipo_documento' => $borrador ? 'Contrato (borrador)' : 'Contrato (final)',
                'nombre_archivo' => basename($rutaRelativa),
                'faltas_graves_origen' => $faltasGraves['origen'],
            ]
        );

        return ['ruta' => $rutaRelativa, 'faltas_graves_origen' => $faltasGraves['origen']];
    }

    /**
     * Vista Blade dedicada por tipo_contrato (mismo string literal que
     * DESCRIPCION_TIPO_CONTRATO arriba). Si el tipo no está acá, generarHTML()
     * cae a generarHTMLMinima() - los otros 5 tipos siguen con el documento
     * mínimo actual hasta que se retomen con sus propias plantillas reales.
     */
    private const VISTA_POR_TIPO = [
        'Contrato a Término Fijo'       => 'pdfs.contratos.termino-fijo',
        'Contrato a Término Indefinido' => 'pdfs.contratos.termino-indefinido',
        'Contrato de Obra o Labor'      => 'pdfs.contratos.obra-labor',
    ];

    private const DOCUMENTO_LABEL = [
        'CC'   => 'cédula de ciudadanía',
        'CE'   => 'cédula de extranjería',
        'TI'   => 'tarjeta de identidad',
        'PASS' => 'pasaporte',
    ];

    private const PERIODO_PAGO_FRASE = [
        'quincenal' => 'cada quince (15) días',
        'mensual'   => 'cada mes',
        'semanal'   => 'cada semana',
        'diario'    => 'cada día',
        'destajo'   => 'según la obra o labor ejecutada',
    ];

    private function generarHTML(SolicitudContrato $solicitud, array $faltasGraves): string
    {
        if ($vista = self::VISTA_POR_TIPO[$solicitud->tipo_contrato] ?? null) {
            return $this->generarHTMLDesdeVista($solicitud, $vista, $faltasGraves);
        }

        return $this->generarHTMLMinima($solicitud);
    }

    /**
     * Motor de plantillas real (hoy solo Término Fijo) - las 29 cláusulas
     * viven en la vista Blade, acá solo se preparan los datos variables.
     */
    private function generarHTMLDesdeVista(SolicitudContrato $solicitud, string $vista, array $faltasGraves): string
    {
        $empresa           = $solicitud->empresa;
        $periodoPago       = $solicitud->periodo_pago ?: 'quincenal';
        $lugarContratacion = trim(collect([$empresa?->ciudad, $empresa?->departamento])->filter()->implode(', '), ', ');
        $salario           = (float) ($solicitud->salario_propuesto ?? 0);

        return view($vista, [
            'empresa'                  => $empresa,
            'nombreEmpresa'            => $empresa?->nombre_completo ?? '',
            'nit'                      => $empresa?->nit ?? '',
            'direccionEmpresa'         => $empresa?->direccion ?? '',
            'telefonoEmpresa'          => $empresa?->telefono ?? '',
            'representanteLegal'       => $empresa?->representante_legal ?? '',
            'representanteLegalCedula' => $empresa?->representante_legal_cedula,
            'nombreTrabajador'         => trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}"),
            'tipoDocumentoLabel'       => self::DOCUMENTO_LABEL[$solicitud->trabajador_documento_tipo] ?? 'documento de identidad',
            'numeroDocumento'          => $solicitud->trabajador_documento_numero,
            'direccionTrabajador'      => $solicitud->trabajador_direccion,
            'telefonoTrabajador'       => $solicitud->trabajador_telefono,
            'emailTrabajador'          => $solicitud->trabajador_email,
            'cargo'                    => $solicitud->cargo_contrato,
            'salarioFormateado'        => number_format($salario, 0, ',', '.'),
            'salarioEnLetras'          => \App\Support\MontoEnLetras::pesos($salario),
            'periodoPagoLabel'         => mb_strtoupper($periodoPago),
            'periodoPagoFrase'         => self::PERIODO_PAGO_FRASE[$periodoPago] ?? self::PERIODO_PAGO_FRASE['quincenal'],
            'lugarLabores'             => $solicitud->lugar_labores ?: $lugarContratacion,
            'lugarContratacion'        => $lugarContratacion,
            'fechaInicio'              => $solicitud->fecha_inicio_propuesta?->format('d/m/Y') ?? 'No especificada',
            'fechaFin'                 => $solicitud->fecha_fin_contrato?->format('d/m/Y') ?? 'No especificada',
            'duracionTexto'            => ($solicitud->fecha_inicio_propuesta && $solicitud->fecha_fin_contrato)
                ? $solicitud->fecha_inicio_propuesta->diffForHumans($solicitud->fecha_fin_contrato, true)
                : 'No especificada',
            'fechaFirma'               => now()->locale('es')->translatedFormat('d \d\e F \d\e Y'),
            'objetoJuridico'           => nl2br(e(strip_tags($solicitud->objeto_juridico_redactado ?? ''))),
            'diaDescansoObligatorio'   => $empresa?->diaDescansoObligatorio() ?? 'domingo',
            'descripcionObraLabor'          => $solicitud->descripcion_obra_labor ?: 'No especificada',
            // OJO: a diferencia de $objetoJuridico (que usa
            // nl2br(e(strip_tags(...))) porque es texto simple envuelto en
            // UN <p> por la vista), este campo SÍ debe llegar como HTML
            // real - el prompt de una tarea previa le pide a la IA que
            // devuelva sus propios bloques <p class="clausula"><span class="clausula-titulo">...
            // completos. strip_tags() aquí borraría exactamente esas
            // etiquetas antes de insertarlas con {!! !!}, dejando el texto
            // sin negrita ni el espaciado del resto de cláusulas -
            // confirmado como hallazgo real de la revisión de este plan.
            'duracionTerminacionRedactada'  => $solicitud->duracion_terminacion_obra_redactada ?? '',
            'faltasGravesOrigen'       => $faltasGraves['origen'],
            'faltasGravesGrave'        => $faltasGraves['grave'],
            'faltasGravesGravisima'    => $faltasGraves['gravisima'],
        ])->render();
    }

    /** Documento mínimo actual - fallback para los tipos sin plantilla propia todavía. */
    private function generarHTMLMinima(SolicitudContrato $solicitud): string
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
     * Genera el PDF del otrosí y actualiza el campo correspondiente en la
     * SolicitudContrato original con el valor nuevo - el contrato siempre
     * refleja el estado VIGENTE; esta tabla (modificaciones_contractuales)
     * guarda el historial de cómo se llegó ahí.
     */
    public function generarOtrosiPDF(ModificacionContractual $modificacion): string
    {
        $html = $this->generarHTMLOtrosi($modificacion);

        $directorioRelativo = "solicitudes-contrato/{$modificacion->empresa_id}/otrosies";
        Storage::disk('local')->makeDirectory($directorioRelativo);

        $rutaRelativa = "{$directorioRelativo}/otrosi_{$modificacion->id}.pdf";
        $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        // Protección real (no de interfaz): solo permiso de impresión, mismo
        // mecanismo ya usado para el RIT y el contrato - ver App\Support\PdfProteccion.
        // Sal distinta a 'contrato' a propósito: contraseñas de propietario
        // independientes por tipo de documento, aunque sea la misma empresa.
        \App\Support\PdfProteccion::proteger(
            $dompdf,
            \App\Support\PdfProteccion::ownerPassword($modificacion->empresa_id, 'otrosi')
        );

        file_put_contents($rutaAbsoluta, $dompdf->output());

        $modificacion->update([
            'ruta_otrosi'             => $rutaRelativa,
            'fecha_generacion_otrosi' => now(),
            'estado'                  => 'otrosi_generado',
        ]);

        if ($modificacion->tipo_modificacion === 'plazo') {
            $this->aplicarProrrogaAlContrato($modificacion);
            return $rutaRelativa;
        }

        $campoPorTipo = [
            'salario'       => 'salario_propuesto',
            'cargo'         => 'cargo_contrato',
            'jornada'       => 'jornada',
            'tipo_contrato' => 'tipo_contrato',
        ];

        if ($campo = $campoPorTipo[$modificacion->tipo_modificacion] ?? null) {
            $modificacion->solicitudContrato->update([$campo => $modificacion->valor_nuevo]);
        }

        return $rutaRelativa;
    }

    /**
     * Aplica la prórroga aprobada al contrato: la fecha fin vigente pasa a
     * ser la acordada, el período vigente arranca donde terminaba el
     * anterior, se cuenta una prórroga más, y se reinicia el ciclo de
     * alerta/decisión para el período nuevo (ver PlazoContratoService).
     */
    private function aplicarProrrogaAlContrato(ModificacionContractual $modificacion): void
    {
        $solicitud = $modificacion->solicitudContrato;

        $solicitud->update([
            'fecha_inicio_periodo_actual'  => Carbon::parse($solicitud->fecha_fin_contrato)->addDay(),
            'fecha_fin_contrato'           => $modificacion->valor_nuevo,
            'veces_prorrogado'             => $solicitud->veces_prorrogado + 1,
            'decision_no_renovacion_en'    => null,
            'notificado_vencimiento_en'    => null,
            'requiere_revision_manual_renovacion' => false,
        ]);
    }

    /**
     * Genera el Preaviso de no renovación (plantilla literal, sin IA) y
     * marca formalmente la decisión de no renovar - la alerta de
     * vencimiento deja de mostrarse para este contrato (ver
     * PlazoContratoService::sinDecisionTomada()).
     */
    public function generarPreavisoPDF(SolicitudContrato $solicitud): string
    {
        $empresa = $solicitud->empresa;

        $fechaContratoOriginal = $solicitud->fecha_inicio_propuesta
            ? Carbon::parse($solicitud->fecha_inicio_propuesta)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
            : 'la fecha de suscripción del contrato';

        $html = view('pdfs.contratos.preaviso', [
            'empresa'                    => $empresa,
            'municipioEmpresa'           => $empresa?->ciudad ?? '',
            'departamentoEmpresa'        => $empresa?->departamento ?? '',
            'fechaCarta'                 => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'nombreTrabajador'           => trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}"),
            'tipoDocumentoLabel'         => self::DOCUMENTO_LABEL[$solicitud->trabajador_documento_tipo] ?? 'documento de identidad',
            'numeroDocumento'            => $solicitud->trabajador_documento_numero,
            'fechaContratoOriginalTexto' => $fechaContratoOriginal,
            'fechaFinContratoTexto'      => Carbon::parse($solicitud->fecha_fin_contrato)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'nombreEmpresa'              => $empresa?->nombre_completo ?? '',
            'nit'                        => $empresa?->nit ?? '',
            'representanteLegal'         => $empresa?->representante_legal ?? '',
        ])->render();

        $directorioRelativo = "solicitudes-contrato/{$solicitud->empresa_id}/preavisos";
        Storage::disk('local')->makeDirectory($directorioRelativo);

        $rutaRelativa = "{$directorioRelativo}/preaviso_{$solicitud->id}.pdf";
        $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        \App\Support\PdfProteccion::proteger(
            $dompdf,
            \App\Support\PdfProteccion::ownerPassword($solicitud->empresa_id, 'preaviso')
        );

        file_put_contents($rutaAbsoluta, $dompdf->output());

        $solicitud->update([
            'ruta_preaviso' => $rutaRelativa,
            'decision_no_renovacion_en' => now(),
        ]);

        return $rutaRelativa;
    }

    private function generarHTMLOtrosi(ModificacionContractual $modificacion): string
    {
        // Prórroga de plazo: texto_otrosi_redactado YA es un documento HTML
        // completo y autocontenido (ver renderizarOtrosiPlazoLiteral()) -
        // envolverlo en la caja genérica de abajo duplicaría el
        // encabezado/pie y rompería el formato de la plantilla literal.
        if ($modificacion->tipo_modificacion === 'plazo') {
            return $modificacion->texto_otrosi_redactado ?? '';
        }

        $solicitud        = $modificacion->solicitudContrato;
        $empresa          = $solicitud->empresa;
        $nombreEmpresa    = e($empresa?->nombre_completo ?? '');
        $nit              = e($empresa?->nit ?? '');
        $nombreTrabajador = e(trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}"));
        $tipoLabel        = e(ModificacionContractual::TIPOS[$modificacion->tipo_modificacion] ?? $modificacion->tipo_modificacion);
        $valorAnterior    = e($modificacion->valor_anterior ?? 'No especificado');
        $valorNuevo       = e($modificacion->valor_nuevo);
        $fechaEfectiva    = e($modificacion->fecha_efectiva?->format('d/m/Y') ?? 'No especificada');
        $textoOtrosi      = nl2br(e(strip_tags($modificacion->texto_otrosi_redactado ?? '')));
        $codigo           = e($solicitud->codigo);

        return <<<HTML
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.6; }
            h1 { font-size: 16px; text-align: center; }
            .datos { margin: 20px 0; }
            .datos p { margin: 4px 0; }
        </style></head>
        <body>
            <h1>OTROSÍ AL CONTRATO INDIVIDUAL DE TRABAJO {$codigo}</h1>
            <div class="datos">
                <p><strong>Empresa:</strong> {$nombreEmpresa}</p>
                <p><strong>NIT:</strong> {$nit}</p>
                <p><strong>Trabajador:</strong> {$nombreTrabajador}</p>
                <p><strong>Tipo de modificación:</strong> {$tipoLabel}</p>
                <p><strong>Valor anterior:</strong> {$valorAnterior}</p>
                <p><strong>Valor nuevo:</strong> {$valorNuevo}</p>
                <p><strong>Fecha efectiva:</strong> {$fechaEfectiva}</p>
            </div>
            <div class="objeto">{$textoOtrosi}</div>
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
                    'empresa_id' => $empresaId,
                    'model' => $model,
                    'intento' => $intento,
                    'status' => $status,
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
