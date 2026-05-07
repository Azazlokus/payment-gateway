/**
 * Общая конфигурация для всех k6 сценариев.
 *
 * Переменные окружения (задаются через -e при запуске):
 *   BASE_URL  — базовый URL (default: http://localhost:8000)
 *   API_KEY   — X-Api-Key (default: пусто)
 */

export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
export const API_KEY  = __ENV.API_KEY  || '';

/** Общие заголовки для всех запросов */
export function headers(extra = {}) {
  return {
    'Content-Type': 'application/json',
    'Accept':       'application/json',
    ...(API_KEY ? { 'X-Api-Key': API_KEY } : {}),
    ...extra,
  };
}

/**
 * Стандартные thresholds — нарушение = тест провалился.
 * Переопределяй в конкретном скрипте если нужны другие лимиты.
 */
export const defaultThresholds = {
  // 95% запросов должны завершаться быстрее 500ms
  http_req_duration: ['p(95)<500'],
  // Не более 1% ошибок
  http_req_failed:   ['rate<0.01'],
};
