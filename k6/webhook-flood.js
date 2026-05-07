/**
 * k6 нагрузочный тест: POST /api/webhook/{provider}
 *
 * Имитирует всплеск вебхуков от провайдера (например, после маркетинговой акции
 * когда тысячи платежей завершаются почти одновременно).
 *
 * ВАЖНО: используй только против dev/staging стенда.
 * Реальные подписи не проверяются — нужно отключить IP/HMAC верификацию в .env:
 *   WEBHOOK_VERIFY_IP=false
 *   WEBHOOK_VERIFY_SIGNATURE=false
 *
 * Запуск:
 *   k6 run k6/webhook-flood.js
 *   k6 run -e PROVIDER=robokassa k6/webhook-flood.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';
import { BASE_URL, headers } from './config.js';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const webhookAccepted = new Counter('webhook_accepted');
const webhookRejected = new Counter('webhook_rejected');
const rateLimited     = new Rate('webhook_rate_limited');

const PROVIDER = __ENV.PROVIDER || 'yookassa';

// Генераторы payload для разных провайдеров
const payloadGenerators = {
  yookassa: (externalId) => JSON.stringify({
    type:   'notification',
    event:  'payment.succeeded',
    object: {
      id:     externalId,
      status: 'succeeded',
      amount: { value: '500.00', currency: 'RUB' },
      paid:   true,
    },
  }),

  robokassa: (externalId) => {
    const params = new URLSearchParams({
      InvId:       String(Math.floor(Math.random() * 1000000)),
      OutSum:      '500.00',
      SignatureValue: 'test_signature', // верификация должна быть отключена
    });

    return params.toString();
  },

  cloudpayments: (externalId) => JSON.stringify({
    TransactionId: Math.floor(Math.random() * 1000000),
    Amount:        500.0,
    Currency:      'RUB',
    Status:        'Completed',
    InvoiceId:     externalId,
  }),

  sbp: (externalId) => JSON.stringify({
    event:      'PAYMENT_SUCCEEDED',
    paymentId:  externalId,
    amount:     50000,
    currency:   'RUB',
    status:     'SUCCESS',
  }),
};

export const options = {
  scenarios: {
    // Нормальный поток вебхуков
    normal: {
      executor: 'constant-arrival-rate',
      rate:     20,        // 20 вебхуков в секунду
      timeUnit: '1s',
      duration: '1m',
      preAllocatedVUs: 10,
      maxVUs: 50,
      tags: { phase: 'normal' },
    },
    // Всплеск (тест rate limiting)
    burst: {
      executor: 'constant-arrival-rate',
      rate:     100,       // 100 вебхуков в секунду (выше rate limit)
      timeUnit: '1s',
      duration: '30s',
      startTime: '1m',
      preAllocatedVUs: 50,
      maxVUs: 200,
      tags: { phase: 'burst' },
    },
  },

  thresholds: {
    // Во время нормальной фазы — никаких ошибок
    'http_req_failed{phase:normal}': ['rate<0.01'],
    // Во время всплеска допускается rate limiting (429) — это нормально
    'http_req_duration{phase:normal}': ['p(95)<200'],
  },
};

export default function () {
  const externalId = uuidv4();
  const generator  = payloadGenerators[PROVIDER] ?? payloadGenerators.yookassa;
  const body       = generator(externalId);

  const isForm     = PROVIDER === 'robokassa';
  const contentType = isForm ? 'application/x-www-form-urlencoded' : 'application/json';

  const res = http.post(
    `${BASE_URL}/api/webhook/${PROVIDER}`,
    body,
    {
      headers: { 'Content-Type': contentType },
      tags:    { name: `webhook_${PROVIDER}` },
    }
  );

  if (res.status === 429) {
    rateLimited.add(1);
    webhookRejected.add(1);

    check(res, {
      '429 has Retry-After header': (r) => r.headers['Retry-After'] !== undefined,
      '429 body is JSON':           (r) => {
        try { JSON.parse(r.body); return true; } catch { return false; }
      },
    });

    return;
  }

  const ok = check(res, {
    'webhook accepted (2xx)': (r) => r.status >= 200 && r.status < 300,
    'response time < 100ms':  (r) => r.timings.duration < 100,
  });

  if (ok) {
    webhookAccepted.add(1);
  } else {
    webhookRejected.add(1);
  }
}

export function handleSummary(data) {
  const accepted    = data.metrics['webhook_accepted']?.values?.count ?? 0;
  const rejected    = data.metrics['webhook_rejected']?.values?.count ?? 0;
  const rateLimitedRate = (data.metrics['webhook_rate_limited']?.values?.rate * 100 ?? 0).toFixed(1);
  const p95         = data.metrics['http_req_duration']?.values?.['p(95)']?.toFixed(0);

  console.log(`\n=== Webhook Flood Summary (provider: ${PROVIDER}) ===`);
  console.log(`  Accepted:     ${accepted}`);
  console.log(`  Rejected:     ${rejected}`);
  console.log(`  Rate limited: ${rateLimitedRate}%`);
  console.log(`  p(95) latency: ${p95} ms`);

  return {};
}
