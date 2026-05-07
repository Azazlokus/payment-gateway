/**
 * k6 нагрузочный тест: GET /api/v1/payments (cursor pagination)
 *
 * Симулирует операторов, листающих список платежей в дашборде.
 * Тестирует cursor-based пагинацию под нагрузкой.
 *
 * Запуск:
 *   k6 run k6/list-payments.js
 *   k6 run -e BASE_URL=http://staging.example.com -e API_KEY=secret k6/list-payments.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { BASE_URL, headers, defaultThresholds } from './config.js';

const paginationErrors = new Counter('pagination_errors');

export const options = {
  scenarios: {
    // Умеренная нагрузка — дашборд читают операторы, не тысячи клиентов
    operators: {
      executor: 'constant-vus',
      vus:      20,
      duration: '3m',
    },
  },

  thresholds: {
    ...defaultThresholds,
    // Список должен отдаваться быстро — индекс по (created_at, id)
    'http_req_duration': ['p(95)<300', 'p(99)<600'],
  },
};

export default function () {
  // Страница 1
  const page1 = http.get(
    `${BASE_URL}/api/v1/payments?per_page=20`,
    { headers: headers(), tags: { name: 'list_page_1' } }
  );

  const ok1 = check(page1, {
    'list status 200':       (r) => r.status === 200,
    'has data array':        (r) => Array.isArray(JSON.parse(r.body)?.data),
    'has next_cursor field': (r) => 'next_cursor' in (JSON.parse(r.body) ?? {}),
    'response time < 300ms': (r) => r.timings.duration < 300,
  });

  if (!ok1) {
    paginationErrors.add(1);
    return;
  }

  const body1      = JSON.parse(page1.body);
  const nextCursor = body1.next_cursor;

  // Если есть следующая страница — запрашиваем её
  if (nextCursor) {
    sleep(0.3); // имитируем время прокрутки пользователем

    const page2 = http.get(
      `${BASE_URL}/api/v1/payments?per_page=20&cursor=${encodeURIComponent(nextCursor)}`,
      { headers: headers(), tags: { name: 'list_page_2' } }
    );

    check(page2, {
      'page 2 status 200':    (r) => r.status === 200,
      'page 2 has data':      (r) => Array.isArray(JSON.parse(r.body)?.data),
      'page 2 time < 300ms':  (r) => r.timings.duration < 300,
    });
  }

  // Фильтрация по статусу (частый паттерн в дашборде)
  const statuses = ['Pending', 'Succeeded', 'Cancelled', 'Refunded'];
  const status   = statuses[Math.floor(Math.random() * statuses.length)];

  http.get(
    `${BASE_URL}/api/v1/payments?status=${status}&per_page=20`,
    { headers: headers(), tags: { name: 'list_filtered' } }
  );

  sleep(1); // оператор изучает список ~1с перед следующим действием
}
