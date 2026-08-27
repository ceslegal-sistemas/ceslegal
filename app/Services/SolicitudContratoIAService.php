<?php

namespace App\Services;

use App\Models\ModificacionContractual;
use App\Models\ReglamentoInterno;
use App\Models\SolicitudContrato;
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
        $descripcionObra = trim((string) $solicitud->descripcion_obra_labor);
        $fechaInicio      = $solicitud->fecha_inicio_propuesta?->format('Y-m-d') ?? 'No especificada';

        return <<<PROMPT
        Eres un abogado colombiano redactando las cláusulas de DURACIÓN y
        TERMINACIÓN de un contrato de trabajo POR OBRA O LABOR DETERMINADA,
        con base ÚNICAMENTE en los datos provistos abajo.

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
            Eres un abogado colombiano redactando el OBJETO JURÍDICO de un
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
        $textoRit = ReglamentoInterno::where('empresa_id', $solicitud->empresa_id)
            ->where('activo', true)
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

        // Dato real de la empresa (Empresa.actividad_economica_id +
        // actividadesSecundarias) - sin esto, el prompt solo tenía el RIT
        // como contexto, y el RIT normalmente no repite el detalle de la
        // actividad económica registrada. Bug real encontrado empíricamente:
        // sin este dato, la IA seguía al pie de la letra la PROHIBICIÓN
        // ABSOLUTA de no inventar, pero en vez de omitir la parte que no
        // sabía, escribía corchetes tipo "[Actividad Económica Principal de
        // {empresa}]" - técnicamente "no inventado", pero un documento legal
        // final no puede tener placeholders sin llenar.
        $actividadPrincipal = $empresa?->actividadEconomica?->nombre;
        $actividadesSecundarias = $empresa?->actividadesSecundarias?->pluck('nombre')->filter()->implode('; ');

        $actividadEconomicaTexto = $actividadPrincipal
            ? "Actividad económica principal: {$actividadPrincipal}."
                . ($actividadesSecundarias ? " Actividades secundarias: {$actividadesSecundarias}." : ' Sin actividades secundarias registradas.')
            : '(La empresa no tiene actividad económica registrada en el sistema - NO menciones actividad económica principal/secundaria en el objeto comercial, describe el objeto del contrato de forma genérica a partir del cargo y el RIT solamente.)';

        return <<<PROMPT
        Eres un analista de RRHH colombiano redactando un BORRADOR (que el
        abogado revisará y editará) de 3 campos de una solicitud de contrato,
        con base ÚNICAMENTE en el cargo, la actividad económica de la
        empresa y el Reglamento Interno de Trabajo (RIT) provistos abajo.

        PROHIBICIÓN ABSOLUTA: No inventes funciones, sanciones ni cláusulas
        que no se deriven razonablemente del cargo o del RIT provisto.

        PROHIBICIÓN ABSOLUTA: Nunca escribas placeholders ni texto entre
        corchetes (ej. "[Actividad Económica Principal de la empresa]") para
        un dato que no tengas - si un dato no está disponible en el contexto
        provisto, omite esa parte de la frase por completo o redacta de
        forma genérica sin necesitar ese dato específico. Un documento legal
        final no puede contener texto sin llenar.

        CARGO: {$cargo}
        TRABAJADOR: {$nombreTrabajador}
        TIPO DE CONTRATO: {$solicitud->tipo_contrato}

        ACTIVIDAD ECONÓMICA DE LA EMPRESA:
        {$actividadEconomicaTexto}

        REGLAMENTO INTERNO DE TRABAJO DE LA EMPRESA:
        {$textoRit}

        Redacta los 3 campos siguientes, cada uno en HTML simple (solo
        <p>, <ul>, <li>, <strong> - sin markdown, sin asteriscos), separados
        EXACTAMENTE por los marcadores indicados (una línea con el marcador
        solo, nada más en esa línea):

        ###RESPONSABILIDADES###
        (Lista de 4-8 responsabilidades y funciones típicas del cargo
        "{$cargo}", en una lista <ul><li>, coherentes con las obligaciones
        del trabajador que ya aparecen en el RIT si aplica.)

        ###OBJETO_COMERCIAL###
        (1 párrafo <p> describiendo el objeto comercial/alcance del
        contrato para este cargo dentro del giro ordinario de la empresa,
        usando la actividad económica real provista arriba - si no hay
        actividad económica registrada, redacta este párrafo sin
        mencionarla, de forma genérica a partir del cargo y el RIT.)

        ###MANUAL_FUNCIONES###
        (Descripción detallada en <ul><li> de las funciones específicas
        del puesto "{$cargo}", más extensa y concreta que las
        responsabilidades generales de arriba.)
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
            ->value('texto_completo') ?? '(La empresa no tiene un Reglamento Interno de Trabajo cargado)';

        $prompt = $this->construirPromptOtrosi($modificacion, $solicitud, $articulosCst, $textoRit);

        return $this->llamarGemini($prompt, $modificacion->empresa_id);
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
     */
    public function generarContratoPDF(SolicitudContrato $solicitud, bool $borrador = false): string
    {
        $html = $this->generarHTML($solicitud);

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

        return $rutaRelativa;
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

    private function generarHTML(SolicitudContrato $solicitud): string
    {
        if ($vista = self::VISTA_POR_TIPO[$solicitud->tipo_contrato] ?? null) {
            return $this->generarHTMLDesdeVista($solicitud, $vista);
        }

        return $this->generarHTMLMinima($solicitud);
    }

    /**
     * Motor de plantillas real (hoy solo Término Fijo) - las 29 cláusulas
     * viven en la vista Blade, acá solo se preparan los datos variables.
     */
    private function generarHTMLDesdeVista(SolicitudContrato $solicitud, string $vista): string
    {
        $empresa           = $solicitud->empresa;
        $periodoPago       = $solicitud->periodo_pago ?: 'quincenal';
        $lugarContratacion = trim(collect([$empresa?->ciudad, $empresa?->departamento])->filter()->implode(', '), ', ');
        $salario           = (float) ($solicitud->salario_propuesto ?? 0);

        return view($vista, [
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

    private function generarHTMLOtrosi(ModificacionContractual $modificacion): string
    {
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
