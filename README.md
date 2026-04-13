# Payment Gateway

REST API платёжного шлюза на Laravel 13 с поддержкой нескольких провайдеров, асинхронной обработкой вебхуков через Horizon и полным покрытием тестами.

## Содержание

- [Возможности](#возможности)
- [Стек технологий](#стек-технологий)
- [Архитектура](#архитектура)
- [Быстрый старт](#быстрый-старт)
- [Конфигурация](#конфигурация)
- [API](#api)
- [Провайдеры](#провайдеры)
- [Вебхуки](#вебхуки)
- [Очереди и Horizon](#очереди-и-horizon)
- [Тесты](#тесты)
- [CI/CD](#cicd)
- [Makefile](#makefile)

---

## Возможности

- Создание платежей через **YooKassa** и **Robokassa**
- Частичные и полные **возвраты** с кумулятивным трекингом суммы
- **Рекуррентные платежи** (YooKassa): сохранение метода оплаты и списание без редиректа
- **Чеки 54-ФЗ** (YooKassa): передача позиций, налоговых кодов, данных покупателя
- **Идемпотентность** создания и возврата по заголовку `Idempotency-Key`
- Асинхронная обработка вебхуков с экспоненциальным backoff (Horizon + Redis)
- Полный **audit trail** через `spatie/laravel-activitylog`
- Structured logging с Correlation ID на каждый запрос
- IP-фильтрация вебхуков по официальным CIDR провайдеров
- Алерты в **Slack** при исчерпании попыток обработки вебхука
- Swagger UI / OpenAPI 3.0 документация
- **PHPStan level 6** + **Laravel Pint** в CI

---

## Стек технологий

| Слой | Технология |
|---|---|
| Framework | Laravel 13, PHP 8.4 |
| База данных | PostgreSQL 16 |
| Очереди / кэш | Redis 7, Laravel Horizon |
| Веб-сервер | Nginx (reverse proxy) |
| Документация | l5-swagger (OpenAPI 3.0) |
| Тесты | PHPUnit 11 |
| Статический анализ | PHPStan level 6 (Larastan) |
| Стиль кода | Laravel Pint |
| Контейнеры | Docker, Docker Compose |

---

## Архитектура

Проект построен по принципам **Clean Architecture** с элементами **DDD**.

```
app/Payments/
├── Domain/                      # Бизнес-логика, не зависит от фреймворка
│   ├── Aggregates/
│   │   └── Payment.php          # Главный агрегат: статусы, рефанды, события
│   ├── Contracts/
│   │   ├── PaymentProviderInterface.php
│   │   ├── PaymentRepositoryInterface.php
│   │   └── ProviderResponse.php
│   ├── Entities/
│   │   ├── PaymentAttempt.php
│   │   └── RefundRequest.php
│   ├── Enums/
│   │   ├── Currency.php
│   │   └── PaymentStatus.php    # Pending | Succeeded | Cancelled | Refunded
│   ├── Events/                  # Доменные события (чистый PHP, без Laravel)
│   │   ├── PaymentWasCreated.php
│   │   ├── PaymentWasSucceeded.php
│   │   ├── PaymentWasCancelled.php
│   │   └── PaymentWasRefunded.php
│   ├── Exceptions/
│   │   ├── PaymentException.php
│   │   └── InvalidPaymentStateException.php
│   └── ValueObjects/
│       ├── Money.php            # Сумма в копейках + валюта
│       ├── PaymentId.php        # ULID
│       ├── ExternalId.php       # ID у провайдера
│       ├── AttemptId.php
│       └── RefundId.php
│
├── Application/                 # Сценарии использования
│   ├── Bus/
│   │   └── CommandBus.php       # Pipeline: Validate → Idempotency → Log → Handle
│   ├── Commands/
│   │   ├── CreatePayment/
│   │   ├── CancelPayment/
│   │   ├── RefundPayment/       # Поддержка Idempotency-Key
│   │   └── SyncPayment/
│   ├── DTOs/
│   │   ├── PaymentResultDTO.php
│   │   ├── CreatePaymentOptionsDTO.php
│   │   ├── ReceiptDTO.php
│   │   └── ReceiptItemDTO.php
│   └── Pipeline/
│       ├── EnforceIdempotency.php
│       ├── LogCommand.php
│       └── ValidateCommand.php
│
├── Infrastructure/              # Адаптеры к внешнему миру
│   ├── Jobs/
│   │   ├── ProcessYooKassaWebhookJob.php
│   │   └── ProcessRobokassaWebhookJob.php
│   ├── Observability/
│   │   ├── PaymentLogger.php    # Structured logging + enrich с контекстом
│   │   └── CorrelationIdMiddleware.php
│   ├── Persistence/
│   │   ├── EloquentPaymentRepository.php
│   │   └── Models/
│   │       ├── PaymentModel.php
│   │       └── PaymentEventModel.php
│   └── Providers/
│       ├── YooKassaProvider.php
│       └── RobokassaProvider.php
│
└── Presentation/                # HTTP-слой
    └── Http/
        ├── Controllers/
        │   ├── PaymentController.php
        │   ├── WebhookController.php           # YooKassa JSON webhook
        │   ├── RobokassaWebhookController.php  # Robokassa form POST webhook
        │   ├── HealthController.php
        │   └── ApiDocController.php            # OpenAPI schemas
        ├── Requests/
        │   ├── CreatePaymentRequest.php
        │   └── RefundPaymentRequest.php
        └── Resources/
            └── PaymentResource.php
```

### Жизненный цикл платежа

```
POST /payments
    → CommandBus (Validate → Idempotency → Log)
    → CreatePaymentHandler
        → Payment::create()           # доменный агрегат
        → repository->save()          # первое сохранение (status=Pending)
        → provider->createPayment()   # запрос к YooKassa / Robokassa
        → payment->assignExternalData()
        → repository->save()          # обновление с external_id и confirmation_url
    ← PaymentResultDTO { id, status, confirmation_url, ... }

Клиент переходит по confirmation_url → оплачивает → провайдер шлёт webhook

POST /webhook/yookassa (или /webhook/robokassa)
    → verifyWebhook()     # проверка IP + подписи
    → ProcessWebhookJob::dispatch()   # очередь Horizon
    ← 200 OK (немедленно)

ProcessWebhookJob (async, Horizon)
    → repository->findBy...()
    → payment->markAsSucceeded() / cancel() / refund()
    → repository->save()
    → activity log
```

### Состояния платежа

```
Pending ──→ Succeeded ──→ Refunded  (частичный возврат: остаётся Succeeded до полной суммы)
   │
   └──→ Cancelled
```

Переход в терминальный статус (Succeeded / Cancelled / Refunded) необратим — агрегат выбрасывает `InvalidPaymentStateException`.

---

## Быстрый старт

### Требования

- Docker и Docker Compose
- Make (опционально, для удобства)

### Установка

```bash
# 1. Клонировать репозиторий
git clone <repo-url>
cd payment-gateway

# 2. Скопировать и настроить .env
cp .env.example .env

# 3. Поднять контейнеры
make up

# 4. Сгенерировать ключ приложения
make artisan CMD="key:generate"

# 5. Выполнить миграции
make migrate

# 6. (Опционально) Открыть Swagger UI
open http://localhost:8000/api/documentation
```

Сервисы после запуска:

| Сервис | URL |
|---|---|
| API | http://localhost:8000/api |
| Swagger UI | http://localhost:8000/api/documentation |
| Horizon dashboard | http://localhost:8000/horizon |
| Adminer (БД) | http://localhost:8080 |

---

## Конфигурация

### Переменные окружения

```dotenv
# Активный провайдер: yookassa | robokassa
PAYMENT_PROVIDER=yookassa

# YooKassa
YOOKASSA_SHOP_ID=100500
YOOKASSA_SECRET_KEY=test_xxxxx

# Robokassa
ROBOKASSA_LOGIN=your_login
ROBOKASSA_PASSWORD1=your_password1   # для создания платежей и возвратов
ROBOKASSA_PASSWORD2=your_password2   # для верификации вебхуков
ROBOKASSA_IS_TEST=true               # false в продакшене

# Slack-алерты при сбое обработки вебхука
SLACK_WEBHOOK_URL=https://hooks.slack.com/...

# Horizon dashboard: разрешённые IP в продакшене (через запятую)
HORIZON_ALLOWED_IPS=1.2.3.4,5.6.7.8
```

### Переключение провайдера

Изменить `PAYMENT_PROVIDER` в `.env` — без изменения кода. Вся бизнес-логика не знает о конкретном провайдере.

---

## API

### Базовый URL

```
http://localhost:8000/api
```

### Эндпоинты

#### `GET /payments`

Список платежей с пагинацией.

| Параметр | Тип | Описание |
|---|---|---|
| `status` | string | Фильтр: `Pending`, `Succeeded`, `Cancelled`, `Refunded` |
| `from_date` | date | Дата от (Y-m-d) |
| `to_date` | date | Дата до (Y-m-d) |
| `per_page` | int | Размер страницы (1–100, default: 15) |
| `page` | int | Номер страницы |

---

#### `POST /payments`

Создать платёж.

**Заголовки:**

| Заголовок | Описание |
|---|---|
| `Idempotency-Key` | UUID (опционально). Повторный запрос с тем же ключом вернёт существующий платёж без обращения к провайдеру |

**Тело запроса:**

```json
{
  "amount": 10000,
  "description": "Оплата заказа №1234",
  "return_url": "https://example.com/payment/success",
  "metadata": { "order_id": "1234" },

  // Опционально (YooKassa)
  "payment_method_type": "bank_card",
  "confirmation_type": "redirect",
  "save_payment_method": false,

  // Рекуррентное списание (YooKassa)
  "payment_method_id": "saved-method-uuid",

  // Чек 54-ФЗ (YooKassa)
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

> **Robokassa:** `confirmation_url` в ответе — прямая ссылка для редиректа клиента. `external_id` содержит внутренний ULID как placeholder; реальный `InvId` устанавливается при обработке вебхука.

---

#### `GET /payments/{id}`

Получить платёж по ULID.

---

#### `POST /payments/{id}/cancel`

Отменить платёж. Работает только для статуса `Pending`. Возвращает `409` при терминальном статусе.

---

#### `POST /payments/{id}/refund`

Создать возврат. Работает только для статуса `Succeeded`.

**Заголовки:** `Idempotency-Key` — защита от двойного списания при сетевых ретраях.

```json
{
  "amount": 5000,   // копейки; если не указан — полный возврат
  "reason": "Возврат по заявке клиента"
}
```

**Частичные возвраты:** сумма аккумулируется. Статус меняется на `Refunded` только когда сумма возвратов равна сумме платежа.

---

#### `POST /payments/{id}/sync`

Синхронизировать статус с провайдером (polling).

> **Robokassa:** polling не поддерживается, вернёт `422`.

---

#### `GET /health`

```json
{ "status": "ok", "db": "ok" }
```

---

## Провайдеры

### YooKassa

**Настройка:** `shop_id` и `secret_key` из [личного кабинета YooKassa](https://yookassa.ru/my).

| Возможность | Поддержка |
|---|---|
| Методы оплаты | Карта, ЮMoney, СБП, Сбербанк, Тинькофф, и др. |
| Типы подтверждения | `redirect`, `embedded`, `qr`, `mobile_application` |
| Рекуррентные платежи | ✅ |
| Чеки 54-ФЗ | ✅ |
| Возвраты | ✅ (частичные и полные) |
| Polling статуса | ✅ |

### Robokassa

**Настройка:** Создать магазин в [личном кабинете Robokassa](https://merchant.robokassa.ru). Указать `ResultURL = https://yourdomain.com/api/webhook/robokassa`.

| Возможность | Поддержка |
|---|---|
| Методы оплаты | Карты, электронные кошельки, терминалы, СБП |
| Типы подтверждения | `redirect` (только) |
| Рекуррентные платежи | ❌ |
| Чеки 54-ФЗ | ❌ (в данной реализации) |
| Возвраты | ✅ |
| Polling статуса | ❌ (только вебхуки) |

### СБП

Интеграция через банк-эквайер с НСПК-совместимым API. Платёж — динамический QR-код.

**Настройка:** Получить `merchant_id`, `api_key` и `webhook_secret` у банка-партнёра. Настроить URL вебхука: `https://yourdomain.com/api/webhook/sbp`.

| Возможность | Поддержка |
|---|---|
| Подтверждение | QR-код / deep link (`https://qr.nspk.ru/...`) |
| Рекуррентные платежи | ❌ |
| Возвраты | ✅ |
| Polling статуса | ✅ |

> `confirmation_url` в ответе содержит QR-payload — строку для рендеринга QR-кода или deep link для мобильных приложений.

### Альфа-Банк

Интернет-эквайринг Альфа-Банка. Тестовый стенд: `https://alfa.rbsuat.com/payment/rest`.

**Настройка:** Получить `login` и `password` в личном кабинете. Настроить URL вебхука: `https://yourdomain.com/api/webhook/alfabank`.

| Возможность | Поддержка |
|---|---|
| Методы оплаты | Банковские карты |
| Типы подтверждения | `redirect` |
| Рекуррентные платежи | ❌ |
| Возвраты | ✅ |
| Polling статуса | ✅ |

---

## Вебхуки

### Сравнительная таблица провайдеров

| Провайдер | Подтверждение | Возвраты | Polling | Вебхук формат | Верификация |
|---|---|---|---|---|---|
| YooKassa | redirect / embedded / qr / mobile | ✅ частичные | ✅ | JSON | IP CIDR |
| Robokassa | redirect | ✅ | ❌ | Form POST | IP + MD5 |
| СБП | QR / deep link | ✅ | ✅ | JSON | HMAC заголовок |
| Альфа-Банк | redirect | ✅ | ✅ | Form POST | Поля payload |

---

### YooKassa — `POST /api/webhook/yookassa`

**Формат:** JSON  
**Верификация:** IP-фильтрация по официальным CIDR

| Событие | Действие |
|---|---|
| `payment.succeeded` | `payment->markAsSucceeded()` |
| `payment.canceled` | `payment->cancel()` |
| `refund.succeeded` | `payment->refund()` с суммой из вебхука |

### Robokassa — `POST /api/webhook/robokassa`

**Формат:** `application/x-www-form-urlencoded`  
**Верификация:** IP-фильтрация + MD5-подпись (`Password#2`)  
**Ответ:** Plain-text `OK{InvId}` (обязательно, иначе Robokassa повторит запрос)

### СБП — `POST /api/webhook/sbp`

**Формат:** JSON  
**Верификация:** заголовок `X-Api-Key` сверяется с `SBP_WEBHOOK_SECRET`  
**Статусы:** `PAID` → Succeeded, `CANCELLED` / `EXPIRED` → Cancelled

### Альфа-Банк — `POST /api/webhook/alfabank`

**Формат:** `application/x-www-form-urlencoded`  
**Верификация:** наличие полей `mdOrder` и `operation`  
**Операции:** `deposited` → Succeeded, `refunded` → Refunded, `reversed` / `declinedByTimeout` → Cancelled

### Надёжность

- Вебхуки ставятся в очередь Redis мгновенно — `200 OK` до обработки
- **5 попыток** с экспоненциальным backoff: 10s → 30s → 60s → 120s → 300s
- При исчерпании: критический лог + алерт в Slack
- Повторный вебхук на терминальный платёж молча игнорируется (идемпотентность)

---

## Очереди и Horizon

**Dashboard:** http://localhost:8000/horizon

### Супервизоры

| Супервизор | Очередь | Процессы (min/max) |
|---|---|---|
| `payments-critical` | `payments-critical` | 2 / 10 |
| `payments` | `payments` | 1 / 5 |
| `default` | `default` | 1 / 5 |

```bash
make horizon-status    # статус
make horizon-pause     # пауза всех воркеров
make horizon-resume    # возобновление
make queue-failed      # список упавших задач
make queue-retry-all   # повторить все упавшие
```

---

## Тесты

```bash
make test           # все тесты
make test-unit      # только Domain (без БД, без Laravel bootstrap)
make test-feature   # только Feature (SQLite in-memory)
```

### Структура

```
tests/
├── Unit/
│   ├── Domain/
│   │   ├── PaymentAggregateTest.php   # 20 тестов: create, succeed, cancel, partial refund, restore
│   │   └── MoneyTest.php              # 10 тестов: валидация, форматирование, сравнение
│   └── YooKassaProviderTest.php
└── Feature/
    └── Payments/
        ├── CreatePaymentTest.php      # создание, идемпотентность, валидация, пагинация
        ├── RefundPaymentTest.php      # полный/частичный рефанд, кумулятив, идемпотентность
        ├── CancelPaymentTest.php      # отмена всех статусов, show, sync
        └── WebhookTest.php            # диспетчеризация, IP-фильтрация
```

Unit-тесты домена не зависят от Laravel (чистый PHPUnit). Feature-тесты используют SQLite in-memory и мокируют провайдеров через `$this->mock()`.

---

## CI/CD

GitHub Actions на каждый push:

| Job | Что делает |
|---|---|
| `lint` | `pint --test` — стиль кода |
| `analyse` | PHPStan level 6 |
| `test-unit` | Unit-тесты |
| `test-feature` | Feature-тесты (Redis + SQLite) |

CD (`cd.yml`) собирает Docker-образ и пушит в GHCR. Для деплоя настроить секреты: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_PORT`, `DEPLOY_PATH`.

---

## Makefile

```bash
make help             # список всех команд

make up / down / restart / logs / ps

make migrate
make migrate-fresh    # пересоздать БД (с подтверждением)
make migrate-rollback STEP=1

make test / test-unit / test-feature
make lint / lint-fix / analyse

make horizon-status / horizon-pause / horizon-resume
make queue-failed / queue-retry-all

make artisan CMD="route:list"
make shell            # bash в контейнере app
make tinker
```

---

## Структура БД

### `payments`

| Колонка | Тип | Описание |
|---|---|---|
| `id` | ULID | Внутренний ID |
| `idempotency_key` | string(36) | Ключ для дедупликации |
| `external_id` | string, nullable | ID у провайдера |
| `payment_method_id` | string, nullable | Сохранённый метод (YooKassa recurring) |
| `provider` | string(50) | `yookassa` / `robokassa` |
| `amount` | uint | Сумма в копейках |
| `refunded_amount` | uint | Сумма возвратов в копейках |
| `currency` | char(3) | `RUB` |
| `status` | string(30) | `Pending` / `Succeeded` / `Cancelled` / `Refunded` |
| `confirmation_url` | string, nullable | URL страницы оплаты |
| `metadata` | JSON, nullable | Произвольные данные |

### `payment_events`

Audit trail доменных событий.

| Колонка | Тип | Описание |
|---|---|---|
| `payment_id` | ULID FK | |
| `event_id` | UUID | Уникальный ID события |
| `event_name` | string | `PaymentWasCreated`, `PaymentWasSucceeded`, ... |
| `event_data` | JSON | Данные события |
| `occurred_at` | string | ISO 8601 |
