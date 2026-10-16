<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Segunda socialización" pedida en la reunión con el equipo (2026-09-28):
 * un video corto y didáctico que explica el Reglamento Interno en lenguaje
 * sencillo, en vez de una presentación de diapositivas. Genera el video con
 * Gemini Omni Flash (`gemini-omni-1.1-flash`, confirmado accesible con la
 * cuenta de este proyecto - ver config('services.ia.gemini.model_video')).
 *
 * Disparo SIEMPRE manual (nunca automático) y se genera UNA SOLA VEZ por
 * Reglamento (no por trabajador) - los capítulos quedan guardados en el
 * propio registro del RIT y todos los trabajadores que lo socialicen ven
 * los mismos archivos. Costo real confirmado 2026-09-29 por el usuario en
 * https://ai.studio/spend: ~1.170 COP por clip de ~10s a 360p.
 *
 * REDISEÑO 2026-09-30 (capítulos independientes, no encadenados): la
 * primera versión encadenaba clips con "extend" (previous_interaction_id)
 * para simular un solo video largo, pero Gemini tiene un límite DURO de
 * ~30-40s totales por video extendido - error real de producción, "Videos
 * longer than 30s are not supported for extension." La solución: UN CLIP
 * INDEPENDIENTE por capítulo (sin previous_interaction_id), así que ese
 * límite no aplica nunca. El trabajador los ve como una serie con
 * reproducción automática en secuencia (ver `socializacion-rit.blade.php`).
 *
 * REDISEÑO 2026-10-14 (guion previo + profundidad + coherencia): los
 * abogados asesores reportaron que el video carecía de explicación,
 * profundidad y de una secuencia coherente que destacara lo más importante
 * del RIT. Causas encontradas en la versión anterior:
 *  1. Un clip de ~10s por tema (~24 palabras habladas) no puede explicar
 *     nada con profundidad.
 *  2. El modelo de video recibía solo "tema: resumen" y parafraseaba por su
 *     cuenta - nadie controlaba qué afirmaba ni su exactitud jurídica.
 *  3. No había guion de conjunto: ni apertura, ni hilo, ni cierre, y todos
 *     los temas pesaban igual (sanciones y debido proceso = cualquier otro).
 *  4. Sin presentador/escenario fijos, cada clip independiente podía
 *     mostrar una persona distinta.
 *
 * Ahora el flujo tiene DOS pasos:
 *  A. GUIONISTA (modelo de texto): lee el texto completo del RIT y escribe
 *     el guion de toda la serie - apertura, secciones por importancia (los
 *     temas críticos reciben varios clips: regla, ejemplo cotidiano,
 *     consecuencia), cierre - con la narración EXACTA de cada clip. Cada
 *     clip trae `cita_rit` (fragmento literal del reglamento que sustenta lo
 *     que se afirma) y PHP VERIFICA, sin IA, que esa cita exista de verdad
 *     en el texto del RIT; un clip cuya cita no aparece se descarta
 *     (anti-alucinación jurídica). El guion queda guardado en un JSON
 *     revisable por los abogados ANTES de gastar en video - ver
 *     generarGuion() y guionEnTexto().
 *  B. VIDEO (Gemini Omni): cada clip recibe la narración textual del guion
 *     ("di EXACTAMENTE estas palabras"), más la misma ficha de presentador y
 *     escenario en todos los clips. Es reanudable: si falla a mitad de
 *     camino, un reintento retoma desde el último clip generado en vez de
 *     volver a pagar los anteriores.
 */
class RitVideoDidacticoService
{
    /** Subir este valor invalida los guiones guardados y fuerza uno nuevo. */
    private const VERSION_GUION = 'v2-2026-10-14';

    private const TIPOS_CLIP = ['apertura', 'contenido', 'ejemplo', 'cierre'];

    /** Misma descripción en TODOS los clips para maximizar consistencia visual. */
    private const FICHA_PRESENTADOR = 'Presentadora de unos 35 años, cabello castaño oscuro recogido, camisa blanca y blazer azul marino, expresión cercana y serena, mirando a cámara.';

    private const FICHA_ESCENARIO = 'Oficina moderna, luminosa y sobria, fondo desenfocado en tonos neutros y azules, plano medio fijo, iluminación suave.';

    /**
     * @param array $cambios Diff de RitDiffService::compararDocumentos() (opcional) -
     *   si se pasa, el video se enfoca en "qué cambió" en vez de explicar todo el RIT.
     * @param bool $regenerarGuion true = ignora el guion guardado y escribe uno nuevo.
     */
    public function generar(ReglamentoInterno $rit, array $cambios = [], bool $regenerarGuion = false): void
    {
        $empresa = $rit->empresa;

        $guion = $this->generarGuion($rit, $cambios, $regenerarGuion);

        $logoBase64 = $this->logoBase64($empresa);

        foreach ($guion['clips'] as $i => $clip) {
            // Reanudable: este clip ya se generó (y pagó) en un intento anterior.
            if (! empty($clip['archivo']) && Storage::disk('local')->exists($clip['archivo'])) {
                continue;
            }

            $input = [];
            if ($logoBase64) {
                $input[] = ['type' => 'image', 'data' => $logoBase64['data'], 'mime_type' => $logoBase64['mime']];
            }
            $input[] = [
                'type' => 'text',
                'text' => $this->promptClip($empresa, $clip, (bool) $logoBase64),
            ];

            [, $videoBase64] = $this->crearInteraccion($input);

            $ruta = "rit-videos/{$rit->empresa_id}/video_{$rit->id}_cap" . ($i + 1) . '_' . Str::random(8) . '.mp4';
            Storage::disk('local')->put($ruta, base64_decode($videoBase64));

            // Se persiste el avance clip por clip: si el job falla en el clip 40,
            // el reintento no vuelve a pagar los 39 anteriores.
            $guion['clips'][$i]['archivo'] = $ruta;
            $this->guardarManifiesto($guion);
        }

        $capitulos = array_map(function (array $c) {
            $titulo = $c['subtitulo'] !== '' ? $c['seccion'] . ' · ' . $c['subtitulo'] : $c['seccion'];

            return ['titulo' => $titulo, 'seccion' => $c['seccion'], 'path' => $c['archivo']];
        }, $guion['clips']);

        // Pedido de Andrés Sarmiento (reunión 2026-10-05, item 3): el video
        // debe poder "invocarse/traerse" después como evidencia. Los .mp4 nunca
        // se sobreescriben (nombre único con Str::random()), pero el puntero en
        // video_didactico_capitulos sí - se archiva el vigente justo antes de
        // reemplazarlo. (2026-10-14: antes se archivaba al INICIO de la
        // generación; como ahora los reintentos son reanudables, eso habría
        // duplicado la fila de historial en cada reintento.)
        $this->archivarVideoVigenteSiExiste($rit);

        $rit->update([
            'video_didactico_path' => null,
            'video_didactico_capitulos' => $capitulos,
            'video_didactico_estado' => 'completado',
            'video_didactico_error' => null,
            'video_didactico_generado_en' => now(),
            'video_didactico_texto_hash' => hash('sha256', (string) $rit->texto_completo),
        ]);
    }

    /**
     * Paso A: escribe (o recupera) el guion verificado de la serie. Es público
     * a propósito: permite que los abogados lo revisen ANTES de gastar en video.
     *
     *   $guion = app(RitVideoDidacticoService::class)->generarGuion($rit);
     *   echo app(RitVideoDidacticoService::class)->guionEnTexto($guion);
     *
     * El guion se guarda en el disco local ("rit-videos/{empresa}/guion_*.json")
     * y se reutiliza mientras no cambie el texto del RIT, el diff ni
     * VERSION_GUION.
     *
     * @return array{version: string, rit_id: int, titulo_serie: string, creado_en: string, manifiesto: string, clips_rechazados: array, clips: array}
     */
    public function generarGuion(ReglamentoInterno $rit, array $cambios = [], bool $regenerar = false): array
    {
        $cambiosReales = array_values(array_filter($cambios, fn ($c) => $c['tipo'] !== 'igual'));
        $texto = trim((string) $rit->texto_completo);

        if (mb_strlen($texto) < 500) {
            throw new \RuntimeException('El Reglamento no tiene texto suficiente para construir el guion del video.');
        }

        $clave = substr(hash('sha256', json_encode([$rit->id, hash('sha256', $texto), $cambiosReales, self::VERSION_GUION])), 0, 16);
        $ruta = "rit-videos/{$rit->empresa_id}/guion_{$rit->id}_{$clave}.json";

        if (! $regenerar && Storage::disk('local')->exists($ruta)) {
            $existente = json_decode((string) Storage::disk('local')->get($ruta), true);
            if (is_array($existente) && ! empty($existente['clips'])) {
                return $existente;
            }
        }

        $cambiosDescritos = array_map(fn (array $c) => $this->describirCambio($c), $cambiosReales);

        // Universo contra el que se verifican las citas: el RIT completo y, en
        // modo "cambios", también el texto anterior de lo modificado/eliminado
        // (que ya no está en el RIT nuevo pero sí es citable al explicar el cambio).
        $corpus = $this->normalizar(
            $texto . ' ' . collect($cambiosDescritos)->map(fn ($c) => $c['antes'] . ' ' . $c['despues'])->implode(' ')
        );

        $tope = (int) config('services.ia.gemini.video_max_clips', 60);
        $modoCambios = ! empty($cambiosDescritos);
        $maxClips = $modoCambios ? min($tope, 2 + 2 * count($cambiosDescritos)) : $tope;
        // Piso de calidad (no de cobertura): exige que haya apertura + algo
        // de contenido real + cierre, pero sin bloquear por completo la
        // generación para una empresa con un RIT legítimamente corto/sencillo
        // - la versión original de este rediseño exigía 14 como mínimo fijo
        // en modo "RIT completo", lo cual habría hecho IMPOSIBLE generar el
        // video para cualquier RIT con pocos temas citables (una regresión
        // real: la versión anterior de este servicio, 1 capítulo por tema sin
        // tope, sí generaba video para esos casos). 6 sigue siendo mucho más
        // profundo que el capítulo único de antes, sin excluir a nadie.
        $minClips = $modoCambios ? 3 : 6;

        $mejor = null;
        $retroalimentacion = '';

        for ($intento = 1; $intento <= 2; $intento++) {
            $prompt = $this->promptGuion($rit, $texto, $cambiosDescritos, $retroalimentacion, $minClips, $maxClips);
            $crudo = $this->decodificarJson($this->llamarTextoGemini($prompt));

            $clipsCrudos = is_array($crudo['clips'] ?? null) ? $crudo['clips'] : [];
            [$clips, $rechazados] = $this->validarClips($clipsCrudos, $corpus);
            $total = count($clips) + count($rechazados);

            if ($mejor === null || count($clips) > count($mejor['clips'])) {
                $mejor = ['titulo' => (string) ($crudo['titulo_serie'] ?? ''), 'clips' => $clips, 'rechazados' => $rechazados];
            }

            if ($total > 0 && count($rechazados) / $total <= 0.25) {
                break;
            }

            $citasFallidas = collect($rechazados)->pluck('cita')->filter()->take(10)->map(fn ($c) => '- ' . Str::limit($c, 160))->implode("\n");
            $retroalimentacion = "CORRECCIÓN OBLIGATORIA: en tu intento anterior, varias citas NO se encontraron literalmente en el texto del RIT. "
                . "Copia cada cita LETRA POR LETRA del texto, sin cambiar ni una palabra, sin puntos suspensivos y sin unir fragmentos distantes. "
                . "Citas que fallaron:\n{$citasFallidas}\n";
        }

        $clips = $this->aplicarTope($mejor['clips'], $maxClips);

        if (count($clips) < $minClips) {
            throw new \RuntimeException(
                'El guion quedó con solo ' . count($clips) . ' clips verificados contra el texto del RIT (mínimo ' . $minClips . '). '
                . 'Revise que el texto del Reglamento se haya extraído bien o regenere el guion.'
            );
        }

        $guion = [
            'version' => self::VERSION_GUION,
            'rit_id' => $rit->id,
            'titulo_serie' => $mejor['titulo'],
            'creado_en' => now()->toDateTimeString(),
            'manifiesto' => $ruta,
            'clips_rechazados' => $mejor['rechazados'],
            'clips' => $clips,
        ];

        $this->guardarManifiesto($guion);

        Log::info('RitVideoDidacticoService: guion generado', [
            'rit_id' => $rit->id,
            'clips' => count($clips),
            'rechazados_por_cita_no_verificada' => count($mejor['rechazados']),
            'modo' => $modoCambios ? 'cambios' : 'completo',
        ]);

        return $guion;
    }

    /** Guion en texto plano, pensado para que lo lean y aprueben los abogados. */
    public function guionEnTexto(array $guion): string
    {
        $lineas = [];

        foreach ($guion['clips'] as $i => $c) {
            $lineas[] = sprintf('[%02d] %s · %s (%s, importancia %d)', $i + 1, $c['seccion'], $c['subtitulo'], $c['tipo'], $c['importancia']);
            $lineas[] = '     Narración: ' . $c['narracion'];
            if (! empty($c['cita_rit'])) {
                $ref = ! empty($c['referencia']) ? " ({$c['referencia']})" : '';
                $lineas[] = '     Fuente en el RIT' . $ref . ': "' . $c['cita_rit'] . '"';
            }
            $lineas[] = '';
        }

        return implode("\n", $lineas);
    }

    /**
     * Prompt del guionista. Es el que decide qué se explica, con qué
     * profundidad y en qué orden - ahí está la diferencia con la versión anterior.
     */
    private function promptGuion(
        ReglamentoInterno $rit,
        string $texto,
        array $cambiosDescritos,
        string $retroalimentacion,
        int $minClips,
        int $maxClips
    ): string {
        $nombreEmpresa = $rit->empresa?->nombre_completo ?? 'la empresa';
        $objetivo = (int) config('services.ia.gemini.video_palabras_clip', 24);
        $minPalabras = max(10, $objetivo - 6);
        $maxPalabras = $objetivo + 4;
        $textoRit = mb_substr($texto, 0, 500000);

        if (empty($cambiosDescritos)) {
            $indice = $this->indiceTematico($rit);
            $bloqueIndice = $indice !== '' ? "ÍNDICE TEMÁTICO YA CLASIFICADO DE ESTE RIT (úsalo solo como guía de qué hay; la verdad es el texto completo):\n{$indice}\n" : '';

            $bloqueModo = <<<TXT
MODO: EXPLICACIÓN COMPLETA DEL REGLAMENTO.
Estructura de la serie (respeta este orden e incluye SOLO las secciones que el RIT realmente cubre):
1. APERTURA (1 o 2 clips): qué es el reglamento, para qué sirve y a quién aplica, y un "mapa" de lo que se va a ver. Sin afirmaciones jurídicas específicas.
2. OBLIGACIONES Y PROHIBICIONES del trabajador. [importancia 3]
3. DERECHOS Y GARANTÍAS del trabajador que reconozca el RIT. [importancia 2 o 3]
4. LO DEL DÍA A DÍA: jornada, horarios, descansos, permisos, licencias, pagos. [importancia 2]
5. CONVIVENCIA, ACOSO LABORAL y mecanismos de queja, SEGURIDAD Y SALUD en el trabajo, si el RIT los regula. [importancia 2]
6. FALTAS Y SANCIONES [importancia 3]: cómo clasifica el RIT las faltas (leves, graves...), qué sanciones existen y su escala exacta, qué pasa en la reincidencia, y qué conductas concretas menciona.
7. PROCEDIMIENTO DISCIPLINARIO Y DERECHO DE DEFENSA [importancia 3]: paso a paso, tal como lo describe el RIT (citación, descargos, derecho a ser escuchado, acompañante si lo prevé, decisión, posibilidad de impugnar, plazos).
8. OTROS TEMAS del RIT que sean relevantes para el trabajador (importancia 1 o 2).
9. CIERRE (1 o 2 clips): recapitulación de lo esencial y recordatorio de que puede consultar el reglamento completo y acudir a la persona o área que su empresa designe para resolver dudas. Sin afirmaciones jurídicas nuevas.
{$bloqueIndice}
TXT;
        } else {
            $listaCambios = json_encode(
                array_map(fn (array $c, int $i) => [
                    'n' => $i + 1,
                    'tipo' => $c['tipo'],
                    'antes' => Str::limit($c['antes'], 1200, '…'),
                    'despues' => Str::limit($c['despues'], 1200, '…'),
                ], $cambiosDescritos, array_keys($cambiosDescritos)),
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );

            $bloqueModo = <<<TXT
MODO: ACTUALIZACIÓN DEL REGLAMENTO. El reglamento fue modificado; el video explica SOLO qué cambió y qué significa para el trabajador.
Estructura: APERTURA (1 clip: el reglamento se actualizó y qué se verá) -> por cada cambio relevante, un clip de "qué decía antes y qué dice ahora" y, si su importancia es 2 o 3, un clip adicional con el significado práctico o un ejemplo cotidiano -> CIERRE (1 clip: recapitulación).
Ignora los cambios triviales (ortografía, formato, numeración). Ordena los cambios de MAYOR a MENOR impacto para el trabajador (sanciones, derechos, procedimientos primero).
Para citar un cambio, la cita_rit puede salir del texto "despues" o, si se eliminó algo, del texto "antes" de la lista de cambios.
LISTA DE CAMBIOS:
{$listaCambios}
TXT;
        }

        return <<<PROMPT
Eres, a la vez, un abogado laboral colombiano experto en reglamentos internos de trabajo y un guionista de contenidos educativos. Vas a escribir el GUION de una serie de clips de video cortos con los que la empresa "{$nombreEmpresa}" le explicará a sus trabajadores su Reglamento Interno de Trabajo (RIT). Quien lo ve no es abogado: el guion debe ser claro, cercano y concreto, pero JURÍDICAMENTE EXACTO. Un error en este guion es un riesgo legal para la empresa: ante la duda, omite en vez de inventar.

OBJETIVO
Que al terminar la serie el trabajador entienda con claridad qué le exige el reglamento, qué le garantiza, qué conductas son faltas y qué consecuencias tienen, y cómo es el procedimiento si alguna vez se le cita a dar su versión (descargos).

FUENTE ÚNICA DE VERDAD
Solo puedes afirmar lo que dice el texto del RIT incluido al final. Está PROHIBIDO: inventar artículos, plazos, porcentajes, sanciones, procedimientos o derechos que no estén en ese texto; citar leyes o sentencias por tu cuenta; usar generalizaciones como "normalmente" o "en general las empresas". Si el RIT no regula un tema, no lo menciones. Conserva los matices exactos: si dice "hasta ocho días", di "hasta ocho días", nunca "ocho días"; si dice "podrá", no lo conviertas en "deberá".

{$bloqueModo}

PROFUNDIDAD (esto es lo que más se reclamó de la versión anterior)
Cada clip dura unos 10 segundos, así que la profundidad se logra con VARIOS clips consecutivos por tema, nunca metiendo más palabras en uno.
- Tema de importancia 3: entre 3 y 4 clips en secuencia: (a) qué dice exactamente la regla; (b) cómo se ve en la vida diaria, con un ejemplo cotidiano; (c) qué consecuencia tiene o qué debe hacer el trabajador; (d) si aplica, el detalle que suele confundirse.
- Tema de importancia 2: 2 clips (la regla + un ejemplo o su consecuencia).
- Tema de importancia 1: 1 clip.
Los ejemplos son hipotéticos, sin nombres propios, se presentan como "Por ejemplo, ..." y deben ser coherentes con la regla citada: nunca crean una regla nueva.

COHERENCIA
- Los clips forman UNA sola historia. El primer clip anuncia el mapa de la serie; el último recapitula lo esencial.
- Cada clip enlaza con el anterior con una frase puente muy corta ("Ahora veamos...", "Ya vimos que...") y los clips de una misma sección no repiten información.
- Las secciones de importancia 3 van con más clips y en el orden en que el trabajador las necesita, no en el orden del documento.
- Usa exactamente el mismo texto en "seccion" para todos los clips de una misma sección.

FORMATO DE CADA CLIP
- tipo: "apertura", "contenido", "ejemplo" o "cierre".
- importancia: 1, 2 o 3 (3 = lo que todo trabajador debe saber sí o sí).
- narracion: lo que dirá el presentador, de {$minPalabras} a {$maxPalabras} palabras (objetivo: {$objetivo}). Escrita para el oído: frases cortas, sin paréntesis, números escritos con letras, trato de "usted", español neutro colombiano. No menciones números de artículo salvo que sean útiles y aparezcan en la cita.
- escena: una frase de máximo 15 palabras sobre qué se ve (el presentador siempre es quien habla; puede alternar con una imagen de apoyo de un entorno laboral genérico). NUNCA texto en pantalla.
- cita_rit: fragmento copiado LETRA POR LETRA del texto del RIT (de 8 a 30 palabras), sin cambiar nada, sin puntos suspensivos y sin unir fragmentos distantes, que sustente lo que afirma la narración. Para "apertura" y "cierre" déjalo como "".
- referencia: número o nombre del artículo/capítulo SOLO si aparece en el texto citado o justo antes de él; si no, null.

TOTAL: entre {$minClips} y {$maxClips} clips. Si el RIT es corto o cubre pocos temas, usa menos; nunca rellenes.

{$retroalimentacion}
SALIDA - responde ÚNICAMENTE este JSON, sin texto antes ni después:
{
  "titulo_serie": "",
  "clips": [
    {"seccion": "", "subtitulo": "", "tipo": "contenido", "importancia": 3, "narracion": "", "escena": "", "cita_rit": "", "referencia": null}
  ]
}

TEXTO COMPLETO DEL RIT:
{$textoRit}
PROMPT;
    }

    /**
     * Prompt del clip de video: la narración llega YA escrita y verificada,
     * el modelo de video solo debe decirla - ya no improvisa contenido.
     *
     * El recordatorio de logo y de no mostrar texto en pantalla se repite en
     * TODOS los clips porque cada uno es una petición nueva a Gemini. (Ver
     * nota histórica 2026-09-30: en producción algunos capítulos mostraron
     * subtítulos deformados y el logo no siempre se mantuvo idéntico. Gemini
     * no garantiza obediencia perfecta a ninguna instrucción, por eso se
     * repite la prohibición de varias formas.)
     */
    private function promptClip(?Empresa $empresa, array $clip, bool $conLogo): string
    {
        $nombre = $empresa?->nombre_completo;
        $presentador = config('services.ia.gemini.video_presentador', self::FICHA_PRESENTADOR);
        $escenario = config('services.ia.gemini.video_escenario', self::FICHA_ESCENARIO);
        $escena = $clip['escena'] !== '' ? $clip['escena'] : 'El presentador habla directamente a cámara.';

        $prompt = $clip['tipo'] === 'apertura'
            ? "Video didáctico y profesional en español que abre una serie de capítulos cortos sobre el Reglamento Interno de Trabajo de la empresa \"{$nombre}\", dirigida a sus trabajadores colombianos. "
            : "Video didáctico y profesional en español, un capítulo corto de una serie que explica el Reglamento Interno de Trabajo de la empresa \"{$nombre}\" a sus trabajadores colombianos. ";

        $prompt .= "PRESENTADOR (la misma persona, con exactamente el mismo aspecto, en todos los capítulos de la serie): {$presentador} "
            . "ESCENARIO (el mismo en todos los capítulos): {$escenario} "
            . "ESCENA DE ESTE CAPÍTULO: {$escena} El presentador es siempre quien habla; puede alternar con imágenes de apoyo de un entorno laboral genérico y neutro. "
            . "NARRACIÓN: el presentador debe decir EXACTAMENTE las siguientes palabras, sin agregar, quitar ni cambiar ninguna, con ritmo pausado y natural, y terminando la última frase completa antes de que acabe el clip: \"{$clip['narracion']}\" "
            . "No agregues ninguna información que no esté en esa narración. "
            . "PROHIBIDO POR COMPLETO: no muestres NINGÚN texto, palabra, letra, número, viñeta, subtítulo, caption ni rótulo escrito en pantalla en NINGÚN momento del video, bajo ninguna circunstancia - ni siquiera fragmentos, ni siquiera borrosos. "
            . "El video debe ser 100% imagen, SOLO narración hablada, cero texto en pantalla. "
            . "Narración en español neutro colombiano. No incluya música con derechos de autor reconocibles.";

        if ($conLogo) {
            $prompt = "El video muestra el logo de la empresa <IMAGE_REF_0> en una esquina fija durante todo el clip, EXACTAMENTE igual a la imagen de referencia - mismo tamaño, misma posición, mismos colores, sin ninguna variación, distorsión ni animación del logo. " . $prompt;
        }

        return $prompt;
    }

    /**
     * Validación determinística (sin IA) del guion: estructura válida y, para
     * todo clip que afirme algo, que su cita_rit exista LITERALMENTE en el
     * texto del RIT (comparación normalizada: sin tildes, mayúsculas ni
     * puntuación). Un clip cuya cita no aparece se descarta.
     *
     * @return array{0: array, 1: array} [clips válidos, clips rechazados]
     */
    private function validarClips(array $clips, string $corpus): array
    {
        $validos = [];
        $rechazados = [];
        $umbralAviso = (int) config('services.ia.gemini.video_palabras_clip', 24) + 8;
        $corpusPad = ' ' . $corpus . ' ';

        foreach ($clips as $c) {
            if (! is_array($c)) {
                continue;
            }

            $tipo = (string) ($c['tipo'] ?? '');
            $narracion = trim((string) ($c['narracion'] ?? ''));

            if (! in_array($tipo, self::TIPOS_CLIP, true) || $narracion === '') {
                $rechazados[] = ['motivo' => 'estructura inválida', 'narracion' => $narracion, 'cita' => ''];
                continue;
            }

            $cita = trim((string) ($c['cita_rit'] ?? ''));

            if (! in_array($tipo, ['apertura', 'cierre'], true)) {
                $citaNorm = $this->normalizar($cita);
                $palabrasCita = count(preg_split('/\s+/u', $citaNorm, -1, PREG_SPLIT_NO_EMPTY));

                if ($palabrasCita < 5 || ! str_contains($corpusPad, ' ' . $citaNorm . ' ')) {
                    $rechazados[] = ['motivo' => 'cita no encontrada literalmente en el RIT', 'narracion' => $narracion, 'cita' => $cita];
                    continue;
                }
            }

            $palabras = count(preg_split('/\s+/u', $narracion, -1, PREG_SPLIT_NO_EMPTY));
            if ($palabras > $umbralAviso) {
                Log::warning('RitVideoDidacticoService: narración más larga de lo que cabe en un clip', [
                    'palabras' => $palabras,
                    'narracion' => Str::limit($narracion, 120),
                ]);
            }

            $seccion = trim((string) ($c['seccion'] ?? ''));

            $validos[] = [
                'seccion' => $seccion !== '' ? $seccion : 'Reglamento Interno de Trabajo',
                'subtitulo' => trim((string) ($c['subtitulo'] ?? '')),
                'tipo' => $tipo,
                'importancia' => max(1, min(3, (int) ($c['importancia'] ?? 1))),
                'narracion' => $narracion,
                'escena' => trim((string) ($c['escena'] ?? '')),
                'cita_rit' => $cita,
                'referencia' => isset($c['referencia']) && $c['referencia'] !== '' ? (string) $c['referencia'] : null,
                'palabras' => $palabras,
                'archivo' => null,
            ];
        }

        return [$validos, $rechazados];
    }

    /**
     * Si el guion excede el tope de clips (control de costo), se eliminan
     * primero los de menor importancia y, a igual importancia, los más
     * tardíos. Nunca se tocan la apertura ni el cierre.
     */
    private function aplicarTope(array $clips, int $max): array
    {
        if (count($clips) <= $max) {
            return $clips;
        }

        $candidatos = array_values(array_filter(
            array_keys($clips),
            fn ($i) => ! in_array($clips[$i]['tipo'], ['apertura', 'cierre'], true)
        ));

        usort($candidatos, fn ($a, $b) => [$clips[$a]['importancia'], -$a] <=> [$clips[$b]['importancia'], -$b]);

        foreach (array_slice($candidatos, 0, count($clips) - $max) as $i) {
            unset($clips[$i]);
        }

        return array_values($clips);
    }

    /** @return array{tipo: string, antes: string, despues: string} */
    private function describirCambio(array $c): array
    {
        if ($c['tipo'] === 'agregado') {
            return ['tipo' => 'agregado', 'antes' => '', 'despues' => trim((string) ($c['texto'] ?? ''))];
        }
        if ($c['tipo'] === 'eliminado') {
            return ['tipo' => 'eliminado', 'antes' => trim((string) ($c['texto'] ?? '')), 'despues' => ''];
        }

        // "modificado": trae un diff de PALABRAS - se reconstruye el texto
        // anterior (igual + eliminado) y el nuevo (igual + agregado).
        $palabras = collect($c['palabras'] ?? []);

        return [
            'tipo' => 'modificado',
            'antes' => trim($palabras->whereIn('tipo', ['igual', 'eliminado'])->pluck('texto')->implode('')),
            'despues' => trim($palabras->whereIn('tipo', ['igual', 'agregado'])->pluck('texto')->implode('')),
        ];
    }

    /** Lista "Tema: resumen" de los temas ya clasificados del RIT (guía opcional para el guionista). */
    private function indiceTematico(ReglamentoInterno $rit): string
    {
        try {
            return $rit->temasNormativos()
                ->activos()
                ->get(['temas_normativos.id', 'temas_normativos.nombre', 'temas_normativos.descripcion'])
                ->map(fn ($tema) => '- ' . $tema->nombre . ': ' . Str::limit((string) ($tema->pivot->resumen_simple ?: $tema->descripcion), 220))
                ->implode("\n");
        } catch (\Throwable $e) {
            Log::warning('RitVideoDidacticoService: no se pudo cargar el índice temático', ['rit_id' => $rit->id, 'error' => $e->getMessage()]);

            return '';
        }
    }

    private function normalizar(string $texto): string
    {
        return trim((string) Str::of($texto)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' '));
    }

    private function guardarManifiesto(array $guion): void
    {
        Storage::disk('local')->put(
            $guion['manifiesto'],
            json_encode($guion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    private function decodificarJson(string $texto): array
    {
        $limpio = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($texto)));
        $data = json_decode($limpio, true);

        if (! is_array($data)) {
            $ini = strpos($limpio, '{');
            $fin = strrpos($limpio, '}');
            if ($ini !== false && $fin !== false && $fin > $ini) {
                $data = json_decode(substr($limpio, $ini, $fin - $ini + 1), true);
            }
        }

        if (! is_array($data)) {
            throw new \RuntimeException('La IA no devolvió un guion en JSON válido: ' . Str::limit($texto, 300));
        }

        return $data;
    }

    /** Llamada de TEXTO a Gemini (el guionista). Opcional: config('services.ia.gemini.model_guion'). */
    private function llamarTextoGemini(string $prompt): string
    {
        $apiKey = config('services.ia.gemini.api_key');
        $modelo = config('services.ia.gemini.model_guion') ?: (config('services.ia.gemini.model') ?: 'gemini-2.5-flash');

        $response = Http::timeout(240)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}",
            [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 40000,
                    'responseMimeType' => 'application/json',
                ],
            ]
        );

        if (! $response->successful()) {
            throw new \RuntimeException('Error generando el guion del video con Gemini: ' . $response->body());
        }

        $data = $response->json();

        if (($data['candidates'][0]['finishReason'] ?? null) === 'MAX_TOKENS') {
            throw new \RuntimeException('El guion se truncó por límite de tokens: reduzca services.ia.gemini.video_max_clips.');
        }

        $texto = '';
        foreach (($data['candidates'][0]['content']['parts'] ?? []) as $parte) {
            if (isset($parte['text']) && empty($parte['thought'])) {
                $texto .= $parte['text'];
            }
        }

        return $texto;
    }

    /**
     * Archiva el video vigente (si existe) antes de que se reemplace -
     * ver HistoricoVideoDidacticoRit. No falla el job si algo sale mal aquí
     * (fail-open): perder el archivado no debe bloquear la generación del
     * video nuevo.
     */
    private function archivarVideoVigenteSiExiste(ReglamentoInterno $rit): void
    {
        $capitulosVigentes = $rit->capitulosVideoDidactico();
        if (empty($capitulosVigentes)) {
            return;
        }

        try {
            \App\Models\HistoricoVideoDidacticoRit::create([
                'reglamento_interno_id' => $rit->id,
                'empresa_id' => $rit->empresa_id,
                'capitulos' => $capitulosVigentes,
                'texto_rit_hash' => $rit->video_didactico_texto_hash,
                'generado_en' => $rit->video_didactico_generado_en,
                'archivado_en' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('RitVideoDidacticoService: no se pudo archivar el video anterior', [
                'rit_id' => $rit->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array{data: string, mime: string}|null */
    private function logoBase64(?Empresa $empresa): ?array
    {
        if (! $empresa?->logo_path) {
            return null;
        }

        $rutaAbsoluta = Storage::disk('local')->path($empresa->logo_path);
        if (! is_file($rutaAbsoluta)) {
            return null;
        }

        return [
            'data' => base64_encode(file_get_contents($rutaAbsoluta)),
            'mime' => mime_content_type($rutaAbsoluta) ?: 'image/png',
        ];
    }

    /** @return array{0: string, 1: string} [interaction_id, video_base64] */
    private function crearInteraccion(array $input): array
    {
        return $this->llamarGemini(['input' => $input]);
    }

    /** @return array{0: string, 1: string} [interaction_id, video_base64] */
    private function llamarGemini(array $camposExtra): array
    {
        $apiKey = config('services.ia.gemini.api_key');
        $modelo = config('services.ia.gemini.model_video');

        $payload = array_merge([
            'model' => $modelo,
            'response_format' => [
                'type' => 'video',
                'resolution' => config('services.ia.gemini.video_resolution'),
            ],
        ], $camposExtra);

        // 180s no alcanzó en producción (cURL error 28, confirmado
        // 2026-09-29 con el log real: "Operation timed out after 180002
        // milliseconds") - la generación de video real tarda más que eso.
        // Corre dentro de un job en cola, no de una petición web, así que
        // un timeout generoso no bloquea a ningún usuario esperando.
        $response = Http::timeout(300)->post(
            "https://generativelanguage.googleapis.com/v1beta/interactions?key={$apiKey}",
            $payload
        );

        if (! $response->successful()) {
            throw new \RuntimeException('Error generando el video didáctico con Gemini: ' . $response->body());
        }

        $data = $response->json();
        $videoBase64 = $this->extraerVideoBase64($data);

        if (! $videoBase64) {
            throw new \RuntimeException('Gemini respondió sin un video en el resultado: ' . $response->body());
        }

        $interactionId = $data['id'] ?? null;
        if (! $interactionId) {
            throw new \RuntimeException('Gemini respondió sin un id de interacción: ' . $response->body());
        }

        return [$interactionId, $videoBase64];
    }

    /**
     * Extrae el video en base64 de la estructura cruda de /v1beta/interactions -
     * el campo de conveniencia `output_video` solo existe en los SDKs, la REST
     * cruda trae el resultado dentro de `steps[].content[]` (ver docs pegadas
     * por el usuario, 2026-09-28).
     */
    private function extraerVideoBase64(array $respuesta): ?string
    {
        foreach (($respuesta['steps'] ?? []) as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }
            foreach (($step['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'video' && ! empty($content['data'])) {
                    return $content['data'];
                }
            }
        }

        return null;
    }
}
