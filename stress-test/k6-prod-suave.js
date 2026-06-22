// PRUEBA SUAVE PARA PRODUCCIÓN (Camino B) — descargos.ceslegal.co
//
// Objetivo: confirmar que el sitio aguanta carga MODERADA sin caerse, SIN buscar
// el punto de quiebre (eso solo se hace en staging). Tope bajo (máx 20 VUs),
// duración corta (~4 min) y AUTO-ABORTO si los errores o la latencia se disparan,
// para proteger a los clientes reales.
//
// Solo pega rutas GET públicas (landing + login). NO dispara IA, RIT ni sanciones.
//
// Requiere k6 (https://k6.io/docs/get-started/installation/). Córrelo DESDE TU PC,
// en horario de bajo tráfico (madrugada).
//
// Uso:
//   k6 run stress-test/k6-prod-suave.js
//   k6 run -e BASE_URL=https://descargos.ceslegal.co stress-test/k6-prod-suave.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'https://descargos.ceslegal.co';

const errores = new Rate('errores_pct');
const ttfb = new Trend('ttfb_ms', true);

export const options = {
  scenarios: {
    suave: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 5 },
        { duration: '45s', target: 10 },
        { duration: '45s', target: 15 },
        { duration: '60s', target: 20 },
        { duration: '30s', target: 20 },
        { duration: '20s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  // Si los errores pasan de 2% o la latencia p95 de 3s, k6 ABORTA la prueba sola
  // (delayAbortEval da 10s de gracia para no abortar por un pico puntual).
  thresholds: {
    errores_pct: [{ threshold: 'rate<0.02', abortOnFail: true, delayAbortEval: '10s' }],
    http_req_duration: [{ threshold: 'p(95)<3000', abortOnFail: true, delayAbortEval: '10s' }],
  },
};

const rutas = ['/', '/admin/login'];

export default function () {
  for (const ruta of rutas) {
    const res = http.get(`${BASE}${ruta}`, {
      headers: { 'User-Agent': 'CESLegal-StressTest/1.0 (prueba autorizada)' },
      tags: { ruta },
    });
    const ok = check(res, { 'status 200': (r) => r.status === 200 });
    errores.add(!ok);
    ttfb.add(res.timings.waiting);
  }
  sleep(2); // pausa de lectura (más gentil que el test de staging)
}

export function handleSummary(data) {
  const m = data.metrics;
  const g = (k, s) => (m[k] && m[k].values[s] !== undefined ? m[k].values[s] : 0);
  const errPct = (g('errores_pct', 'rate') * 100).toFixed(2);
  const veredicto = errPct < 1 && g('http_req_duration', 'p(95)') < 2000
    ? 'OK: el sitio aguantó 20 usuarios concurrentes sin problema.'
    : 'ATENCIÓN: hubo errores o lentitud — el plan está cerca de su límite con poca carga.';
  return {
    stdout:
      '\n================  RESUMEN (PROD SUAVE)  ================\n' +
      `Peticiones   : ${g('http_reqs', 'count')}\n` +
      `Errores      : ${errPct}%\n` +
      `Latencia p95 : ${Number(g('http_req_duration', 'p(95)')).toFixed(0)} ms\n` +
      `Req/seg      : ${Number(g('http_reqs', 'rate')).toFixed(1)}\n` +
      `VUs máx      : 20\n` +
      '======================================================\n' +
      veredicto + '\n',
  };
}
