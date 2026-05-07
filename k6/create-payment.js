/**
 * k6 нагрузочный тест: POST /api/v1/payments
 *
 * Симулирует клиентов, создающих платежи.
 * YooKassa API мокируется на уровне Laravel (Http::fake) или
 * запускается против dev-стенда с настоящим sandbox-ключом.
 *
 * Запуск:
 *   k6 run k6/create-payment.js
 *   k6 run -e BASE_URL=http://staging.example.com -e API_KEY=secret k6/create-payment.js
 *
 * Интерпретация результатов:
 *   http_req_duration p(95) — 95% запросов укладываются в это время
 *   http_req_failed rate    — доля упавших запросов (4xx/5xx)
 *   iterations              — общее число создания платежей
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { BASE_URL, headers, defaultThresholds } from './config.js';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

// Кастомные метрики
const paymentCreated  = new Counter('payment_created');
const paymentFailed   = new Counter('payment_failed');
const creationLatency = new Trend('payment_creation_latency_ms', true);

export const options = {
  scenarios: {
    // Этап 1: Плавный разогрев (30с → 10 VU)
    warmup: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 10 },
      ],
      gracefulRampDown: '10s',
      tags: { phase: 'warmup' },
    },
    // Этап 2: Основная нагрузка (10 VU на 2 минуты)
    sustained: {
      executor: 'constant-vus',
      vus: 10,
      duration: '2m',
      startTime: '30s',
      tags: { phase: 'sustained' },
    },
    // Этап 3: Пиковый всплеск (50 VU на 30с)
    spike: {
      executor: 'ramping-vus',
      startVUs: 10,
      stages: [
        { duration: '10s', target: 50 },
        { duration: '30s', target: 50 },
        { duration: '10s', target: 10 },
      ],
      startTime: '2m 30s',
      tags: { phase: 'spike' },
    },
  },

  thresholds: {
    ...defaultThresholds,
    // Для создания платежей — допустим чуть более медленный p(95)
    'http_req_duration{scenario:sustained}': ['p(95)<800'],
    'http_req_duration{scenario:spike}':     ['p(95)<1500'],
    // Ни одного failed во время warmup
    'http_req_failed{phase:warmup}':         ['rate==0'],
  },
};

export default function () {
  const idempotencyKey = uuidv4();

  const payload = JSON.stringify({
    amount:      Math.floor(Math.random() * 90000) + 10000, // 100–1000 руб в копейках
    description: `Load test payment ${idempotencyKey.slice(0, 8)}`,
    return_url:  'https://example.com/payment/success',
    metadata:    { source: 'k6', run_id: __ENV.K6_RUN_ID || 'local' },
  });

  const res = http.post(
    `${BASE_URL}/api/v1/payments`,
    payload,
    {
      headers: headers({ 'Idempotency-Key': idempotencyKey }),
      tags:    { name: 'create_payment' },
    }
  );

  creationLatency.add(res.timings.duration);

  const ok = check(res, {
    'status is 201':        (r) => r.status === 201,
    'has payment id':       (r) => JSON.parse(r.body)?.data?.id !== undefined,
    'has confirmation_url': (r) => JSON.parse(r.body)?.data?.confirmation_url !== undefined,
    'response time < 1s':   (r) => r.timings.duration < 1000,
  });

  if (ok) {
    paymentCreated.add(1);
  } else {
    paymentFailed.add(1);
  }

  sleep(0.5); // небольшая пауза между итерациями одного VU
}

export function handleSummary(data) {
  const p95 = data.metrics['http_req_duration']?.values?.['p(95)'];
  const rps  = data.metrics['http_reqs']?.values?.rate;
  const fail = (data.metrics['http_req_failed']?.values?.rate * 100).toFixed(2);

  console.log(`\n=== Create Payment Summary ===`);
  console.log(`  RPS:        ${rps?.toFixed(1)} req/s`);
  console.log(`  p(95):      ${p95?.toFixed(0)} ms`);
  console.log(`  Error rate: ${fail}%`);
  console.log(`  Created:    ${data.metrics['payment_created']?.values?.count}`);
  console.log(`  Failed:     ${data.metrics['payment_failed']?.values?.count}`);

  return {}; // не пишем файлы, только stdout
}
