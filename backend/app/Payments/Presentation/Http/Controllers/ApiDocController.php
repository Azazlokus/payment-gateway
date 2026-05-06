<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Payment Gateway API',
    description: <<<'TEXT'
        API платёжного шлюза с поддержкой нескольких провайдеров и крипто-депозитов.

        ## Провайдеры

        | Провайдер     | Тип подтверждения                        | Рефанды | Вебхуки |
        |---------------|------------------------------------------|---------|---------|
        | YooKassa      | redirect / embedded / qr / mobile        | ✅      | ✅      |
        | Robokassa     | redirect (только)                        | ✅      | ✅      |
        | CloudPayments | redirect                                  | ✅      | ✅      |
        | СБП           | QR-код (НСПК)                            | ❌      | ✅      |
        | Альфа-Банк    | redirect                                  | ✅      | ✅      |

        Активный провайдер задаётся через переменную окружения `PAYMENT_PROVIDER`.

        ## Крипто-депозиты

        Приём криптовалюты без кастодиального кошелька — только бесплатные API:

        | Актив        | Блокчейн | API              | Режим           |
        |--------------|----------|------------------|-----------------|
        | TON          | TON      | TonCenter v2     | Адрес + memo    |
        | USDT-TON     | TON      | TonCenter v3     | Адрес + memo    |
        | TRX          | TRON     | TronGrid         | Пул адресов     |
        | USDT-TRC20   | TRON     | TronGrid         | Пул адресов     |
        | BTC          | Bitcoin  | mempool.space    | Пул адресов     |

        ## Идемпотентность

        Заголовок `Idempotency-Key` (UUID) поддерживается для:
        - `POST /payments` — повторный запрос с тем же ключом вернёт уже созданный платёж
        - `POST /payments/{id}/refund` — защита от двойного списания при сетевых ретраях

        ## Суммы

        Все фиатные суммы передаются и возвращаются **в копейках** (целое число).
        Минимальная сумма: 100 (= 1 рубль).
        TEXT,
)]
#[OA\Server(url: '/api/v1', description: 'Local / Docker')]
#[OA\SecurityScheme(
    securityScheme: 'ApiKeyAuth',
    type: 'apiKey',
    in: 'header',
    name: 'X-Api-Key',
    description: 'API-ключ для всех эндпоинтов /api/v1/*. Устанавливается через переменную окружения API_KEY.',
)]
#[OA\SecurityScheme(
    securityScheme: 'IdempotencyKey',
    type: 'apiKey',
    in: 'header',
    name: 'Idempotency-Key',
    description: 'UUID ключ идемпотентности. При повторном запросе возвращает тот же результат.',
)]

// ─── Shared response schemas ──────────────────────────────────────────────────

#[OA\Schema(
    schema: 'PaymentResponse',
    description: 'Объект платежа',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'ulid', description: 'Внутренний ID платежа', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'status', type: 'string', enum: ['Pending', 'Succeeded', 'Cancelled', 'Refunded'], example: 'Pending'),
        new OA\Property(property: 'amount', type: 'integer', description: 'Сумма в копейках', example: 10000),
        new OA\Property(property: 'currency', type: 'string', example: 'RUB'),
        new OA\Property(
            property: 'confirmation_url',
            type: 'string',
            nullable: true,
            description: 'URL страницы оплаты у провайдера. Для YooKassa — ссылка на checkout, для Robokassa — ссылка для редиректа клиента.',
            example: 'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=...',
        ),
        new OA\Property(property: 'external_id', type: 'string', nullable: true, description: 'ID платежа у провайдера (YooKassa UUID или Robokassa InvId)', example: '22d65900-000f-5000-a000-10d000000000'),
        new OA\Property(property: 'payment_method_id', type: 'string', nullable: true, description: 'ID сохранённого метода оплаты (только YooKassa, для рекуррентных платежей)'),
        new OA\Property(property: 'three_ds_required', type: 'boolean', description: 'Платёж требует прохождения 3-D Secure', example: false),
        new OA\Property(property: 'three_ds_challenge_url', type: 'string', nullable: true, description: 'URL страницы 3DS-челленджа (заполняется, если three_ds_required = true)'),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedPayments',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaymentResponse')),
        new OA\Property(property: 'total', type: 'integer', example: 42),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The amount field is required.'),
        new OA\Property(property: 'errors', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))),
    ]
)]
#[OA\Schema(
    schema: 'PaymentError',
    description: 'Стандартный ответ об ошибке',
    properties: [
        new OA\Property(property: 'code', type: 'string', description: 'Машиночитаемый код ошибки', example: 'payment_error',
            enum: ['payment_error', 'invalid_payment_state', 'webhook_verification_failed', 'idempotency_violation', 'throttle_exceeded', 'not_found', 'unauthorized']),
        new OA\Property(property: 'message', type: 'string', description: 'Человекочитаемое описание ошибки', example: 'Payment not found: 01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'trace_id', type: 'string', nullable: true, description: 'X-Correlation-Id запроса для отслеживания в логах', example: 'a1b2c3d4-0000-0000-0000-000000000000'),
    ]
)]
#[OA\Schema(
    schema: 'DisputeResponse',
    description: 'Диспут (чарджбэк) по платежу',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'ulid', description: 'ID диспута', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'payment_id', type: 'string', format: 'ulid', description: 'ID платежа'),
        new OA\Property(property: 'status', type: 'string', enum: ['Filed', 'Won', 'Lost'], description: 'Статус диспута', example: 'Filed'),
        new OA\Property(property: 'amount', type: 'integer', description: 'Оспариваемая сумма в копейках', example: 50000),
        new OA\Property(property: 'currency', type: 'string', example: 'RUB'),
        new OA\Property(property: 'reason', type: 'string', description: 'Основание диспута', example: 'Товар не получен'),
        new OA\Property(property: 'note', type: 'string', nullable: true, description: 'Комментарий при разрешении диспута'),
    ]
)]

// ─── YooKassa webhook schemas ─────────────────────────────────────────────────

#[OA\Schema(
    schema: 'YooKassaWebhookPayload',
    description: 'Тело вебхука от YooKassa (JSON)',
    required: ['event', 'object'],
    properties: [
        new OA\Property(property: 'event', type: 'string', enum: ['payment.succeeded', 'payment.canceled', 'refund.succeeded'], example: 'payment.succeeded'),
        new OA\Property(
            property: 'object',
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'string', description: 'ID платежа в YooKassa', example: '22d65900-000f-5000-a000-10d000000000'),
                new OA\Property(property: 'status', type: 'string', example: 'succeeded'),
                new OA\Property(property: 'amount', type: 'object', properties: [
                    new OA\Property(property: 'value', type: 'string', example: '100.00'),
                    new OA\Property(property: 'currency', type: 'string', example: 'RUB'),
                ]),
            ]
        ),
    ]
)]

// ─── SBP webhook schemas ─────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'SbpWebhookPayload',
    description: 'JSON-вебхук от банка-эквайера СБП',
    required: ['qrId', 'status'],
    properties: [
        new OA\Property(property: 'transactionId', type: 'string', description: 'ID транзакции в банке', example: 'txn-abc123'),
        new OA\Property(property: 'qrId', type: 'string', description: 'ID QR-кода (external_id платежа)', example: 'AS1000123456789'),
        new OA\Property(property: 'status', type: 'string', enum: ['PAID', 'CANCELLED', 'EXPIRED'], example: 'PAID'),
        new OA\Property(property: 'amount', type: 'object', properties: [
            new OA\Property(property: 'value', type: 'integer', description: 'Сумма в копейках', example: 10000),
            new OA\Property(property: 'currency', type: 'string', example: 'RUB'),
        ]),
        new OA\Property(property: 'order', type: 'string', description: 'Внутренний ULID платежа', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
    ]
)]

// ─── AlfaBank webhook schemas ─────────────────────────────────────────────────

#[OA\Schema(
    schema: 'AlfaBankWebhookPayload',
    description: 'Form POST уведомление от Альфа-Банка',
    required: ['mdOrder', 'operation'],
    properties: [
        new OA\Property(property: 'mdOrder', type: 'string', description: 'ID заказа в Альфа-Банке (external_id)', example: 'a1b2c3d4-...'),
        new OA\Property(property: 'operation', type: 'string', enum: ['deposited', 'refunded', 'reversed', 'declinedByTimeout'], example: 'deposited'),
        new OA\Property(property: 'orderNumber', type: 'string', description: 'Внутренний ULID платежа', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'status', type: 'integer', description: '1 = успех, 0 = ошибка', example: 1),
    ]
)]

// ─── Crypto deposit schemas ───────────────────────────────────────────────────

#[OA\Schema(
    schema: 'CryptoDepositResponse',
    description: 'Крипто-депозит',
    properties: [
        new OA\Property(property: 'depositId', type: 'string', format: 'ulid', description: 'ID депозита', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'paymentId', type: 'string', description: 'Внешний ID платежа', example: 'pay-order-1234'),
        new OA\Property(property: 'status', type: 'string', enum: ['awaiting', 'confirmed', 'expired', 'overpaid'], example: 'awaiting'),
        new OA\Property(property: 'asset', type: 'string', enum: ['TON', 'USDT_TON', 'TRX', 'USDT_TRC20', 'BTC'], example: 'TON'),
        new OA\Property(property: 'expectedUnits', type: 'integer', description: 'Ожидаемая сумма в наименьших единицах актива (наноТОН, сатоши, sun и т.д.)', example: 125000000),
        new OA\Property(property: 'cryptoAmount', type: 'string', description: 'Читаемое значение суммы', example: '0.125'),
        new OA\Property(property: 'fiatAmountKopecks', type: 'integer', description: 'Эквивалент в копейках на момент создания', example: 50000),
        new OA\Property(property: 'depositAddress', type: 'string', description: 'Адрес для перевода', example: 'UQA...'),
        new OA\Property(property: 'memo', type: 'string', nullable: true, description: 'Числовой комментарий (только для TON-сетей). Для BTC/TRX — null.', example: '123456789'),
        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', description: 'Время истечения депозита (ISO 8601)'),
        new OA\Property(property: 'qrPayload', type: 'string', description: 'URI для QR-кода (ton://, bitcoin:, tron:)', example: 'ton://transfer/UQA...?amount=125000000&text=123456789'),
        new OA\Property(property: 'txHash', type: 'string', nullable: true, description: 'Хэш транзакции после подтверждения'),
    ]
)]
#[OA\Schema(
    schema: 'CreateCryptoDepositRequest',
    required: ['payment_id', 'fiat_amount_kopecks', 'asset'],
    properties: [
        new OA\Property(property: 'payment_id', type: 'string', description: 'Внешний ID платежа', example: 'pay-order-1234', maxLength: 255),
        new OA\Property(property: 'fiat_amount_kopecks', type: 'integer', description: 'Сумма в копейках для конвертации в крипту', example: 50000, minimum: 100),
        new OA\Property(property: 'asset', type: 'string', enum: ['TON', 'USDT_TON', 'TRX', 'USDT_TRC20', 'BTC'], description: 'Криптоактив для приёма', example: 'TON'),
    ]
)]

// ─── Robokassa webhook schemas ────────────────────────────────────────────────

#[OA\Schema(
    schema: 'RobokassaWebhookPayload',
    description: 'Тело ResultURL-вебхука от Robokassa (form POST)',
    required: ['OutSum', 'InvId', 'SignatureValue', 'Shp_paymentId'],
    properties: [
        new OA\Property(property: 'OutSum', type: 'string', description: 'Сумма платежа в рублях', example: '100.00'),
        new OA\Property(property: 'InvId', type: 'integer', description: 'Номер заказа, присвоенный Robokassa', example: 12345),
        new OA\Property(property: 'SignatureValue', type: 'string', description: 'MD5-подпись для верификации', example: 'A1B2C3D4E5F6...'),
        new OA\Property(property: 'Shp_paymentId', type: 'string', description: 'Внутренний ULID платежа (передаётся при создании)', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
    ]
)]
class ApiDocController {}
