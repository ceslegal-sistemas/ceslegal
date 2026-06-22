# Prueba de estrés — CES Legal

Objetivo: conocer el **techo real** del plan de Hostinger compartido y estimar
**cuántos clientes** aguanta antes de tener que migrar a VPS/Cloud.

> REGLA DE ORO: se prueba contra un **clon/staging**, nunca contra producción
> (`descargos.ceslegal.co`). Y ojo: si el clon está en la **misma cuenta** de
> Hostinger, comparte CPU y MySQL con producción → estresarlo puede tumbar prod
> y hacer que **suspendan toda la cuenta**. Lo ideal es el clon en una
> **cuenta/plan separado**. Si no se puede, correr **suave y en horario de bajo
> tráfico**, subiendo de a poco y deteniéndose al primer signo de saturación.

---

## Los 3 muros (por qué se prueba por separado)

1. **MySQL** — cola, caché y sesión usan la base (`*_CONNECTION=database`). Es el
   cuello de botella único a escala.
2. **Hostinger compartido** — límites duros: procesos PHP concurrentes (~20-40),
   CPU, y conexiones MySQL (~25-75). No existe "miles a la vez" aquí.
3. **Gemini (IA)** — techo EXTERNO: cuota de Google (RPM/TPM) + costo $. El
   servidor no es el límite ahí. Por eso los benchmarks de TU servidor **no**
   llaman a Gemini.

---

## Paso 1 — Capacidad del servidor (PDF + MySQL) · por SSH en el staging

Mide cuántos documentos/seg y operaciones de BD/seg aguanta **un proceso (un core)**.
No usa IA, no cuesta dinero, no toca datos reales.

```bash
php artisan stress:benchmark --pdf=50 --db=2000
```

Apunta el resultado:
- **PDF p50 (ms)** → throughput real de documentos. Ej: 400 ms ⇒ ~2.5 PDF/seg por core.
  (El primer PDF tarda más por el *warmup* de fuentes de DomPDF; usa el **p50**, no la media.)
- **MySQL ciclo /s** → cuántas escrituras+lecturas aguanta. Si esto es bajo, la
  cola/caché/sesión en BD se vuelve el límite.

Estimación: `PDF/seg total ≈ (1000 / pdf_p50_ms) × nº de cores/procesos del plan`.

---

## Paso 2 — Concurrencia web (k6) · desde TU PC contra el staging

Instala k6: https://k6.io/docs/get-started/installation/

```bash
k6 run -e BASE_URL=https://staging.tudominio.com stress-test/k6-ramp.js
# escalón final más alto:
k6 run -e BASE_URL=https://staging.tudominio.com -e MAX=300 stress-test/k6-ramp.js
```

La prueba sube usuarios virtuales por escalones (10 → 25 → 50 → 100 → MAX).
**Tu techo = el último escalón ANTES de que** los errores pasen de 1% o la
latencia p95 supere 2 s. Ese número de VUs ≈ usuarios concurrentes que aguanta.

---

## Paso 3 — Vigilar el servidor mientras corre (hPanel)

Durante el k6, mira en **hPanel → Uso de recursos**:
- **CPU** (si toca el 100% del límite → throttling).
- **Entry processes / procesos concurrentes** (si llega al tope → cola de peticiones).
- **Procesos de MySQL** (si choca el límite de conexiones → errores 500).
- **I/O**.

El recurso que tope **primero** es tu cuello de botella real.

---

## Paso 4 — Throughput de cola (lo más crítico para "miles de jobs")

La app encola RIT, preguntas, GAP, correos (y la sanción debería ir a cola también).
En Hostinger compartido **no hay un worker permanente fiable** (no hay supervisor;
los procesos largos se matan). Lo normal es un cron que corre:

```bash
php artisan queue:work --stop-when-empty --max-time=50
```
cada minuto. Eso significa que la cola drena **a ráfagas de ~50 s por minuto**, no
en tiempo real. Para medirlo en staging:

1. Encola N trabajos (genera procesos/acciones de prueba en staging).
2. Mide cuántos drena en una corrida:
   ```bash
   php artisan queue:work --stop-when-empty --max-time=50 -v
   ```
   Cuenta jobs procesados ÷ segundos = **jobs/seg de la cola**.

Si necesitas miles de jobs/minuto, la cola en BD + cron de Hostinger **no alcanza**
→ es la señal #1 de migrar a VPS con Redis + worker permanente.

---

## Paso 5 — Límite de Gemini (aparte, suave, con costo controlado)

NO lo mezcles con lo anterior. Haz una prueba pequeña (ej. 20-50 llamadas reales)
y observa: latencia por llamada, y si aparecen errores 429 (rate limit). Con eso
calculas cuántas generaciones IA/minuto te permite tu cuota actual y el costo por
documento. Ese número, no el servidor, suele ser el verdadero límite de "miles de
IA a la vez".

---

## Cómo traducir a "cuántos clientes aguanta"

Estimación gruesa (ajústala con tus datos reales de uso):

```
Concurrencia web (Paso 2)  -> usuarios navegando a la vez.
PDF/seg (Paso 1)           -> documentos por segundo.
Jobs/seg de cola (Paso 4)  -> RIT/sanciones/correos por segundo (diferidos).
Gemini /min (Paso 5)       -> generaciones IA por minuto (techo externo).
```

El **menor** de esos cuatro, comparado con tu **pico esperado de uso simultáneo**
(no el total de clientes — casi nunca todos usan a la vez), te dice si el plan
aguanta. Regla práctica: si un cliente activo dispara ~1 acción pesada cada pocos
minutos, el límite te lo marca el **pico**, no el número total de clientes.

---

## Seguridad / checklist

- [ ] Probar contra **staging**, no producción.
- [ ] Staging en **cuenta separada** si es posible; si no, **horario de bajo tráfico** y subir de a poco.
- [ ] Empezar con cargas chicas (`--pdf=20`, k6 MAX bajo) e ir subiendo.
- [ ] Detener al primer 500/timeout sostenido (ya encontraste el techo).
- [ ] No correr la prueba de Gemini en masa (cuesta $ y choca cuota).
