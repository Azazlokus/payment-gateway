# Payment Gateway

REST API платёжного шлюза на Laravel 13 с поддержкой нескольких провайдеров, асинхронной обработкой вебхуков через Horizon и полным покрытием тестами.

## Содержание

- [Возможности](#возможности)
- [Стек технологий](#стек-технологий)
- [Структура репозитория](#структура-репозитория)
- [Архитектура](#архитектура)
- [Быстрый старт](#быстрый-старт)
- [Конфигурация](#конфигурация)
- [Безопасность](#безопасность)
- [API v1](#api-v1)
- [Провайдеры](#провайдеры)
- [Вебхуки](#вебхуки)
- [Крипто-депозиты (TON / USDT-TON / TRX / USDT-TRC20 / BTC)](#крипто-депозиты)
- [Диспуты и чарджбэки](#диспуты-и-чарджбэки)
- [Очереди и Horizon](#очереди-и-horizon)
- [Деплой и Graceful Shutdown](#деплой-и-graceful-shutdown)
- [Observability](#observability)
  - [ELK Stack (опционально)](#elk-stack-опционально)
- [Тесты](#тесты)
- [CI/CD](#cicd)
- [Makefile](#makefile)
- [Структура БД](#структура-бд)

---

## Возможности

- Создание платежей через **5 провайдеров**: YooKassa, Robokassa, CloudPayments, СБП, Альфа-Банк
- Частичные и полные **возвраты** с кумулятивным трекингом суммы
- **Рекуррентные платежи** (YooKassa): сохранение метода оплаты и списание без редиректа
- **Чеки 54-ФЗ** (YooKassa): передача позиций, налоговых кодов, данных покупателя
- **QR-коды СБП**: динамические QR через НСПК API
- **3-D Secure**: событие `PaymentRequiresThreeDSecure`, поля `three_ds_required` / `three_ds_challenge_url`
- **Диспуты / чарджбэки**: агрегат `Dispute` со статусами Filed → Won / Lost
- **Крипто-депозиты** (TON / USDT-TON / TRX / USDT-TRC20 / BTC): приём оплаты в 3 блокчейнах, только бесплатные API
- **Идемпотентность** создания и возврата по заголовку `Idempotency-Key`
- Асинхронная обработка вебхуков (Horizon + Redis), 5 попыток с экспоненциальным backoff
- Structured logging с Correlation ID, audit trail через `spatie/laravel-activitylog`
- IP-фильтрация вебхуков по официальным CIDR; HMAC-SHA256 (CloudPayments), X-Api-Key (СБП)
- Алерты в **Slack** при исчерпании попыток обработки вебхука
- **Стандартизированные ошибки**: единый формат `{code, message, trace_id}`
- **Версионирование API**: все бизнес-маршруты под `/api/v1/`
- **Prometheus метрики** + **Grafana** дашборды
- **PHPStan level 7** + **Laravel Pint** в CI
- **Vue 3 SPA** фронтенд: дашборд, создание платежей, детали, крипто-депозиты

---

## Стек технологий

| Слой | Технология |
|---|---|
| Framework | Laravel 13, PHP 8.4 |
| База данных | PostgreSQL 16 |
| Очереди / кэш | Redis 7, Laravel Horizon |
| Веб-сервер | Nginx (reverse proxy) |
| Фронтенд | Vue 3, Vite, Tailwind CSS |
| Документация | l5-swagger (OpenAPI 3.0) |
| Тесты | PHPUnit 11 |
| Статический анализ | PHPStan level 7 (Larastan) |
| Стиль кода | Laravel Pint |
| Observability | Prometheus + Grafana |
| Контейнеры | Docker, Docker Compose |

---

## Структура репозитория

Монорепозиторий: backend и frontend живут в отдельных папках и деплоятся как независимые Docker-контейнеры.

```
payment-gateway/
├── backend/                  # Laravel 13 (PHP 8.4)
│   ├── app/
│   │   ├── Payments/         # Bounded context: платежи, диспуты
│   │   └── CryptoPayments/   # Bounded context: TON / USDT-TON депозиты
│   ├── config/
│   ├── database/migrations/
│   ├── routes/
│   │   └── api.php           # Все API маршруты (v1 + unversioned)
│   └── tests/
│       ├── Unit/             # PHPUnit без БД (Mockery + Http::fake)
│       └── Feature/          # SQLite in-memory + Redis
│
├── frontend/                 # Vue 3 SPA (Vite + Tailwind)
│   └── src/
│       ├── api/payments.js   # axios wrapper → /api/v1/
│       ├── pages/
│       │   ├── DashboardPage.vue
│       │   ├── CreatePaymentPage.vue
│       │   ├── PaymentDetailPage.vue
│       │   ├── CryptoDepositPage.vue
│       │   └── MetricsDashboardPage.vue
│       └── router/index.js
│
├── docker/
│   ├── nginx/default.conf    # reverse proxy для backend
│   └── frontend/nginx.conf   # статика Vue SPA
│
├── docker-compose.yml
└── Makefile
```

---

## Архитектура

Проект построен по принципам **Clean Architecture + DDD**. Каждый bounded context полностью независим.

### Bounded context `Payments`

```
app/Payments/
├── Domain/
│   ├── Aggregates/
│   │   ├── Payment.php       # Главный агрегат (final): статусы, возвраты, 3DS
│   │   └── Dispute.php       # Агрегат диспута: Filed → Won / Lost
│   ├── Contracts/            # PaymentProviderInterface, PaymentRepositoryInterface, ...
│   ├── Enums/                # PaymentStatus, DisputeStatus, Currency
│   ├── Events/               # PaymentWas*, PaymentRequiresThreeDSecure, DisputeWas*
│   ├── Exceptions/           # InvalidPaymentStateException (409), WebhookVerificationFailedException (403), ...
│   └── ValueObjects/         # Money (копейки), PaymentId (ULID), DisputeId, ...
│
├── Application/
│   ├── Bus/CommandBus.php    # Pipeline: Validate → Idempotency → Log → Handle
│   └── Commands/             # CreatePayment, CancelPayment, RefundPayment, SyncPayment
│
├── Infrastructure/
│   ├── Jobs/                 # ProcessXxxWebhookJob (ShouldQueue, 5 попыток)
│   ├── Observability/        # PaymentLogger, MetricsService, NotificationService
│   ├── Persistence/          # EloquentPaymentRepository, EloquentDisputeRepository
│   └── Providers/            # YooKassa, Robokassa, CloudPayments, SBP, AlfaBank
│
└── Presentation/Http/
    ├── Controllers/          # PaymentController, DisputeController, WebhookControllers, ...
    ├── Requests/             # CreatePaymentRequest, RefundPaymentRequest
    └── Resources/            # PaymentResource
```

### Bounded context `CryptoPayments`

```
app/CryptoPayments/
├── Domain/
│   ├── Aggregates/CryptoDeposit.php       # Awaiting → Confirmed / Overpaid / Expired
│   ├── Contracts/                          # BlockchainClientInterface, PriceOracleInterface
│   ├── Enums/                              # CryptoAsset (TON, USDT_TON), CryptoDepositStatus
│   ├── Events/                             # DepositAwaitingPayment, DepositConfirmed, ...
│   └── ValueObjects/                       # TonAddress, Memo, NativeCryptoAmount, TxHash
│
├── Application/
│   ├── ACL/CryptoDepositToPaymentAdapter.php   # Anti-corruption layer → Payments context
│   └── Commands/CreateCryptoDeposit/
│
├── Infrastructure/
│   ├── Blockchain/
│   │   ├── TonBlockchainClient.php         # TON via v2 /getTransactions
│   │   │                                   # USDT-TON via v3 /jetton/transfers
│   │   └── BlockchainClientRegistry.php
│   ├── Jobs/
│   │   ├── PollCryptoDepositsJob.php       # каждые 15 сек — опрос блокчейна
│   │   └── ExpireCryptoDepositsJob.php     # каждую минуту — экспирация
│   ├── Pricing/CoinGeckoPriceOracle.php    # RUB → TON/USDT конвертация
│   └── Persistence/EloquentCryptoDepositRepository.php
│
└── Presentation/Http/Controllers/CryptoDepositController.php
```

### Жизненный цикл платежа

```
POST /api/v1/payments
    → CommandBus (Validate → Idempotency → Log)
    → CreatePaymentHandler
        → Payment::create()           # агрегат, status=Pending
        → provider->createPayment()   # запрос к провайдеру
        → repository->save()
    ← PaymentResultDTO { id, status, confirmation_url, ... }

Клиент переходит по confirmation_url → оплачивает → провайдер шлёт webhook

POST /api/webhook/{provider}
    → verifyWebhook()                 # IP / HMAC / X-Api-Key
    → ProcessWebhookJob::dispatch()   # очередь Horizon
    ← 200 OK (немедленно)

ProcessWebhookJob (async)
    → payment->markAsSucceeded() / cancel() / refund()
    → repository->save() + activity log
```

### Состояния платежа

```
Pending ──→ Succeeded ──→ Refunded  (частичный возврат: Succeeded до полной суммы)
   │
   └──→ Cancelled
```

---

## Быстрый старт

### Требования

- Docker и Docker Compose
- Make (опционально)

### Установка

```bash
git clone <repo-url> && cd payment-gateway

# Backend
cp backend/.env.example backend/.env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

# Frontend (опционально, отдельный контейнер)
docker compose --profile frontend up -d frontend
```

Сервисы после запуска:

| Сервис | URL |
|---|---|
| API v1 | http://localhost:8000/api/v1 |
| Vue SPA | http://localhost:3080 |
| Swagger UI | http://localhost:8000/api/documentation |
| Horizon dashboard | http://localhost:8000/horizon |
| Adminer (БД) | http://localhost:8080 |
| Grafana | http://localhost:3000 |
| Prometheus | http://localhost:9090 |

---

## Конфигурация

### Основные переменные окружения

```dotenv
# Безопасность API
API_KEY=                            # X-Api-Key для /api/v1/*. Пустая строка — проверка отключена

# Платёжные провайдеры
PAYMENT_PROVIDER=yookassa

YOOKASSA_SHOP_ID=100500
YOOKASSA_SECRET_KEY=test_xxxxx

ROBOKASSA_LOGIN=your_login
ROBOKASSA_PASSWORD1=your_password1
ROBOKASSA_PASSWORD2=your_password2
ROBOKASSA_IS_TEST=true

CLOUDPAYMENTS_PUBLIC_ID=pk_xxxxx
CLOUDPAYMENTS_API_SECRET=your_secret

SBP_MERCHANT_ID=your_merchant
SBP_API_KEY=your_api_key
SBP_WEBHOOK_SECRET=your_secret

ALFABANK_LOGIN=your_login
ALFABANK_PASSWORD=your_password

# Крипто-депозиты
CRYPTO_DEPOSIT_TTL_MINUTES=20

TON_MASTER_ADDRESS=UQA...           # единый адрес для TON / USDT-TON
TON_API_KEY=                        # TonCenter API key (опционально)
TON_USDT_JETTON_MASTER=EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs
TON_HOT_WALLET_MNEMONIC=            # 24 слова для рефандов TON/USDT-TON (требует olifanton/ton)

BTC_DEPOSIT_ADDRESSES=bc1q...       # через запятую
TRON_DEPOSIT_ADDRESSES=T...         # через запятую
TRONGRID_API_KEY=                   # TronGrid API key (опционально)
TRON_HOT_WALLET_PRIVATE_KEY=        # hex private key для рефандов TRX/USDT-TRC20

# Observability
SLACK_WEBHOOK_URL=https://hooks.slack.com/...
GRAFANA_USER=admin
GRAFANA_PASSWORD=secret
```

---

## Безопасность

### API Key Authentication

Все эндпоинты `/api/v1/*` защищены заголовком `X-Api-Key`.

| Заголовок | Описание |
|---|---|
| `X-Api-Key` | Ключ доступа к API. Устанавливается через переменную окружения `API_KEY`. |

- Если `API_KEY` не задан (пустая строка) — проверка отключена (режим разработки, все запросы проходят).
- При неверном или отсутствующем ключе возвращается `401 Unauthorized`:
  ```json
  { "code": "unauthorized", "message": "Invalid or missing API key" }
  ```
- Вебхук-маршруты (`/api/webhook/*`), `/api/health` и `/api/metrics` не требуют `X-Api-Key`.

Пример запроса:
```bash
curl -H "X-Api-Key: your-secret-key" http://localhost:8000/api/v1/payments
```

### Webhook Replay Protection

CloudPayments вебхуки защищены от повторной отправки (replay attacks):

- **Заголовок `X-Request-Id`** используется как одноразовый nonce. Повторный запрос с тем же nonce отклоняется с кодом `webhook_verification_failed`.
- **Временно́е окно**: timestamp из поля `DateTimeUTC` проверяется — запросы старше 5 минут или из будущего отклоняются.
- Использованные nonce хранятся в Redis с TTL 10 минут.

### Audit Log

Все мутации платежей и диспутов записываются в таблицу `activity_log`:

| Событие | Описание |
|---|---|
| `payment.created` | Создание платежа |
| `payment.cancelled` | Отмена платежа |
| `payment.refunded` | Возврат платежа |
| `dispute.filed` | Открытие диспута |
| `dispute.resolved` | Разрешение диспута (Won / Lost) |

---

## API v1

### Базовый URL

```
http://localhost:8000/api/v1
```

Все бизнес-маршруты версионированы. Инфраструктурные эндпоинты (`/health`, `/metrics`, `/webhook/*`) — без версии, т.к. Prometheus и провайдеры используют фиксированные URL.

### Заголовки

| Заголовок | Описание |
|---|---|
| `X-Correlation-Id` | Передаётся в структурированные логи как `trace_id`. Если не указан — генерируется автоматически |
| `Idempotency-Key` | UUID. Защищает `POST /payments` и `POST /payments/{id}/refund` от двойного исполнения |

### Эндпоинты — Платежи

#### `GET /api/v1/payments`

Список платежей с пагинацией.

| Параметр | Тип | Описание |
|---|---|---|
| `status` | string | `Pending` / `Succeeded` / `Cancelled` / `Refunded` |
| `provider` | string | `yookassa` / `robokassa` / `cloudpayments` / `sbp` / `alfabank` |
| `from_date` | date | Дата от (Y-m-d) |
| `to_date` | date | Дата до (Y-m-d) |
| `per_page` | int | 1–100, default: 15 |
| `page` | int | Номер страницы |

#### `GET /api/v1/payments/export`

CSV-экспорт платежей (streaming). Throttle: 10 запросов/мин.

#### `POST /api/v1/payments`

Создать платёж.

```json
{
  "provider":    "yookassa",
  "amount":      10000,
  "currency":    "RUB",
  "description": "Оплата заказа №1234",
  "return_url":  "https://example.com/payment/success",
  "metadata":    { "order_id": "1234" },

  // Опционально — YooKassa
  "save_payment_method": false,
  "payment_method_id":   "saved-method-uuid",

  // Чек 54-ФЗ — YooKassa
  "receipt": {
    "customer": { "email": "user@example.com" },
    "items": [{
      "description": "Товар",
      "quantity": 1,
      "amount": 10000,
      "vat_code": 1
    }]
  }
}
```

#### `GET /api/v1/payments/{id}`

Получить платёж по ULID.

#### `POST /api/v1/payments/{id}/cancel`

Отменить. Только статус `Pending` → `409` при терминальном.

#### `POST /api/v1/payments/{id}/refund`

```json
{ "amount": 5000, "reason": "Возврат по заявке" }
```

Если `amount` не указан — полный возврат. Частичные возвраты аккумулируются.

#### `POST /api/v1/payments/{id}/sync`

Синхронизировать статус с провайдером.

#### `POST /api/v1/payments/{id}/retry`

Повторить создание платежа у провайдера (при сбое на стороне провайдера).

#### `POST /api/v1/payments/{id}/resync`

Принудительная синхронизация через job (асинхронно).

---

### Эндпоинты — Диспуты

#### `GET /api/v1/payments/{id}/disputes`

Список диспутов по платежу.

#### `POST /api/v1/payments/{id}/disputes`

Открыть диспут.

```json
{ "amount": 50000, "reason": "Товар не получен" }
```

#### `GET /api/v1/disputes/{id}`

Получить диспут по ID.

#### `POST /api/v1/disputes/{id}/resolve`

Разрешить диспут.

```json
{ "resolution": "Won", "note": "Доставка подтверждена треком" }
```

`resolution`: `Won` (победа мерчанта) / `Lost`.

---

### Эндпоинты — Крипто-депозиты

#### `POST /api/v1/crypto/deposits`

Создать крипто-депозит. Возвращает адрес и memo для перевода.

```json
{
  "payment_id":          "order-1234",
  "fiat_amount_kopecks": 50000,
  "asset":               "TON"
}
```

`asset`: `TON` или `USDT_TON`. Минимум `fiat_amount_kopecks`: 100 (1 рубль).

**Ответ:**

```json
{
  "depositId":          "01HXXXXX...",
  "paymentId":          "order-1234",
  "status":             "awaiting",
  "asset":              "TON",
  "expectedUnits":      125000000,
  "cryptoAmount":       "0.125000000",
  "fiatAmountKopecks":  50000,
  "depositAddress":     "UQA...",
  "memo":               "748291836",
  "expiresAt":          "2026-04-21T15:30:00+00:00",
  "qrPayload":          "ton://transfer/UQA...?amount=125000000&text=748291836",
  "txHash":             null
}
```

Клиент переводит ровно `expectedUnits` с комментарием `memo` на `depositAddress`. Поллинг — каждые 15 сек через `GET /deposits/{id}`.

#### `GET /api/v1/crypto/deposits/{id}`

Статус депозита. Когда `status` станет `confirmed` — оплата зачтена.

#### `POST /api/v1/crypto/deposits/{id}/refund`

Запросить возврат подтверждённого депозита на указанный адрес.

```json
{ "to_address": "UQA..." }
```

**Ответ 201:**
```json
{ "refund_id": "01HXXXXX..." }
```

Возврат помещается в очередь (`ProcessCryptoRefundsJob`, каждые 2 минуты). Для фактической отправки требуется горячий кошелёк:
- **TON**: `TON_HOT_WALLET_MNEMONIC` в `.env` + `composer require olifanton/ton`
- **TRON**: `TRON_HOT_WALLET_PRIVATE_KEY` в `.env`
- **BTC**: не поддерживается (требует UTXO-сервиса)

---

### Инфраструктурные эндпоинты (без версии)

| Метод | URL | Описание |
|---|---|---|
| GET | `/api/health` | Liveness probe: `{"status":"ok","db":"ok"}` |
| GET | `/api/metrics` | Prometheus text format |

---

### Формат ошибок

```json
{
  "code":     "invalid_payment_state",
  "message":  "Payment 01HV... is already in terminal status: Succeeded",
  "trace_id": "a1b2c3d4-0000-0000-0000-000000000000"
}
```

| `code` | HTTP | Причина |
|---|---|---|
| `payment_error` | 422 | Ошибка бизнес-логики |
| `invalid_payment_state` | 409 | Недопустимый переход состояния |
| `idempotency_violation` | 409 | Коллизия Idempotency-Key |
| `webhook_verification_failed` | 403 | Невалидная подпись / IP вебхука |
| `throttle_exceeded` | 429 | Превышен rate limit |
| `not_found` | 404 | Ресурс не найден |

---

## Провайдеры

| Провайдер | Верификация вебхука | Возвраты | Polling | Рекуррентные |
|---|---|---|---|---|
| YooKassa | IP CIDR | ✅ частичные | ✅ | ✅ |
| Robokassa | IP + MD5 | ✅ | ❌ | ❌ |
| CloudPayments | HMAC-SHA256 | ✅ | ✅ | ❌ |
| СБП | X-Api-Key | ✅ | ✅ | ❌ |
| Альфа-Банк | IP CIDR + поля | ✅ | ✅ | ❌ |

---

## Вебхуки

Все вебхуки — **без версии** (`/api/webhook/*`). Менять URL у провайдера при обновлении API не нужно.

| Провайдер | URL | Формат | Rate limit |
|---|---|---|---|
| YooKassa | `POST /api/webhook/yookassa` | JSON | 300 req/min |
| Robokassa | `POST /api/webhook/robokassa` | Form POST, ответ `OK{InvId}` | 200 req/min |
| СБП | `POST /api/webhook/sbp` | JSON | 300 req/min |
| Альфа-Банк | `POST /api/webhook/alfabank` | Form POST | 200 req/min |
| CloudPayments | `POST /api/webhook/cloudpayments` | JSON, ответ `{code:0}` | 300 req/min |

Каждый вебхук-эндпоинт имеет собственный именованный rate limiter (Laravel named throttle). При превышении лимита возвращается `429 Too Many Requests`.

**Надёжность:** 5 попыток, backoff 10s → 30s → 60s → 120s → 300s. При исчерпании — Slack алерт.

---

## Крипто-депозиты

Приём криптовалютных платежей без кастодиального кошелька — клиент переводит средства напрямую на ваш адрес.  
Поддерживаются **5 активов** в 3 блокчейнах. Все используемые API — **полностью бесплатные**, без платных операций.

### Поддерживаемые активы и API

| Актив | Блокчейн | API (бесплатно) | Режим |
|---|---|---|---|
| **TON** | TON | TonCenter v2 `/getTransactions` | Единый адрес + числовой memo |
| **USDT-TON** | TON | TonCenter v3 `/jetton/transfers` | Единый адрес + числовой memo |
| **TRX** | TRON | TronGrid `/v1/accounts/{addr}/transactions` | Пул уникальных адресов |
| **USDT-TRC20** | TRON | TronGrid `/v1/accounts/{addr}/transactions/trc20` | Пул уникальных адресов |
| **BTC** | Bitcoin | mempool.space `/address/{addr}/txs` | Пул уникальных адресов |

### Режимы депозита

**Memo-режим (TON / USDT-TON)**
- Один мастер-адрес для всех клиентов
- Каждый депозит получает уникальный числовой `memo` (комментарий к переводу)
- Клиент обязан указать memo — иначе платёж невозможно идентифицировать

**UniqueAddress-режим (BTC / TRX / USDT-TRC20)**
- Пул адресов, настраиваемый через `.env` (через запятую)
- Каждому активному депозиту назначается свободный адрес из пула
- Memo не требуется — идентификация по адресу

### Как это работает

1. `POST /api/v1/crypto/deposits` → создаётся депозит с TTL `CRYPTO_DEPOSIT_TTL_MINUTES` (по умолчанию 20 мин)
2. Клиент переводит указанную сумму на `depositAddress` (+ `memo` для TON-сетей)
3. `PollCryptoDepositsJob` каждые 15 сек опрашивает соответствующий блокчейн-API
4. При обнаружении входящей транзакции — депозит подтверждается, событие `DepositConfirmed` уходит в `CryptoDepositToPaymentAdapter`

### Статусы депозита

```
awaiting ──→ confirmed
    │──→ overpaid
    └──→ expired   (по TTL, ExpireCryptoDepositsJob каждую минуту)
```

### Конфигурация

```dotenv
CRYPTO_DEPOSIT_TTL_MINUTES=20

# TON / USDT-TON (TonCenter, бесплатный tier: 1 req/s)
TON_MASTER_ADDRESS=UQA...
TON_API_KEY=                       # опционально, увеличивает лимиты
TON_USDT_JETTON_MASTER=EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs

# Bitcoin (mempool.space, полностью бесплатно, без ключа)
BTC_DEPOSIT_ADDRESSES=bc1q...,bc1q...

# TRON / USDT-TRC20 (TronGrid, бесплатно)
TRON_DEPOSIT_ADDRESSES=T...,T...
TRONGRID_API_KEY=                  # опционально, увеличивает лимиты
```

> **Масштабирование**: для обслуживания большего числа одновременных депозитов в BTC/TRON просто добавьте больше адресов в переменные `BTC_DEPOSIT_ADDRESSES` / `TRON_DEPOSIT_ADDRESSES`.

---

## Диспуты и чарджбэки

```
POST /api/v1/payments/{id}/disputes   → открыть диспут (status=Filed)
POST /api/v1/disputes/{id}/resolve    → разрешить (Won / Lost)
```

Агрегат `Dispute` хранится в таблице `disputes`, связан с `payments` по FK. Доменные события `DisputeWasFiled` и `DisputeWasResolved` попадают в `payment_events` для audit trail.

---

## Очереди и Horizon

**Dashboard:** http://localhost:8000/horizon

| Супервизор | Очередь | min/max процессов |
|---|---|---|
| `payments-critical` | `payments-critical` | 2 / 10 |
| `payments` | `payments` | 1 / 5 |
| `default` | `default` | 1 / 5 |

```bash
make horizon-status
make queue-failed
make queue-retry-all
```

---

## Деплой и Graceful Shutdown

Перед деплоем необходимо дать воркерам доделать текущие задачи и остановиться корректно.

### Команда остановки

```bash
php artisan payments:shutdown
# или с явным таймаутом ожидания:
php artisan payments:shutdown --wait=60
```

Команда выполняет два шага:
1. `queue:restart` — устанавливает флаг `illuminate:queue:restart` в кэше; каждый воркер проверяет его в конце текущего цикла и завершает процесс.
2. `horizon:terminate` — отправляет Horizon сигнал SIGTERM; мастер-процесс ждёт завершения всех дочерних воркеров перед выходом.

### Supervisor конфигурации

Готовые конфиги находятся в `docker/supervisor/`:

| Файл | Описание |
|---|---|
| `laravel-worker.conf` | 3 параллельных воркера, `--timeout=60`, `stopwaitsecs=70` — supervisor ждёт дольше, чем таймаут задачи, прежде чем отправить SIGKILL |
| `horizon.conf` | Один процесс Horizon, `stopsignal=SIGTERM`, `stopwaitsecs=3600` — Horizon сам управляет воркерами и сигнализирует им завершиться |

Ключевой принцип: `stopwaitsecs` в supervisor должен быть **больше** `--timeout` воркера, чтобы задача успела завершиться до принудительного убийства процесса.

### Horizon и SIGTERM

При получении SIGTERM Horizon:
1. Перестаёт принимать новые задачи.
2. Ждёт, пока все дочерние воркеры допишут текущие задачи.
3. Корректно завершает мастер-процесс.

---

## Observability

| Сервис | URL | Описание |
|---|---|---|
| Prometheus | http://localhost:9090 | Сбор метрик |
| Grafana | http://localhost:3000 | Дашборды |
| Horizon | http://localhost:8000/horizon | Очереди |

**Метрики** (`GET /api/metrics`, Prometheus text format):

| Метрика | Тип | Описание |
|---|---|---|
| `payments_total{provider,status}` | counter | Созданные платежи |
| `refunds_total{provider}` | counter | Возвраты |
| `webhook_processed_total{provider}` | counter | Обработанные вебхуки |
| `throttle_rejections_total{route}` | counter | Rate limit отказы |
| `failed_jobs_count` | gauge | DLQ: количество упавших задач |
| `crypto_deposits_total{asset}` | counter | Созданные крипто-депозиты |
| `crypto_deposits_confirmed_total{asset}` | counter | Подтверждённые депозиты |

### ELK Stack (опционально)

| Сервис | URL | Описание |
|---|---|---|
| Elasticsearch | `http://localhost:9200` | Хранилище логов |
| Kibana | `http://localhost:5601` | Визуализация логов |
| Logstash | `localhost:5044` (TCP) | Приём логов от Laravel |

**Запуск:**
```bash
docker compose --profile elk up -d
```

**Включить отправку логов платежей в ELK:**
```dotenv
LOG_PAYMENTS_STACK=payments_file,logstash
LOGSTASH_HOST=logstash
```

**Индекс в Kibana:** `laravel-payments-*`

Каждая запись содержит: `level_name`, `message`, `payment_id`, `provider`, `correlation_id` и другие поля из context PaymentLogger.

**Пайплайн:**
```
Laravel PaymentLogger → Monolog SocketHandler (TCP) → Logstash:5044 → Elasticsearch → Kibana
```

---

## Тесты

```bash
make test            # все тесты
make test-unit       # Domain unit-тесты (без БД, без Laravel)
make test-feature    # Feature-тесты (SQLite in-memory + Redis)
```

### Структура

```
tests/
├── Unit/
│   ├── Domain/
│   │   ├── PaymentAggregateTest.php      # 20 тестов: create, succeed, cancel, 3DS
│   │   └── MoneyTest.php                  # Валидация, копейки, форматирование
│   ├── CryptoPayments/
│   │   ├── CryptoDepositAggregateTest.php   # 9 тестов: create, confirm, expire, overpay
│   │   ├── CryptoRefundAggregateTest.php    # 9 тестов: lifecycle рефанда, события
│   │   ├── BlockchainClientContractTest.php # Contract-тесты: все 3 клиента, @dataProvider
│   │   ├── TonBlockchainClientTest.php      # 12 тестов: TON v2 + USDT-TON v3, Http::fake()
│   │   ├── TronBlockchainClientTest.php     # TRX + USDT-TRC20, Http::fake()
│   │   └── BitcoinBlockchainClientTest.php  # BTC via mempool.space, Http::fake()
│   └── YooKassaProviderTest.php, RobokassaProviderTest.php, ...
│
└── Feature/
    ├── Payments/
    │   ├── CreatePaymentTest.php          # Создание, идемпотентность, пагинация, фильтры
    │   ├── RefundPaymentTest.php          # Полный / частичный / кумулятивный возврат
    │   ├── CancelPaymentTest.php
    │   ├── ExportPaymentsTest.php         # CSV streaming, заголовки, Content-Disposition
    │   ├── DisputeTest.php               # 14 сценариев: filed, won, lost, ошибки
    │   ├── WebhookTest.php               # IP-фильтрация, dispatch, идемпотентность
    │   └── YooKassa/Robokassa/CloudPayments/Sbp/AlfaBankWebhookTest.php
    └── CryptoPayments/
        ├── CryptoDepositTest.php          # 12 сценариев: TON + USDT-TON
        └── CryptoPaymentFlowTest.php      # E2E: Create → PollJob → Confirm → Refund (7 сценариев)
```

---

## CI/CD

GitHub Actions на каждый push:

| Job | Что делает | Рабочая директория |
|---|---|---|
| `lint` | `pint --test` | `backend/` |
| `frontend` | `npm run build` | `frontend/` |
| `analyse` | PHPStan level 7 (Larastan) | `backend/` |
| `test-unit` | Unit-тесты | `backend/` |
| `test-feature` | Feature-тесты (Redis + SQLite) | `backend/` |

Все 5 джобов независимы и выполняются параллельно.

---

## Makefile

```bash
make help

make up / down / restart / logs / ps

make migrate
make migrate-fresh      # пересоздать БД (с подтверждением)

make test / test-unit / test-feature
make lint / lint-fix / analyse

make horizon-status / horizon-pause / horizon-resume
make queue-failed / queue-retry-all

make artisan CMD="route:list"
make shell              # bash в контейнере app
```

---

## Структура БД

### `payments`

| Колонка | Тип | Описание |
|---|---|---|
| `id` | ULID PK | |
| `idempotency_key` | string(36)? | Очищается через 90 дней |
| `external_id` | string? | ID у провайдера |
| `payment_method_id` | string? | Сохранённый метод (YooKassa recurring) |
| `provider` | string(50) | `yookassa` / `robokassa` / ... |
| `amount` | uint | Копейки |
| `refunded_amount` | uint |累积 сумма возвратов |
| `currency` | char(3) | `RUB` |
| `status` | string(30) | `Pending` / `Succeeded` / `Cancelled` / `Refunded` |
| `confirmation_url` | string? | |
| `three_ds_required` | boolean | |
| `three_ds_challenge_url` | string? | |
| `metadata` | JSON? | |

### `disputes`

| Колонка | Тип | Описание |
|---|---|---|
| `id` | ULID PK | |
| `payment_id` | ULID FK | |
| `status` | string(20) | `Filed` / `Won` / `Lost` |
| `amount` | uint | Копейки |
| `reason` | string | |
| `note` | text? | Комментарий при разрешении |

### `crypto_deposits`

| Колонка | Тип | Описание |
|---|---|---|
| `id` | ULID PK | |
| `payment_id` | string | Внешний ID платежа |
| `status` | string(20) | `awaiting` / `confirmed` / `overpaid` / `expired` |
| `asset` | string(20) | `TON` / `USDT_TON` |
| `expected_units` | uint | Ожидаемая сумма (nanotons или microUSDT) |
| `actual_units` | uint? | Фактически полученная сумма |
| `fiat_amount_kopecks` | uint | Сумма в копейках |
| `deposit_address` | string | TON-адрес для приёма |
| `memo` | string(10) | Числовой комментарий — уникальный идентификатор перевода |
| `tx_hash` | string? | Хэш транзакции |
| `expires_at` | timestamp | TTL депозита |
| `created_at_ts` | uint | Unix timestamp создания |

### `payment_events`

Audit trail доменных событий.

| Колонка | Тип | Описание |
|---|---|---|
| `payment_id` | ULID FK | |
| `event_id` | UUID | |
| `event_name` | string | `PaymentWasCreated`, `DisputeWasFiled`, `PaymentRequiresThreeDSecure`, ... |
| `event_data` | JSON | |
| `occurred_at` | string | ISO 8601 |
