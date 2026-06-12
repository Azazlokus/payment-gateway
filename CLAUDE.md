# Payment Gateway — CLAUDE.md

## Команды

```bash
# Тесты
php vendor/bin/phpunit --testsuite=Unit
php vendor/bin/phpunit --testsuite=Feature
php vendor/bin/phpunit                        # все тесты

# Статический анализ
./vendor/bin/phpstan analyse --memory-limit=512M

# Архитектурные зависимости (DDD layers)
php vendor/bin/deptrac analyse                # проверить
php vendor/bin/deptrac analyse --formatter=json  # JSON для CI

# Стиль кода
./vendor/bin/pint                             # исправить
./vendor/bin/pint --test                      # только проверить

# Docker
docker compose up -d                          # поднять все сервисы
docker compose exec app php artisan migrate   # миграции

# Frontend (Vue 3)
npm run dev                                   # dev-сервер Vite
npm run build                                 # production build
```

## Архитектура

Проект построен по **Clean Architecture + DDD**.
Bounded contexts вынесены в `app/Contexts/`, отделены от фреймворка:

```
app/
├── Contexts/                              # ← DDD bounded contexts
│   ├── Payments/
│   │   ├── Domain/                        # Чистая бизнес-логика, без зависимостей от фреймворка
│   │   │   ├── Aggregates/Payment.php     # Главный агрегат (final class)
│   │   │   ├── Contracts/                 # PaymentProviderInterface и др.
│   │   │   ├── ValueObjects/Money.php     # Сумма всегда в копейках (int)
│   │   │   └── Events/                    # Доменные события (чистый PHP)
│   │   ├── Application/                   # Use cases, командный bus, DTO
│   │   │   ├── Bus/CommandBus.php         # Pipeline: Validate → Idempotency → Handle
│   │   │   └── Commands/                  # CreatePayment, RefundPayment, CancelPayment, SyncPayment
│   │   ├── Infrastructure/                # Адаптеры к внешним системам
│   │   │   ├── Providers/                 # YooKassa, Robokassa, CloudPayments, SBP, AlfaBank
│   │   │   ├── Jobs/                      # ProcessXxxWebhookJob (ShouldQueue, 5 попыток)
│   │   │   ├── Observability/             # PaymentLogger, MetricsService, NotificationService
│   │   │   └── Persistence/               # Eloquent модели + Repository
│   │   └── Presentation/                  # HTTP-слой (controllers, requests, resources)
│   └── CryptoPayments/                    # Крипто-платежи (аналогичная структура)
│       ├── Domain/
│       ├── Application/
│       ├── Infrastructure/
│       └── Presentation/
├── Console/                               # Laravel artisan-команды
├── Providers/                             # Service providers
├── PaymentLinks/                          # Legacy модуль
└── Http/                                  # Framework middleware
```

Архитектурные зависимости проверяются **deptrac** (`deptrac.yaml`).
Правило: Domain ← Application ← Infrastructure/Presentation. Новый код должен проходить `deptrac analyse` без violations.

## Провайдеры

| Провайдер     | Верификация вебхука   | Вебхук URL           | Особенности                          |
|---------------|-----------------------|----------------------|--------------------------------------|
| YooKassa      | IP CIDR               | POST /webhook/yookassa | Рекуррентные платежи, чеки 54-ФЗ  |
| Robokassa     | IP CIDR + MD5 подпись | POST /webhook/robokassa | form POST, ответ `OK{InvId}`       |
| CloudPayments | HMAC-SHA256           | POST /webhook/cloudpayments | JSON, ответ `{code:0}`       |
| СБП           | X-Api-Key header      | POST /webhook/sbp    | QR-коды через НСПК API               |
| Альфа-Банк    | IP CIDR + поля        | POST /webhook/alfabank | form POST                          |

## Ключевые правила

- **Деньги только в копейках**: `Money::ofRub(10000)` = 100 RUB. Никогда не используй float для денег.
- **PHPStan level 6**: все новые файлы должны проходить без ошибок. Не добавляй `@phpstan-ignore`.
- **Laravel Pint**: стиль кода проверяется в CI. Запускай `./vendor/bin/pint` перед коммитом.
- **Идемпотентность**: создание и рефанды защищены через `Idempotency-Key` заголовок.
- **Джобы вебхуков**: 5 попыток, экспоненциальный backoff (10s → 30s → 60s → 120s → 300s). При исчерпании — Slack алерт.
- **`Payment` — final class**: агрегат намеренно запечатан.

## Тестирование

- `tests/Unit/` — чистые unit-тесты без БД (Mockery для зависимостей, Http::fake() для HTTP)
- `tests/Feature/` — интеграционные тесты с SQLite in-memory + Redis
- Вебхук тесты проверяют: HTTP-слой (dispatch/reject), job processing (статусы), идемпотентность

## Observability

- **Метрики**: `GET /api/metrics` — Prometheus text format из Redis
- **Grafana**: `http://localhost:3000` (docker compose)
- **Prometheus**: `http://localhost:9090`
- **Horizon**: `http://localhost/horizon` — мониторинг очередей

## Frontend (Vue 3 SPA)

```
resources/js/
├── App.vue                  # root компонент с навигацией
├── router/index.js          # vue-router (history mode)
├── api/payments.js          # axios wrapper для /api/payments
├── components/              # StatusBadge, LoadingSpinner, AlertMessage
└── pages/
    ├── DashboardPage.vue    # список платежей с фильтрами
    ├── CreatePaymentPage.vue # форма создания
    └── PaymentDetailPage.vue # детали, отмена, возврат
```

SPA отдаётся через `routes/web.php` → `resources/views/spa.blade.php`.
Все `/api/*` маршруты обрабатываются Laravel, остальное — Vue Router.
