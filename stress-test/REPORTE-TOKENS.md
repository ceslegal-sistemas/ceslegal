# Reporte de tokens y costo de IA — flujo RIT → decisión de sanción

Mide cuántos tokens consume y cuánto cuesta en Gemini **un flujo completo**:
construcción del RIT → creación de descargos → diligencia → decisión de la sanción.
(La impugnación queda fuera por ahora.)

> La forma correcta NO es estimar a ojo, sino **medir los tokens reales** que
> devuelve Gemini (`usageMetadata`). Para eso se agregó un registrador opt-in y un
> comando de reporte.

---

## Cómo medir el costo real (recomendado)

1. En el `.env` del entorno (staging/local) activa el registro:
   ```
   IA_TOKEN_LOG=true
   ```
   y aplica:
   ```bash
   php artisan optimize:clear
   ```

2. Pon el registro en cero antes de empezar:
   ```bash
   php artisan ia:reporte-tokens --limpiar
   ```

3. Corre **un flujo completo de principio a fin**:
   - Construir el RIT (Mi Reglamento / generar RIT).
   - Crear el proceso disciplinario y los descargos.
   - Diligenciar los descargos (responder como trabajador).
   - Emitir / decidir la sanción (genera el documento).

4. Saca el reporte:
   ```bash
   php artisan ia:reporte-tokens
   ```
   Verás el detalle por llamada (cada paso del flujo), el resumen por modelo y el
   **total en tokens, USD y COP**.

> Si el RIT se genera en cola (job), asegúrate de correr el worker para que se
> ejecute: `php artisan queue:work --stop-when-empty`.

Para aislar **solo** la sanción (sin el RIT), usa `--desde` con la hora en que
empezaste esa parte: `php artisan ia:reporte-tokens --desde=2026-06-22T18:05:00`.

Cuando termines de medir, **desactiva** el registro (`IA_TOKEN_LOG=false` +
`optimize:clear`) para no llenar el log en producción.

---

## Llamadas a IA en el flujo (mapa del código)

Todas usan `gemini-2.5-flash` (con caída a `flash-lite`) salvo los embeddings:

| Paso | Servicio | maxOutputTokens | Notas |
|---|---|---|---|
| **RIT** | `RITGeneratorService` / `RITMejoradoService` | **32 768** | Salida grande (reglamento completo). La llamada más pesada. |
| RIT (índice) | `BibliotecaLegalService` (embedding) | — | `gemini-embedding-001`, costo marginal. |
| **Hechos** | `EvaluacionHechosService` | 1 500 | Análisis de hechos; 1-3 llamadas según validación/feedback. |
| Hechos (índice) | embedding | — | Marginal. |
| **Preguntas descargos** | `IADescargoService` | 2 048 | Genera el cuestionario de descargos. |
| **Diligencia** | `IADescargoService` (feedback/voz) | 1 024 | Variable: 0-N según interacción del trabajador. |
| **Análisis sanción** | `IAAnalisisSancionService` | hasta **16 384** | Entrada grande (CST + jurisprudencia + RIT + hechos + descargos). |
| Jurisprudencia | embedding (consulta) | — | Marginal. |
| Pruebas (multimodal) | `IAAnalisisSancionService` | 1 024 | Solo si hay evidencias adjuntas; suma tokens de imagen. |
| **Documento sanción** | `DocumentGeneratorService` | 8 192 | Redacta la resolución/carta final. |

---

## Estimación gruesa (mientras mides el real)

Con tamaños típicos de prompt y salida observados en el código:

| Bloque | Tokens entrada | Tokens salida | Costo USD aprox. |
|---|---|---|---|
| Construcción del RIT | ~4 000 | ~10 000–25 000 | $0.03 – $0.07 |
| Descargos (hechos + preguntas) | ~5 000 | ~3 000 | $0.01 |
| Diligencia (feedback) | ~2 000 | ~1 000 | $0.003 |
| Análisis de sanción | ~12 000 | ~2 500 | $0.01 |
| Documento de sanción | ~2 500 | ~3 000 | $0.01 |
| **TOTAL por flujo completo** | **~25 000** | **~20 000–35 000** | **~$0.07 – $0.13** |

A precios `flash`: entrada $0.30/1M, salida $2.50/1M (USD). **La salida domina el costo**
(es ~8× más cara que la entrada), por eso el RIT y el documento de sanción pesan más.

**Proyección:** a ~$0.10/flujo → 1 000 flujos/mes ≈ **$100 USD (~$400 000 COP)**;
10 000 flujos/mes ≈ **$1 000 USD**. Mídelo real para afinar.

> Estos números son una guía. El comando `ia:reporte-tokens` te da el costo EXACTO
> de tus flujos reales — úsalo para la cifra que lleves a tu jefe.

---

## Cómo bajar el costo (si hace falta)

- Para pasos no críticos (feedback, preguntas) usar `gemini-2.5-flash-lite`
  (salida 6× más barata: $0.40 vs $2.50 /1M).
- Reducir `maxOutputTokens` donde la salida real sea menor que el tope.
- Cachear/condensar el contexto repetido (CST, jurisprudencia) para bajar tokens
  de entrada del análisis de sanción.
