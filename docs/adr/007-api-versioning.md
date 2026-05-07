# ADR-007: URL-based версионирование API (/api/v1/)

## Status
Accepted

## Context

API должен эволюционировать без поломки существующих клиентов. Варианты версионирования:

1. **URL path**: `/api/v1/payments`, `/api/v2/payments`
2. **Query string**: `/api/payments?version=1`
3. **Header**: `Accept: application/vnd.payment-gateway.v1+json`
4. **Subdomain**: `v1.api.example.com`

Некоторые эндпоинты намеренно не версионируются:
- `GET /api/health` — liveness probe; load balancer / k8s имеют захардкоженный URL
- `GET /api/metrics` — Prometheus scraper настроен на фиксированный URL, смена поломает дашборды
- `POST /api/webhook/*` — URL зарегистрирован в панели каждого провайдера; смена требует обновления у всех

## Decision

Используем **URL path версионирование** для бизнес-маршрутов: `/api/v1/`.

Инфраструктурные эндпоинты (`/health`, `/metrics`, `/webhook/*`) остаются без версии.

```php
// routes/api.php
Route::get('/health', ...);      // без версии
Route::get('/metrics', ...);     // без версии
Route::post('/webhook/*', ...);  // без версии

Route::prefix('v1')->middleware(['correlation', 'auth.api'])->group(function () {
    Route::resource('payments', ...);
    Route::resource('disputes', ...);
    // ...
});
```

При выходе v2: v1 и v2 сосуществуют, v1 получает статус Deprecated в заголовке ответа, затем удаляется после периода миграции.

## Consequences

**Плюсы:**
- Явно: разработчик сразу видит версию в URL
- Легко тестировать: `curl /api/v1/payments` vs `curl /api/v2/payments`
- Совместимо со всеми HTTP-клиентами без специальной обработки заголовков
- Load balancer может маршрутизировать `/api/v1/` и `/api/v2/` на разные сервисы

**Минусы:**
- URL "загрязняется" версией — технически URL должен идентифицировать ресурс, не версию
- При breaking change нужно поддерживать обе версии параллельно
- Header-based версионирование считается более REST-correct (но менее практично)
