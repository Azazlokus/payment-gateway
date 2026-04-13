<?php

declare(strict_types=1);

namespace App\Payments\Application\DTOs;

/**
 * Опциональные параметры создания платежа.
 * Вынесены в отдельный DTO чтобы не раздувать сигнатуру провайдера.
 */
final readonly class CreatePaymentOptionsDTO
{
    public function __construct(
        public ?ReceiptDTO $receipt          = null,
        public string      $confirmationType = 'redirect', // redirect|embedded|qr|mobile_application
        public ?string     $paymentMethodType = null,       // bank_card|sbp|yoo_money|sberbank|tinkoff_bank|...
        public bool        $savePaymentMethod = false,      // сохранить метод для recurring
        public ?string     $paymentMethodId   = null,       // ID сохранённого метода для recurring-списания
    ) {
    }
}
