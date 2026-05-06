<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Webhook;

use App\Payments\Domain\Exceptions\WebhookVerificationFailedException;
use Illuminate\Contracts\Cache\Repository;

final class ReplayProtector
{
    public function __construct(
        private readonly Repository $cache,
    ) {}

    /**
     * @throws WebhookVerificationFailedException
     */
    public function verify(string $nonce, int $timestamp): void
    {
        if (abs(time() - $timestamp) > 300) {
            throw new WebhookVerificationFailedException('replay_protection', 'Webhook timestamp too old or future-dated');
        }

        $key = "webhook_nonce:{$nonce}";

        if ($this->cache->has($key)) {
            throw new WebhookVerificationFailedException('replay_protection', 'Webhook nonce already used');
        }

        $this->cache->put($key, 1, 600);
    }
}
