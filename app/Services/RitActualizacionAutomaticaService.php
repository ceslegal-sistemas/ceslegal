<?php

namespace App\Services;

use App\Models\DocumentoLegal;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Plan B de la actualización automática del RIT: dado un RIT que la
 * taxonomía (TemaClasificadorService/DocumentoLegalObserver) ya
 * determinó que comparte tema con un DocumentoLegal nuevo/modificado,
 * decide si algún bloque específico del RIT necesita ajustarse - y si
 * sí, cuál y con qué contenido. La IA NUNCA reescribe el RIT completo:
 * solo señala un bloque (mismo partidor que RitDiffService::partirEnBloques(),
 * un artículo/parágrafo por bloque) y el ensamblaje final lo hace PHP,
 * de forma determinística, para que el resto del texto sea
 * estructuralmente imposible que cambie.
 *
 * Dos "recetas" según DocumentoLegal::incorporar_completo (bug real
 * reportado por un abogado externo el 2026-09-07: una política que debía
 * ir íntegra como anexo terminó resumida en un parágrafo, porque el
 * motor solo sabía hacer ajustes quirúrgicos de un párrafo):
 * - false (la mayoría): receta quirúrgica de siempre, un bloque.
 * - true: el documento se copia LITERAL (nunca lo redacta la IA) como
 *   Anexo al final del RIT; la IA solo redacta un título corto y un
 *   párrafo breve de referencia cruzada para insertar en el cuerpo.
 * En ambos casos la IA también hace una verificación cruzada y avisa si
 * el documento parece necesitar la otra receta - el abogado pudo
 * equivocarse al marcar la casilla al subirlo.
 */
class RitActualizacionAutomaticaService
{
    /**
     * Evalúa si el documento legal justifica un cambio en el RIT.
     * Devuelve null si la IA concluye que no hace falta ningún cambio (el
     * llamador debe entonces mantener la notificación genérica-pero-filtrada
     * que ya dispara la taxonomía), o un arreglo listo para persistir como
     * SugerenciaActualizacionRit vía crearSugerencia().
     */
    public function evaluarCambio(ReglamentoInterno $rit, DocumentoLegal $documento): ?array
    {
        $bloques = RitDiffService::partirEnBloques((string) $rit->texto_completo);
        if (empty($bloques)) {
            return null;
        }

        if ($documento->incorporar_completo) {
            $prompt = $this->construirPromptAnexo($bloques, $documento);
            $respuesta = $this->llamarGemini($prompt);

            return $this->parsearRespuestaAnexo($respuesta, $bloques, $documento);
        }

        $prompt = $this->construirPromptEvaluacion($bloques, $documento);
        $respuesta = $this->llamarGemini($prompt);

        return $this->parsearRespuesta($respuesta, $bloques);
    }

    private function construirPromptEvaluacion(array $bloques, DocumentoLegal $documento): string
    {
        $bloquesTexto = '';
        foreach ($bloques as $indice => $texto) {
            $bloquesTexto .= "[{$indice}] {$texto}\n";
        }
        // Mismo tope que TemaClasificadorService::truncar() - evita gastar
        // cuota en RITs extremadamente largos, suficiente para que la IA
        // capte el contenido real de cada bloque.
        $bloquesTexto = mb_substr($bloquesTexto, 0, 60000);

        // DocumentoLegal no tiene columna texto_completo - su contenido vive
        // fragmentado en FragmentoDocumento (bug real encontrado al probar
        // este servicio: la primera versión asumía la columna sin verificar
        // el esquema real). Mismo patrón ya usado en
        // TemaClasificadorService::clasificarDocumento().
        $documentoTexto = $documento->fragmentos()->pluck('contenido')->implode("\n\n");
        $documentoTexto = mb_substr($documentoTexto, 0, 40000);

        return <<<PROMPT
        Eres un abogado laboral colombiano revisando si un documento legal
        nuevo o modificado obliga a ajustar el Reglamento Interno de
        Trabajo (RIT) de una empresa.

        PROHIBICIÓN ABSOLUTA: No propongas ningún cambio que el documento
        legal provisto no respalde explícitamente. Si tienes duda, responde
        que no hace falta ningún cambio - un falso positivo (proponer un
        cambio innecesario) es peor que no proponer nada, porque alguien
        tendría que revisarlo y rechazarlo manualmente.

        REGLA CENTRAL: solo puedes señalar UN bloque existente para
        modificar/eliminar, o indicar que hace falta UN bloque nuevo. NUNCA
        propongas reescribir varios bloques a la vez ni el documento
        completo.

        VERIFICACIÓN DE REDUNDANCIA (antes de decidir el bloque): revisa
        TODOS los bloques del RIT, no solo el más obvio. Si el contenido que
        ibas a agregar YA EXISTE en detalle en otro bloque cercano (aunque
        con otras palabras o en un artículo separado), NO lo dupliques.
        Identifica el bloque MÁS ESPECÍFICO y pequeño que de verdad necesita
        el cambio - no el resumen general si el detalle real ya vive en un
        artículo aparte. Ejemplo: si el RIT ya tiene un artículo propio para
        "permiso por citas médicas" con todo el detalle, y la ley solo
        agrega un literal nuevo que no encaja en ningún artículo existente,
        prefiere modificar o agregar el bloque más pequeño y puntual
        posible, no reescribir un párrafo resumen repitiendo contenido que
        ya está bien cubierto en otro lado.

        DOCUMENTO LEGAL NUEVO/MODIFICADO ("{$documento->titulo}"):
        {$documentoTexto}

        BLOQUES ACTUALES DEL RIT (cada línea es un bloque independiente,
        numerado entre corchetes - el número es su identificador real):
        {$bloquesTexto}

        VERIFICACIÓN ADICIONAL: este documento fue marcado para un ajuste
        puntual (un solo párrafo), NO como anexo completo. Si el documento es
        en realidad una política/protocolo/código que declara ser "parte
        integral del Reglamento" o pide anexarse completo - y por lo tanto
        NO se puede resumir fielmente en un solo párrafo - repórtalo en
        "requiere_anexo_completo" en vez de forzar un resumen.

        Responde ÚNICAMENTE con un JSON válido, sin texto adicional ni
        bloques de código markdown, con esta forma exacta:

        Si NO hace falta ningún cambio:
        {"cambio_necesario": false}

        Si SÍ hace falta un cambio:
        {
          "cambio_necesario": true,
          "tipo_cambio": "modificar" o "eliminar" o "agregar",
          "bloque_indice": <número entre corchetes del bloque afectado - para "agregar", el bloque DESPUÉS del cual se inserta el nuevo>,
          "texto_propuesto": "<texto completo del bloque nuevo o modificado - null si tipo_cambio es 'eliminar'>",
          "justificacion": "<por qué este cambio es necesario, citando específicamente qué parte del documento legal lo exige>",
          "requiere_anexo_completo": true o false,
          "motivo_incoherencia": "<solo si requiere_anexo_completo es true: por qué este documento no se puede resumir en un párrafo>"
        }
        PROMPT;
    }

    private function parsearRespuesta(string $respuesta, array $bloques): ?array
    {
        $limpio = trim($respuesta);
        $limpio = preg_replace('/^```json\s*|\s*```$/i', '', $limpio) ?? $limpio;

        $datos = json_decode($limpio, true);
        if (!is_array($datos)) {
            Log::warning('RitActualizacionAutomaticaService: respuesta de IA no es JSON válido', [
                'respuesta' => $respuesta,
            ]);
            return null;
        }

        if (empty($datos['cambio_necesario'])) {
            return null;
        }

        $tipoCambio = $datos['tipo_cambio'] ?? null;
        $bloqueIndice = $datos['bloque_indice'] ?? null;

        if (!in_array($tipoCambio, ['modificar', 'agregar', 'eliminar'], true)) {
            Log::warning('RitActualizacionAutomaticaService: tipo_cambio inválido', ['datos' => $datos]);
            return null;
        }

        if (!is_numeric($bloqueIndice) || !array_key_exists((int) $bloqueIndice, $bloques)) {
            Log::warning('RitActualizacionAutomaticaService: bloque_indice fuera de rango', ['datos' => $datos]);
            return null;
        }

        $bloqueIndice = (int) $bloqueIndice;

        return [
            'bloque_indice'    => $bloqueIndice,
            'tipo_cambio'      => $tipoCambio,
            'texto_anterior'   => $tipoCambio === 'agregar' ? null : $bloques[$bloqueIndice],
            'texto_propuesto'  => $tipoCambio === 'eliminar' ? null : (string) ($datos['texto_propuesto'] ?? ''),
            'justificacion'    => (string) ($datos['justificacion'] ?? ''),
            'alerta_incoherencia' => !empty($datos['requiere_anexo_completo'])
                ? (string) ($datos['motivo_incoherencia'] ?? 'La IA detectó que este documento podría necesitar incorporarse completo como Anexo, no solo un ajuste puntual.')
                : null,
        ];
    }

    /**
     * Prompt de la segunda receta (DocumentoLegal::incorporar_completo =
     * true): la IA NUNCA redacta ni resume el documento - solo propone un
     * título de anexo y un párrafo breve de referencia cruzada para el
     * cuerpo del RIT. El texto del anexo se copia literal en PHP, en
     * parsearRespuestaAnexo().
     */
    private function construirPromptAnexo(array $bloques, DocumentoLegal $documento): string
    {
        $bloquesTexto = '';
        foreach ($bloques as $indice => $texto) {
            $bloquesTexto .= "[{$indice}] {$texto}\n";
        }
        $bloquesTexto = mb_substr($bloquesTexto, 0, 60000);

        $documentoTexto = $documento->fragmentos()->pluck('contenido')->implode("\n\n");
        $documentoTexto = mb_substr($documentoTexto, 0, 40000);

        return <<<PROMPT
        Eres un abogado laboral colombiano. Este documento legal fue marcado
        explícitamente para incorporarse COMPLETO y LITERAL como un Anexo del
        Reglamento Interno de Trabajo (RIT) de una empresa - NO debes
        resumirlo ni reescribirlo, el texto se copia aparte, palabra por
        palabra, por fuera de tu respuesta.

        PROHIBICIÓN ABSOLUTA: no reproduzcas ni resumas el contenido del
        documento en tu respuesta. Tu única tarea es: (1) proponer un título
        corto para el anexo, y (2) redactar un párrafo BREVE (2 a 4 líneas)
        para insertar en el cuerpo del RIT que remita al anexo, señalando el
        bloque existente más afín donde insertarlo.

        VERIFICACIÓN ADICIONAL: evalúa también si este documento en realidad
        NO amerita ir como anexo completo - si es un ajuste menor que
        bastaría resolver con un párrafo puntual dentro del cuerpo del RIT,
        repórtalo en "parece_ser_puntual" (el abogado que lo subió pudo
        marcar la casilla por error).

        DOCUMENTO LEGAL ("{$documento->titulo}"):
        {$documentoTexto}

        BLOQUES ACTUALES DEL RIT (cada línea es un bloque independiente,
        numerado entre corchetes - el número es su identificador real):
        {$bloquesTexto}

        Responde ÚNICAMENTE con un JSON válido, sin texto adicional ni
        bloques de código markdown, con esta forma exacta:
        {
          "titulo_anexo": "<título corto del anexo, ej. 'Política de Prevención de Acoso Sexual y Discriminación'>",
          "bloque_indice": <número entre corchetes del bloque del cuerpo DESPUÉS del cual insertar el párrafo de referencia>,
          "texto_referencia": "<párrafo breve que remite al anexo>",
          "justificacion": "<por qué este documento exige incorporarse como anexo completo>",
          "parece_ser_puntual": true o false,
          "motivo_incoherencia": "<solo si parece_ser_puntual es true: por qué en realidad bastaba un ajuste puntual>"
        }
        PROMPT;
    }

    private function parsearRespuestaAnexo(string $respuesta, array $bloques, DocumentoLegal $documento): ?array
    {
        $limpio = trim($respuesta);
        $limpio = preg_replace('/^```json\s*|\s*```$/i', '', $limpio) ?? $limpio;

        $datos = json_decode($limpio, true);
        if (!is_array($datos)) {
            Log::warning('RitActualizacionAutomaticaService: respuesta de IA (anexo) no es JSON válido', [
                'respuesta' => $respuesta,
            ]);
            return null;
        }

        $bloqueIndice = $datos['bloque_indice'] ?? null;
        if (!is_numeric($bloqueIndice) || !array_key_exists((int) $bloqueIndice, $bloques)) {
            Log::warning('RitActualizacionAutomaticaService: bloque_indice fuera de rango (anexo)', ['datos' => $datos]);
            return null;
        }

        // El texto del anexo NUNCA lo genera la IA - se copia literal de
        // FragmentoDocumento (mismo campo que ya usa TemaClasificadorService),
        // justo el requisito que motivó esta receta: el documento debe ir
        // palabra por palabra, no resumido.
        $textoAnexo = $documento->fragmentos()->orderBy('orden')->pluck('contenido')->implode("\n\n");
        if (trim($textoAnexo) === '') {
            Log::warning('RitActualizacionAutomaticaService: documento sin fragmentos, no se puede anexar completo', [
                'documento_id' => $documento->id,
            ]);
            return null;
        }

        return [
            'bloque_indice'       => (int) $bloqueIndice,
            'tipo_cambio'         => 'anexar_completo',
            'texto_anterior'      => null,
            'texto_propuesto'     => (string) ($datos['texto_referencia'] ?? ''),
            'titulo_anexo'        => trim((string) ($datos['titulo_anexo'] ?? $documento->titulo)),
            'texto_anexo'         => $textoAnexo,
            'justificacion'       => (string) ($datos['justificacion'] ?? ''),
            'alerta_incoherencia' => !empty($datos['parece_ser_puntual'])
                ? (string) ($datos['motivo_incoherencia'] ?? 'La IA detectó que este documento podría necesitar solo un ajuste puntual, no un anexo completo.')
                : null,
        ];
    }

    public function crearSugerencia(ReglamentoInterno $rit, DocumentoLegal $documento, array $cambio): SugerenciaActualizacionRit
    {
        return SugerenciaActualizacionRit::create([
            'empresa_id'             => $rit->empresa_id,
            'reglamento_interno_id'  => $rit->id,
            'documento_legal_id'     => $documento->id,
            'bloque_indice'          => $cambio['bloque_indice'],
            'tipo_cambio'            => $cambio['tipo_cambio'],
            'texto_anterior'         => $cambio['texto_anterior'],
            'texto_propuesto'        => $cambio['texto_propuesto'],
            'titulo_anexo'           => $cambio['titulo_anexo'] ?? null,
            'texto_anexo'            => $cambio['texto_anexo'] ?? null,
            'justificacion_ia'       => $cambio['justificacion'],
            'alerta_incoherencia'    => $cambio['alerta_incoherencia'] ?? null,
            'estado'                 => 'pendiente',
        ]);
    }

    /**
     * Aplica el ensamblaje determinístico: PHP reemplaza/inserta/elimina
     * ÚNICAMENTE el bloque señalado, nunca vuelve a pasar el resto del
     * texto por la IA. Verifica primero que el bloque en la posición
     * indicada siga siendo el mismo texto_anterior capturado al proponer
     * la sugerencia - si el RIT cambió entre tanto (otra sugerencia ya
     * aplicada, edición manual), el índice puede haber quedado
     * desalineado y NO se aplica a ciegas.
     */
    public function aplicarSugerencia(SugerenciaActualizacionRit $sugerencia, User $resolutor): bool
    {
        if ($sugerencia->estado !== 'pendiente') {
            return false;
        }

        // El documento que originó la propuesta fue retirado (subido por error,
        // versión equivocada, quedó obsoleto). No se puede modificar el RIT -un
        // documento legal- con base en una fuente que la propia firma dio de
        // baja. Se valida aquí y no solo al listar, para que ningún camino
        // (id forzado, job, comando) pueda saltárselo.
        if (! $sugerencia->documentoLegal || ! $sugerencia->documentoLegal->activo) {
            Log::warning('RitActualizacionAutomaticaService: no se aplica, el documento legal de origen ya no está activo', [
                'sugerencia_id'      => $sugerencia->id,
                'documento_legal_id' => $sugerencia->documento_legal_id,
            ]);

            return false;
        }

        $rit = $sugerencia->reglamentoInterno;
        $bloques = RitDiffService::partirEnBloques((string) $rit->texto_completo);

        $indice = $sugerencia->bloque_indice;
        $bloqueActual = $bloques[$indice] ?? null;

        // 'anexar_completo' se ancla al cuerpo igual que 'agregar' (inserta
        // DESPUÉS de un bloque existente, nunca reemplaza uno) - por eso
        // comparte la misma excepción a la re-verificación de contenido.
        if (!in_array($sugerencia->tipo_cambio, ['agregar', 'anexar_completo'], true) && $bloqueActual !== $sugerencia->texto_anterior) {
            // El índice ya no apunta al bloque original: el RIT cambió entre que
            // se propuso el cambio y el cliente lo aprobó (editó su reglamento,
            // aplicó otra sugerencia, etc.).
            //
            // Caso típico y recuperable: el bloque sigue existiendo palabra por
            // palabra, solo se corrió de posición porque se insertó o eliminó
            // algo antes. Se vuelve a anclar POR CONTENIDO. Sin esto la
            // sugerencia quedaba pendiente para siempre y el botón "Aprobar"
            // fallaba una y otra vez, sin ninguna salida para el cliente.
            $coincidencias = array_keys($bloques, $sugerencia->texto_anterior, true);

            if (count($coincidencias) === 1) {
                $indice = $coincidencias[0];
            } else {
                // 0 coincidencias: el bloque se editó o se borró.
                // 2+: es ambiguo y aplicar podría tocar el bloque equivocado.
                // En ambos casos no se aplica a ciegas: el texto del RIT es
                // justamente lo que el cliente confía en que no se toca solo.
                Log::warning('RitActualizacionAutomaticaService: bloque desalineado, no se aplica automáticamente', [
                    'sugerencia_id' => $sugerencia->id,
                    'bloque_indice' => $sugerencia->bloque_indice,
                    'coincidencias' => count($coincidencias),
                    'esperado'      => $sugerencia->texto_anterior,
                    'actual'        => $bloqueActual,
                ]);

                return false;
            }
        }

        switch ($sugerencia->tipo_cambio) {
            case 'modificar':
                $bloques[$indice] = $sugerencia->texto_propuesto;
                break;
            case 'eliminar':
                unset($bloques[$indice]);
                $bloques = array_values($bloques);
                break;
            case 'agregar':
                array_splice($bloques, $indice + 1, 0, [$sugerencia->texto_propuesto]);
                break;
            case 'anexar_completo':
                // El párrafo de referencia se inserta en el cuerpo (igual que
                // 'agregar') y el documento completo se agrega como su
                // propio Anexo al final - ambas inserciones en el mismo
                // arreglo, antes de la única escritura a BD de abajo, para
                // que queden atómicas (las dos o ninguna).
                array_splice($bloques, $indice + 1, 0, [$sugerencia->texto_propuesto]);
                $bloques[] = mb_strtoupper($sugerencia->titulo_anexo) . "\n\n" . $sugerencia->texto_anexo;
                break;
        }

        $rit->update(['texto_completo' => implode("\n", $bloques)]);

        $sugerencia->update([
            'estado'      => 'aprobada',
            'resuelto_por' => $resolutor->id,
            'resuelto_en'  => now(),
        ]);

        $this->marcarNotificacionComoLeida($sugerencia);

        return true;
    }

    public function rechazarSugerencia(SugerenciaActualizacionRit $sugerencia, User $resolutor): void
    {
        $sugerencia->update([
            'estado'       => 'rechazada',
            'resuelto_por' => $resolutor->id,
            'resuelto_en'  => now(),
        ]);

        $this->marcarNotificacionComoLeida($sugerencia);
    }

    /**
     * Bug real reportado por el usuario (2026-09-07): al aprobar/rechazar
     * una sugerencia desde "Mi Reglamento Interno", la tarjeta de "Cambios
     * sugeridos" desaparece correctamente, pero la notificación de la
     * campana (App\Models\Notificacion, sistema propio - NO el de
     * Filament) que invitaba a revisarla queda huérfana: sigue diciendo
     * "¿Desea actualizarlo?" sobre algo que ya se resolvió. Se marca como
     * leída (no se borra - conserva el historial de que llegó) en cuanto
     * la sugerencia deja de estar pendiente.
     */
    private function marcarNotificacionComoLeida(SugerenciaActualizacionRit $sugerencia): void
    {
        \App\Models\Notificacion::where('relacionado_tipo', SugerenciaActualizacionRit::class)
            ->where('relacionado_id', $sugerencia->id)
            ->where('leida', false)
            ->get()
            ->each->marcarComoLeida();
    }

    /**
     * Copiado del mismo patrón usado en TemaClasificadorService::llamarGemini()
     * / SolicitudContratoIAService::llamarGemini() - sin trait compartido,
     * es la convención ya establecida en el repo. thinkingBudget:0 es
     * obligatorio para respuestas JSON (bug real ya encontrado en la
     * taxonomía: sin esto, Gemini 2.5 trunca el JSON a mitad de camino).
     */
    private function llamarGemini(string $prompt): string
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
                'temperature'     => 0.1,
                'maxOutputTokens' => 4096,
                'topP'            => 0.95,
                'thinkingConfig'  => ['thinkingBudget' => 0],
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
                Log::warning('RitActualizacionAutomaticaService: fallo en intento', [
                    'model' => $model, 'intento' => $intento, 'status' => $status,
                ]);
                $lastError = $response->body();

                if (in_array($status, [429, 503], true) && $intento < 2) {
                    sleep(10);
                }
            }
        }

        throw new \RuntimeException('No se pudo evaluar el cambio con IA: ' . $lastError);
    }
}
