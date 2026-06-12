<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Exceptions;

final class WebhookVerificationFailedException extends PaymentException
{
    public function __construct(string $provider, string $reason = '')
    {
        $message = "Webhook verification failed for provider '{$provider}'";

        if ($reason !== '') {
            $message .= ": {$reason}";
        }

        parent::__construct($message, 403);
    }
}
