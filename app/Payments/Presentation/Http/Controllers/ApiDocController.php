<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Payment Gateway API',
    description: 'API платёжного шлюза с интеграцией YooKassa',
)]
#[OA\Server(url: '/api', description: 'Local')]
#[OA\Schema(
    schema: 'PaymentResponse',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'ulid', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
        new OA\Property(property: 'status', type: 'string', enum: ['Pending', 'Succeeded', 'Cancelled', 'Refunded'], example: 'Pending'),
        new OA\Property(property: 'amount', type: 'integer', description: 'Сумма в копейках', example: 10000),
        new OA\Property(property: 'currency', type: 'string', example: 'RUB'),
        new OA\Property(property: 'confirmation_url', type: 'string', nullable: true, example: 'https://yookassa.ru/checkout/payments/22d65900-000f-5000-a000-10d000000000', description: 'URL страницы оплаты YooKassa'),
        new OA\Property(property: 'external_id', type: 'string', nullable: true, example: '22d65900-000f-5000-a000-10d000000000', description: 'ID платежа в YooKassa'),
        new OA\Property(property: 'payment_method_id', type: 'string', nullable: true, description: 'ID сохранённого метода оплаты (для рекуррентных платежей)'),
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
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'payment_error'),
        new OA\Property(property: 'message', type: 'string', example: 'Payment not found: 01HV9Z7BKQE4GNKR2XQVP0M8T'),
    ]
)]
class ApiDocController
{
}
