<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', description: <<<'TEXT'
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
        TEXT, title: 'Payment Gateway API')]
#[OA\Server(url: '/api/v1', description: 'Local / Docker')]
#[OA\SecurityScheme(securityScheme: 'ApiKeyAuth', type: 'apiKey', description: 'API-ключ для всех эндпоинтов /api/v1/*. Устанавливается через переменную окружения API_KEY.', name: 'X-Api-Key', in: 'header')]
#[OA\SecurityScheme(securityScheme: 'IdempotencyKey', type: 'apiKey', description: 'UUID ключ идемпотентности. При повторном запросе возвращает тот же результат.', name: 'Idempotency-Key', in: 'header')]

// ─── Shared response schemas ──────────────────────────────────────────────────

#[OA\Schema(
    schema: 'PaymentResponse',
    description: 'Объект платежа',
    properties: [
        new OA\Property(property: 'id', description: 'Внутренний ID платежа', type: 'string', format: 'ulid', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'status', type: 'string', example: 'Pending', enum: ['Pending', 'Succeeded', 'Cancelled', 'Refunded']),
        new OA\Property(property: 'amount', description: 'Сумма в копейках', type: 'integer', example: 10000),
        new OA\Property(property: 'currency', type: 'string', example: 'RUB'),
        new OA\Property(
            property: 'confirmation_url',
            description: 'URL страницы оплаты у провайдера. Для YooKassa — ссылка на checkout, для Robokassa — ссылка для редиректа клиента.',
            type: 'string',
            example: 'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=...',
            nullable: true,
        ),
        new OA\Property(property: 'external_id', description: 'ID платежа у провайдера (YooKassa UUID или Robokassa InvId)', type: 'string', example: '22d65900-000f-5000-a000-10d000000000', nullable: true),
        new OA\Property(property: 'payment_method_id', description: 'ID сохранённого метода оплаты (только YooKassa, для рекуррентных платежей)', type: 'string', nullable: true),
        new OA\Property(property: 'three_ds_required', description: 'Платёж требует прохождения 3-D Secure', type: 'boolean', example: false),
        new OA\Property(property: 'three_ds_challenge_url', description: 'URL страницы 3DS-челленджа (заполняется, если three_ds_required = true)', type: 'string', nullable: true),
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
        new OA\Property(property: 'code', description: 'Машиночитаемый код ошибки', type: 'string', example: 'payment_error',
            enum: ['payment_error', 'invalid_payment_state', 'webhook_verification_failed', 'idempotency_violation', 'throttle_exceeded', 'not_found', 'unauthorized']),
        new OA\Property(property: 'message', description: 'Человекочитаемое описание ошибки', type: 'string', example: 'Payment not found: 01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'trace_id', description: 'X-Correlation-Id запроса для отслеживания в логах', type: 'string', example: 'a1b2c3d4-0000-0000-0000-000000000000', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'DisputeResponse',
    description: 'Диспут (чарджбэк) по платежу',
    properties: [
        new OA\Property(property: 'id', description: 'ID диспута', type: 'string', format: 'ulid', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'payment_id', description: 'ID платежа', type: 'string', format: 'ulid'),
        new OA\Property(property: 'status', description: 'Статус диспута', type: 'string', example: 'Filed', enum: ['Filed', 'Won', 'Lost']),
        new OA\Property(property: 'amount', description: 'Оспариваемая сумма в копейках', type: 'integer', example: 50000),
        new OA\Property(property: 'currency', type: 'string', example: 'RUB'),
        new OA\Property(property: 'reason', description: 'Основание диспута', type: 'string', example: 'Товар не получен'),
        new OA\Property(property: 'note', description: 'Комментарий при разрешении диспута', type: 'string', nullable: true),
    ]
)]

// ─── YooKassa webhook schemas ─────────────────────────────────────────────────

#[OA\Schema(
    schema: 'YooKassaWebhookPayload',
    description: 'Тело вебхука от YooKassa (JSON)',
    required: ['event', 'object'],
    properties: [
        new OA\Property(property: 'event', type: 'string', example: 'payment.succeeded', enum: ['payment.succeeded', 'payment.canceled', 'refund.succeeded']),
        new OA\Property(
            property: 'object',
            properties: [
                new OA\Property(property: 'id', description: 'ID платежа в YooKassa', type: 'string', example: '22d65900-000f-5000-a000-10d000000000'),
                new OA\Property(property: 'status', type: 'string', example: 'succeeded'),
                new OA\Property(property: 'amount', properties: [
                    new OA\Property(property: 'value', type: 'string', example: '100.00'),
                    new OA\Property(property: 'currency', type: 'string', example: 'RUB'),
                ], type: 'object'),
            ],
            type: 'object'
        ),
    ]
)]

// ─── SBP webhook schemas ─────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'SbpWebhookPayload',
    description: 'JSON-вебхук от банка-эквайера СБП',
    required: ['qrId', 'status'],
    properties: [
        new OA\Property(property: 'transactionId', description: 'ID транзакции в банке', type: 'string', example: 'txn-abc123'),
        new OA\Property(property: 'qrId', description: 'ID QR-кода (external_id платежа)', type: 'string', example: 'AS1000123456789'),
        new OA\Property(property: 'status', type: 'string', example: 'PAID', enum: ['PAID', 'CANCELLED', 'EXPIRED']),
        new OA\Property(property: 'amount', properties: [
            new OA\Property(property: 'value', description: 'Сумма в копейках', type: 'integer', example: 10000),
            new OA\Property(property: 'currency', type: 'string', example: 'RUB'),
        ], type: 'object'),
        new OA\Property(property: 'order', description: 'Внутренний ULID платежа', type: 'string', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
    ]
)]

// ─── AlfaBank webhook schemas ─────────────────────────────────────────────────

#[OA\Schema(
    schema: 'AlfaBankWebhookPayload',
    description: 'Form POST уведомление от Альфа-Банка',
    required: ['mdOrder', 'operation'],
    properties: [
        new OA\Property(property: 'mdOrder', description: 'ID заказа в Альфа-Банке (external_id)', type: 'string', example: 'a1b2c3d4-...'),
        new OA\Property(property: 'operation', type: 'string', example: 'deposited', enum: ['deposited', 'refunded', 'reversed', 'declinedByTimeout']),
        new OA\Property(property: 'orderNumber', description: 'Внутренний ULID платежа', type: 'string', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'status', description: '1 = успех, 0 = ошибка', type: 'integer', example: 1),
    ]
)]

// ─── Crypto deposit schemas ───────────────────────────────────────────────────

#[OA\Schema(
    schema: 'CryptoDepositResponse',
    description: 'Крипто-депозит',
    properties: [
        new OA\Property(property: 'depositId', description: 'ID депозита', type: 'string', format: 'ulid', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'paymentId', description: 'Внешний ID платежа', type: 'string', example: 'pay-order-1234'),
        new OA\Property(property: 'status', type: 'string', example: 'awaiting', enum: ['awaiting', 'confirmed', 'expired', 'overpaid']),
        new OA\Property(property: 'asset', type: 'string', example: 'TON', enum: ['TON', 'USDT_TON', 'TRX', 'USDT_TRC20', 'BTC']),
        new OA\Property(property: 'expectedUnits', description: 'Ожидаемая сумма в наименьших единицах актива (наноТОН, сатоши, sun и т.д.)', type: 'integer', example: 125000000),
        new OA\Property(property: 'cryptoAmount', description: 'Читаемое значение суммы', type: 'string', example: '0.125'),
        new OA\Property(property: 'fiatAmountKopecks', description: 'Эквивалент в копейках на момент создания', type: 'integer', example: 50000),
        new OA\Property(property: 'depositAddress', description: 'Адрес для перевода', type: 'string', example: 'UQA...'),
        new OA\Property(property: 'memo', description: 'Числовой комментарий (только для TON-сетей). Для BTC/TRX — null.', type: 'string', example: '123456789', nullable: true),
        new OA\Property(property: 'expiresAt', description: 'Время истечения депозита (ISO 8601)', type: 'string', format: 'date-time'),
        new OA\Property(property: 'qrPayload', description: 'URI для QR-кода (ton://, bitcoin:, tron:)', type: 'string', example: 'ton://transfer/UQA...?amount=125000000&text=123456789'),
        new OA\Property(property: 'txHash', description: 'Хэш транзакции после подтверждения', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'CreateCryptoDepositRequest',
    required: ['payment_id', 'fiat_amount_kopecks', 'asset'],
    properties: [
        new OA\Property(property: 'payment_id', description: 'Внешний ID платежа', type: 'string', example: 'pay-order-1234', maxLength: 255),
        new OA\Property(property: 'fiat_amount_kopecks', description: 'Сумма в копейках для конвертации в крипту', type: 'integer', example: 50000, minimum: 100),
        new OA\Property(property: 'asset', description: 'Криптоактив для приёма', type: 'string', example: 'TON', enum: ['TON', 'USDT_TON', 'TRX', 'USDT_TRC20', 'BTC']),
    ]
)]

// ─── Robokassa webhook schemas ────────────────────────────────────────────────

#[OA\Schema(
    schema: 'RobokassaWebhookPayload',
    description: 'Тело ResultURL-вебхука от Robokassa (form POST)',
    required: ['OutSum', 'InvId', 'SignatureValue', 'Shp_paymentId'],
    properties: [
        new OA\Property(property: 'OutSum', description: 'Сумма платежа в рублях', type: 'string', example: '100.00'),
        new OA\Property(property: 'InvId', description: 'Номер заказа, присвоенный Robokassa', type: 'integer', example: 12345),
        new OA\Property(property: 'SignatureValue', description: 'MD5-подпись для верификации', type: 'string', example: 'A1B2C3D4E5F6...'),
        new OA\Property(property: 'Shp_paymentId', description: 'Внутренний ULID платежа (передаётся при создании)', type: 'string', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
    ]
)]
class ApiDocController {}
