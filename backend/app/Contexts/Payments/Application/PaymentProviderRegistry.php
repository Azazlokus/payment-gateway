<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application;

use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use InvalidArgumentException;

/**
 * Реестр всех зарегистрированных провайдеров оплаты.
 * Позволяет выбирать провайдера динамически — по имени из запроса
 * или по полю provider, сохранённому у конкретного платежа.
 */
final class PaymentProviderRegistry
{
    /** @var array<string, PaymentProviderInterface> */
    private array $providers = [];

    public function register(PaymentProviderInterface $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    public function resolve(string $name): PaymentProviderInterface
    {
        return $this->providers[$name]
            ?? throw new InvalidArgumentException("Unknown payment provider: '{$name}'");
    }

    public function default(): PaymentProviderInterface
    {
        return $this->resolve(config('payments.default'));
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /** @return array<string> */
    public function names(): array
    {
        return array_keys($this->providers);
    }
}
