// Prueba de concurrencia web (escalera) para encontrar el TECHO del servidor.
//
// Sube usuarios virtuales (VUs) por etapas y mide cuándo empiezan los errores
// y la latencia se dispara. Pega rutas GET livianas (landing + login) que NO
// requieren auth: con eso medimos cuántas peticiones concurrentes aguanta el
// servidor (PHP-FPM + bootstrap de Laravel + sesión/caché en MySQL).
//
// Requiere k6 (https://k6.io/docs/get-started/installation/). Se corre DESDE TU PC
// o una VM, apuntando al CLON/STAGING — nunca a producción.
//
// Uso:
//   k6 run -e BASE_URL=https://staging.tudominio.com stress-test/k6-ramp.js
//   k6 run -e BASE_URL=https://staging.tudominio.com -e MAX=300 stress-test/k6-ramp.js

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL;
if (!BASE) { throw new Error('Falta BASE_URL. Ej: -e BASE_URL=https://staging.tudominio.com'); }

const MAX = parseInt(__ENV.MAX || '200', 10); // VUs máximos del último escalón

const errores = new Rate('errores_pct');
const ttfb = new Trend('ttfb_ms', true);

// Escalera: cada escalón mantiene la carga 1 min para estabilizar la medición.
// Si el plan revienta antes, lo verás en el escalón donde suben errores/latencia.
export const options = {
  scenarios: {
    escalera: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 10 },
        { duration: '1m',  target: 10 },
        { duration: '30s', target: 25 },
        { duration: '1m',  target: 25 },
        { duration: '30s', target: 50 },
        { duration: '1m',  target: 50 },
        { duration: '30s', target: 100 },
        { duration: '1m',  target: 100 },
        { duration: '30s', target: MAX },
        { duration: '1m',  target: MAX },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  thresholds: {
    // Objetivos: <1% de errores y p95 < 2s. Si los cruza, ese es tu techo.
    errores_pct: ['rate<0.01'],
    http_req_duration: ['p(95)<2000'],
  },
};

const rutas = [
  '/',              // landing (público)
  '/admin/login',   // login de Filament (público, GET)
];

export default function () {
  for (const ruta of rutas) {
    const res = http.get(`${BASE}${ruta}`, {
      headers: { 'User-Agent': 'CESLegal-StressTest/1.0' },
      tags: { ruta },
    });
    const ok = check(res, {
      'status 200': (r) => r.status === 200,
    });
    errores.add(!ok);
    ttfb.add(res.timings.waiting);
  }
  sleep(1); // pausa de "tiempo de lectura" del usuario
}

export function handleSummary(data) {
  const m = data.metrics;
  const get = (k, s) => (m[k] && m[k].values[s] !== undefined ? m[k].values[s] : 'n/a');
  const resumen =
    '\n================  RESUMEN  ================\n' +
    `Peticiones totales : ${get('http_reqs', 'count')}\n` +
    `Errores            : ${(get('errores_pct', 'rate') * 100).toFixed(2)}%\n` +
    `Latencia p95       : ${Number(get('http_req_duration', 'p(95)')).toFixed(0)} ms\n` +
    `Latencia p99       : ${Number(get('http_req_duration', 'p(99)')).toFixed(0)} ms\n` +
    `Req/seg (media)    : ${Number(get('http_reqs', 'rate')).toFixed(1)}\n` +
    '==========================================\n' +
    'Tu TECHO es el último escalón ANTES de que errores>1% o p95>2s.\n';
  return { stdout: resumen };
}
