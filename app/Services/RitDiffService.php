<?php

namespace App\Services;

/**
 * Compara el texto de un RIT original contra su versión mejorada y produce un
 * "redline" legal: qué se agregó, qué se eliminó y qué se modificó.
 *
 * Nivel 1 (artículo/párrafo): cada línea del documento (un artículo o un parágrafo
 * suele ocupar su propia línea, por convención del generador) se empareja entre
 * ambos textos por igualdad exacta primero, y por similitud de palabras (Jaccard)
 * después. Lo que no encuentra pareja es "agregado" (solo en el mejorado) o
 * "eliminado" (solo en el original). Lo que se empareja por similitud pero no es
 * idéntico es "modificado".
 *
 * Nivel 2 (palabra): dentro de cada bloque "modificado", se hace un diff de
 * palabras (LCS clásico) para marcar exactamente qué cambió, en vez de resaltar
 * el artículo completo.
 *
 * Es una comparación de PRESENTACIÓN (para revisión humana antes de aceptar) — no
 * se persiste ni se usa como fuente de verdad; el texto final adoptado siempre es
 * el texto plano del RIT mejorado.
 */
class RitDiffService
{
    /** Similitud mínima (Jaccard sobre PALABRAS DE CONTENIDO, sin stopwords) para considerar dos bloques "el mismo, modificado". */
    private const UMBRAL_SIMILITUD = 0.28;

    /** Si algún documento supera este número de bloques, se omite el emparejamiento fuzzy (solo exactos). */
    private const MAX_BLOQUES_FUZZY = 2000;

    /**
     * Palabras funcionales del español: se excluyen del cálculo de similitud (no del
     * diff de palabras, que muestra el texto completo) porque su alta frecuencia en
     * CUALQUIER frase legal produce falsos positivos entre artículos no relacionados.
     */
    // NOTA: sin tildes a propósito — tokenizarParaSimilitud() quita acentos del texto
    // ANTES de comparar contra esta lista, así que debe estar ya normalizada igual.
    private const STOPWORDS = [
        'de', 'del', 'la', 'el', 'los', 'las', 'un', 'una', 'unos', 'unas', 'y', 'o', 'u', 'a', 'al',
        'en', 'con', 'para', 'por', 'su', 'sus', 'no', 'lo', 'como', 'este', 'esta', 'estos', 'estas',
        'ese', 'esa', 'esos', 'esas', 'que', 'se', 'es', 'son', 'ha', 'han', 'sea', 'sean', 'ser',
        'esta', 'estan', 'fue', 'fueron', 'ni', 'si', 'sin', 'sobre', 'entre', 'cuando', 'cual',
        'cuales', 'cuyo', 'cuya', 'le', 'les', 'ya', 'mas', 'menos', 'muy', 'todo', 'toda',
        'todos', 'todas', 'otro', 'otra', 'otros', 'otras', 'mismo', 'misma', 'mismos', 'mismas',
        'cada', 'asi', 'tal', 'tales', 'pero', 'tambien', 'e', 'segun', 'hasta', 'desde',
    ];

    /**
     * @return array<int, array{tipo:string,texto?:string,palabras?:array}>
     *   tipo: 'igual'|'agregado'|'eliminado'|'modificado'
     *   'modificado' trae 'palabras' => [['tipo'=>'igual'|'agregado'|'eliminado','texto'=>string], ...]
     */
    public function compararDocumentos(string $original, string $mejorado): array
    {
        $bloquesOrig = $this->partirEnBloques($original);
        $bloquesMej  = $this->partirEnBloques($mejorado);

        $matchMejADeOrig = array_fill(0, count($bloquesMej), null);
        $matchOrigAMej   = array_fill(0, count($bloquesOrig), null);

        // Paso 1: coincidencias exactas (texto idéntico), una sola vez cada una.
        $usadosOrig = [];
        foreach ($bloquesMej as $mi => $bm) {
            foreach ($bloquesOrig as $oi => $bo) {
                if (! isset($usadosOrig[$oi]) && $bm === $bo) {
                    $matchMejADeOrig[$mi] = $oi;
                    $matchOrigAMej[$oi]   = $mi;
                    $usadosOrig[$oi]      = true;
                    break;
                }
            }
        }

        // Paso 2: para lo que queda, emparejamiento greedy por similitud de PALABRAS DE
        // CONTENIDO (sin stopwords). Se memoiza el tokenizado (una vez por bloque, no
        // por par) y se descartan primero los pares con longitudes muy dispares
        // (un artículo corto casi nunca es "la misma cláusula, modificada" que uno
        // 4x más largo) para no pagar el costo de Jaccard en pares imposibles.
        if (count($bloquesOrig) <= self::MAX_BLOQUES_FUZZY && count($bloquesMej) <= self::MAX_BLOQUES_FUZZY) {
            $pendientesMej  = array_keys(array_filter($matchMejADeOrig, fn ($v) => $v === null));
            $pendientesOrig = array_keys(array_filter($matchOrigAMej, fn ($v) => $v === null));

            $tokensMej  = [];
            foreach ($pendientesMej as $mi) {
                $tokensMej[$mi] = $this->tokenizarParaSimilitud($bloquesMej[$mi]);
            }
            $tokensOrig = [];
            foreach ($pendientesOrig as $oi) {
                $tokensOrig[$oi] = $this->tokenizarParaSimilitud($bloquesOrig[$oi]);
            }

            $candidatos = [];
            foreach ($pendientesMej as $mi) {
                $lenMi = mb_strlen($bloquesMej[$mi]);
                foreach ($pendientesOrig as $oi) {
                    $lenOi = mb_strlen($bloquesOrig[$oi]);
                    $mayor = max($lenMi, $lenOi);
                    if ($mayor > 0 && min($lenMi, $lenOi) / $mayor < 0.2) {
                        continue; // longitudes demasiado dispares: no es la misma cláusula
                    }
                    $score = $this->similitudTokens($tokensMej[$mi], $tokensOrig[$oi]);
                    if ($score >= self::UMBRAL_SIMILITUD) {
                        $candidatos[] = [$score, $mi, $oi];
                    }
                }
            }
            usort($candidatos, fn ($a, $b) => $b[0] <=> $a[0]);

            $mejEmparejado  = [];
            $origEmparejado = [];
            foreach ($candidatos as [$score, $mi, $oi]) {
                if (isset($mejEmparejado[$mi]) || isset($origEmparejado[$oi])) {
                    continue;
                }
                $matchMejADeOrig[$mi] = $oi;
                $matchOrigAMej[$oi]   = $mi;
                $mejEmparejado[$mi]   = true;
                $origEmparejado[$oi]  = true;
            }
        }

        return $this->armarResultado($bloquesOrig, $bloquesMej, $matchMejADeOrig, $matchOrigAMej);
    }

    /**
     * Construye la salida en el orden del documento MEJORADO (igual/modificado/agregado),
     * e inserta los bloques "eliminado" (originales sin pareja) en la posición correcta
     * según SU PROPIO orden en el original — no en el orden en que el emparejamiento por
     * similitud los fue resolviendo (que no es secuencial: el mejor score puede emparejar
     * primero un bloque muy posterior). Recorrer cada documento en su propio orden natural
     * garantiza que cada bloque se visite y emita EXACTAMENTE una vez.
     */
    private function armarResultado(array $bloquesOrig, array $bloquesMej, array $matchMejADeOrig, array $matchOrigAMej): array
    {
        // 1. Esqueleto en el orden del mejorado; se anota en qué posición quedó cada $mi.
        $resultado = [];
        $posicionDeMi = [];
        foreach ($bloquesMej as $mi => $bm) {
            $oi = $matchMejADeOrig[$mi];

            if ($oi === null) {
                $resultado[] = ['tipo' => 'agregado', 'texto' => $bm];
            } elseif ($bloquesOrig[$oi] === $bm) {
                $resultado[] = ['tipo' => 'igual', 'texto' => $bm];
            } else {
                $resultado[] = [
                    'tipo'     => 'modificado',
                    'palabras' => $this->diffPalabras($bloquesOrig[$oi], $bm),
                ];
            }
            $posicionDeMi[$mi] = count($resultado) - 1;
        }

        // 2. Recorrer el ORIGINAL en su propio orden: cada bloque sin pareja se ancla
        //    justo después de la posición (en $resultado) del último original emparejado
        //    que lo precede. -1 = antes de todo (no hubo ningún emparejado previo).
        $porInsertar = [];
        $ultimaPosicion = -1;
        foreach ($bloquesOrig as $oi => $bo) {
            $mi = $matchOrigAMej[$oi];
            if ($mi !== null) {
                $ultimaPosicion = $posicionDeMi[$mi];
            } else {
                $porInsertar[$ultimaPosicion][] = $bo;
            }
        }

        // 3. Fusionar: recorrer $resultado insertando los eliminados anclados a cada posición.
        $final = [];
        foreach (($porInsertar[-1] ?? []) as $bo) {
            $final[] = ['tipo' => 'eliminado', 'texto' => $bo];
        }
        foreach ($resultado as $idx => $bloque) {
            $final[] = $bloque;
            foreach (($porInsertar[$idx] ?? []) as $bo) {
                $final[] = ['tipo' => 'eliminado', 'texto' => $bo];
            }
        }

        return $final;
    }

    /** Bloques diffables: una línea no vacía = un artículo, parágrafo o encabezado. */
    private function partirEnBloques(string $texto): array
    {
        $lineas   = preg_split('/\r?\n/', trim($texto));
        $bloques = [];
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea !== '') {
                $bloques[] = $linea;
            }
        }
        return $bloques;
    }

    /** Similitud Jaccard entre dos conjuntos de palabras DE CONTENIDO ya tokenizados. */
    private function similitudTokens(array $wa, array $wb): float
    {
        if (empty($wa) || empty($wb)) {
            return 0.0;
        }
        $interseccion = count(array_intersect($wa, $wb));
        $union        = count(array_unique(array_merge($wa, $wb)));
        return $union > 0 ? $interseccion / $union : 0.0;
    }

    /**
     * Tokeniza para SIMILITUD: minúsculas, sin acentos, sin stopwords ni palabras muy
     * cortas. El objetivo es capturar términos con carga legal/semántica (jornada,
     * suspensión, cuarenta, dos, etc.), no palabras funcionales que aparecen en
     * cualquier frase y producirían falsos positivos entre artículos no relacionados.
     */
    private function tokenizarParaSimilitud(string $texto): array
    {
        $texto = mb_strtolower($texto);
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        preg_match_all('/[\p{L}\p{N}]+/u', $texto, $m);
        return array_values(array_filter(
            $m[0],
            fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, self::STOPWORDS, true)
        ));
    }

    /**
     * Diff de palabras (LCS clásico) entre la versión vieja y la nueva de un bloque
     * modificado. Conserva los espacios como tokens propios para no deformar el texto.
     */
    private function diffPalabras(string $viejo, string $nuevo): array
    {
        $a = preg_split('/(\s+)/u', $viejo, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $b = preg_split('/(\s+)/u', $nuevo, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $n = count($a);
        $m = count($b);

        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = ($a[$i] === $b[$j])
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $out = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out[] = ['tipo' => 'igual', 'texto' => $a[$i]];
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $out[] = ['tipo' => 'eliminado', 'texto' => $a[$i]];
                $i++;
            } else {
                $out[] = ['tipo' => 'agregado', 'texto' => $b[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $out[] = ['tipo' => 'eliminado', 'texto' => $a[$i]];
            $i++;
        }
        while ($j < $m) {
            $out[] = ['tipo' => 'agregado', 'texto' => $b[$j]];
            $j++;
        }

        return $this->colapsarConsecutivos($out);
    }

    /** Fusiona tokens consecutivos del mismo tipo en un solo span (menos ruido al renderizar). */
    private function colapsarConsecutivos(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $t) {
            $ultimo = count($out) - 1;
            if ($ultimo >= 0 && $out[$ultimo]['tipo'] === $t['tipo']) {
                $out[$ultimo]['texto'] .= $t['texto'];
            } else {
                $out[] = $t;
            }
        }
        return $out;
    }
}
