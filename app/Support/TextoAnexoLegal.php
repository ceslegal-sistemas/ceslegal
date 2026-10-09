<?php

namespace App\Support;

/**
 * Reconstruye el texto de un documento legal de la Biblioteca para incorporarlo
 * como Anexo del RIT (RitActualizacionAutomaticaService::parsearRespuestaAnexo).
 *
 * El anexo se arma pegando los FragmentoDocumento del documento, pero esos
 * fragmentos NO son el texto limpio:
 * - BibliotecaLegalService::chunkear() antepone a cada fragmento (salvo al
 *   primero) las últimas PALABRAS_SOLAPAMIENTO palabras del anterior, para la
 *   búsqueda semántica. Pegarlos tal cual duplica ese texto en cada empalme.
 * - El texto viene de una página web: trae menús ("Inicio", "Artículo"), el
 *   aviso de derechos de autor y frases cortadas en dos líneas.
 *
 * Esta limpieza solo quita lo que NO es contenido de la norma. El texto legal
 * en sí no se reescribe.
 */
class TextoAnexoLegal
{
    /** Igual a BibliotecaLegalService::PALABRAS_SOLAPAMIENTO (con margen). */
    private const MAX_PALABRAS_SOLAPAMIENTO = 90;

    /** @param array<int, string> $fragmentos contenido de los fragmentos, ordenado por 'orden' */
    public static function reconstruir(array $fragmentos): string
    {
        $partes = [];
        foreach (array_values($fragmentos) as $i => $contenido) {
            $contenido = trim((string) $contenido);
            if ($i > 0) {
                $contenido = self::quitarSolapamiento($contenido);
            }
            if ($contenido !== '') {
                $partes[] = $contenido;
            }
        }

        return self::limpiarRuidoWeb(implode("\n\n", $partes));
    }

    /**
     * El solapamiento es lo que hay antes del primer salto de párrafo ("\n\n"):
     * chunkear() lo une con ' ' y lo separa del resto con "\n\n". Si ese
     * prefijo es más largo de lo esperado no se toca (no es solapamiento).
     */
    public static function quitarSolapamiento(string $fragmento): string
    {
        $pos = strpos($fragmento, "\n\n");
        if ($pos === false) {
            return $fragmento;
        }

        $prefijo = substr($fragmento, 0, $pos);
        if (str_word_count($prefijo) > self::MAX_PALABRAS_SOLAPAMIENTO) {
            return $fragmento;
        }

        return ltrim(substr($fragmento, $pos + 2));
    }

    /**
     * Para mostrar líneas de un diff (RitDiffService, una línea por cambio) como
     * párrafos legibles: une frases cortadas y omite menús web y vacíos.
     *
     * @param  array<int, string> $lineas
     * @return array<int, string>
     */
    public static function parrafosDeLineas(array $lineas): array
    {
        $limpio = self::limpiarRuidoWeb(implode("\n", array_map('strval', $lineas)));

        return array_values(array_filter(array_map('trim', explode("\n", $limpio)), fn ($l) => $l !== ''));
    }

    public static function limpiarRuidoWeb(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $lineas = array_map('trim', explode("\n", $texto));

        $ruido = [
            '/^(inicio|art[ií]culo|anterior|siguiente|volver|imprimir|compartir)$/iu',
            '/^derechos de autor reservados\b.*$/iu',
        ];

        $salida = [];
        foreach ($lineas as $linea) {
            foreach ($ruido as $patron) {
                if ($linea !== '' && preg_match($patron, $linea)) {
                    continue 2;
                }
            }

            if ($linea === '') {
                // Un solo salto de párrafo, nunca varios seguidos.
                if (!empty($salida) && end($salida) !== '') {
                    $salida[] = '';
                }
                continue;
            }

            // Frase cortada por la página: la línea siguiente empieza en
            // minúscula y la anterior no terminó en puntuación final.
            $idxPrevio = self::ultimoNoVacio($salida);
            if ($idxPrevio !== null
                && preg_match('/^\p{Ll}/u', $linea)
                && !preg_match('/[.:;!?]$/u', $salida[$idxPrevio])) {
                $salida[$idxPrevio] .= ' ' . $linea;
                // Se descartan los vacíos que quedaron entre las dos mitades.
                $salida = array_slice($salida, 0, $idxPrevio + 1);
                continue;
            }

            $salida[] = $linea;
        }

        return trim(implode("\n", $salida));
    }

    private static function ultimoNoVacio(array $lineas): ?int
    {
        for ($i = count($lineas) - 1; $i >= 0; $i--) {
            if ($lineas[$i] !== '') {
                return $i;
            }
        }

        return null;
    }
}
