// PRUEBA DEL TOPE REAL de peticiones pesadas concurrentes (descargos).
//
// Pega la ruta /__cap-test, que ocupa el proceso PHP ~3.2s (igual que un envío de
// descargos) PERO sin llamar a Gemini → cero costo. Como cada request tarda ~3.2s,
// el número de VUs ≈ peticiones concurrentes en vuelo. Cuando los VUs superan el
// límite de procesos del plan, las peticiones EMPIEZAN A ENCOLARSE (la latencia
// sube por encima de ~3.2s) y luego aparecen 503/timeout. Ese es tu tope MEDIDO.
//
// Requisitos en el servidor (descargos): CAP_TEST_ENABLED=true + CAP_TEST_TOKEN=...
// y `php artisan optimize:clear`.
//
// Uso (desde tu PC):
//   k6 run -e BASE_URL=https://descargos.ceslegal.co -e TOKEN=elsecreto stress-test/k6-cap-test.js
//   k6 run -e BASE_URL=... -e TOKEN=... -e MS=3200 -e MAX=120 stress-test/k6-cap-test.js

import http from 'k6/http';
import { check } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL;
const TOKEN = __ENV.TOKEN;
if (!BASE || !TOKEN) {
  throw new Error('Faltan BASE_URL y/o TOKEN. Ej: -e BASE_URL=https://descargos.ceslegal.co -e TOKEN=elsecreto');
}
const MS = parseInt(__ENV.MS || '3200', 10);   // duración simulada del envío
const MAX = parseInt(__ENV.MAX || '120', 10);  // VUs máximos del último escalón

const errores = new Rate('errores_pct');
const encolado = new Rate('encolado_pct'); // % de requests cuya latencia supero 1.5x la base (sintoma de cola)
const lat = new Trend('latencia_ms', true);

export const options = {
  scenarios: {
    escalera: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 5 },
        { duration: '45s', target: 15 },
        { duration: '45s', target: 25 },
        { duration: '45s', target: 40 },
        { duration: '45s', target: 60 },
        { duration: '45s', target: 90 },
        { duration: '45s', target: MAX },
        { duration: '20s', target: 0 },
      ],
      gracefulRampDown: '15s',
    },
  },
  // Sin abortOnFail: queremos llegar hasta el quiebre para verlo.
};

export default function () {
  const res = http.get(`${BASE}/__cap-test?ms=${MS}&token=${TOKEN}`, {
    timeout: '30s',
    tags: { name: 'cap-test' },
  });
  const ok = check(res, { 'status 200': (r) => r.status === 200 });
  errores.add(!ok);
  lat.add(res.timings.duration);
  // Si tardó >1.5x la base, la petición estuvo esperando en la cola de FPM.
  encolado.add(res.timings.duration > MS * 1.5);
  // sin sleep: la propia petición (~MS) marca el ritmo; cada VU = 1 request en vuelo
}

export function handleSummary(data) {
  const m = data.metrics;
  const g = (k, s) => (m[k] && m[k].values[s] !== undefined ? m[k].values[s] : 0);
  const errPct = (g('errores_pct', 'rate') * 100).toFixed(2);
  const colaPct = (g('encolado_pct', 'rate') * 100).toFixed(2);
  const p95 = Number(g('latencia_ms', 'p(95)')).toFixed(0);
  const med = Number(g('latencia_ms', 'med')).toFixed(0);

  return {
    stdout:
      '\n================  TOPE REAL (peticiones pesadas)  ================\n' +
      `Duración simulada por envío : ${MS} ms\n` +
      `Peticiones                  : ${g('http_reqs', 'count')}\n` +
      `Errores (503/timeout)       : ${errPct}%\n` +
      `Encoladas (latencia>1.5x)   : ${colaPct}%\n` +
      `Latencia mediana            : ${med} ms\n` +
      `Latencia p95                : ${p95} ms\n` +
      '=================================================================\n' +
      'Lectura: mientras latencia ≈ ' + MS + 'ms y errores≈0 → el plan absorbe esa concurrencia.\n' +
      'El escalón donde la latencia SUBE (cola) o aparecen errores = tu TOPE real de\n' +
      'peticiones pesadas concurrentes. Mira la salida en vivo para ubicar ese nivel de VUs.\n',
  };
}
