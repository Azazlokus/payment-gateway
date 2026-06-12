<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contexts\Payments\Domain\Exceptions\WebhookVerificationFailedException;
use App\Contexts\Payments\Infrastructure\Webhook\ReplayProtector;
use Illuminate\Contracts\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ReplayProtectorTest extends TestCase
{
    private Repository&MockInterface $cache;

    private ReplayProtector $protector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = Mockery::mock(Repository::class);
        $this->protector = new ReplayProtector($this->cache);
    }

    // ─── Валидный запрос ──────────────────────────────────────────────────────

    public function test_accepts_valid_nonce_and_recent_timestamp(): void
    {
        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('put')->once();

        // Не бросает исключение
        $this->protector->verify('nonce-abc', time());
        $this->assertTrue(true); // явно помечаем тест как пройденный
    }

    public function test_accepts_timestamp_at_boundary_299_seconds_old(): void
    {
        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('put')->once();

        $this->protector->verify('nonce-xyz', time() - 299);
        $this->assertTrue(true);
    }

    public function test_accepts_timestamp_299_seconds_in_future(): void
    {
        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('put')->once();

        $this->protector->verify('nonce-future', time() + 299);
        $this->assertTrue(true);
    }

    // ─── Устаревший / будущий timestamp ──────────────────────────────────────

    public function test_rejects_timestamp_older_than_300_seconds(): void
    {
        $this->expectException(WebhookVerificationFailedException::class);
        $this->expectExceptionMessage('timestamp');

        $this->cache->shouldNotReceive('has');
        $this->cache->shouldNotReceive('put');

        $this->protector->verify('nonce-old', time() - 301);
    }

    public function test_rejects_timestamp_more_than_300_seconds_in_future(): void
    {
        $this->expectException(WebhookVerificationFailedException::class);

        $this->cache->shouldNotReceive('has');
        $this->cache->shouldNotReceive('put');

        $this->protector->verify('nonce-future', time() + 301);
    }

    public function test_rejects_zero_timestamp(): void
    {
        $this->expectException(WebhookVerificationFailedException::class);

        $this->cache->shouldNotReceive('has');
        $this->cache->shouldNotReceive('put');

        $this->protector->verify('nonce-zero', 0);
    }

    // ─── Повторный nonce (replay) ─────────────────────────────────────────────

    public function test_rejects_duplicate_nonce(): void
    {
        $this->expectException(WebhookVerificationFailedException::class);
        $this->expectExceptionMessage('nonce');

        $this->cache->shouldReceive('has')
            ->once()
            ->with('webhook_nonce:replay-nonce')
            ->andReturn(true);

        $this->cache->shouldNotReceive('put');

        $this->protector->verify('replay-nonce', time());
    }

    public function test_different_nonces_are_both_accepted(): void
    {
        $this->cache->shouldReceive('has')->twice()->andReturn(false);
        $this->cache->shouldReceive('put')->twice();

        $this->protector->verify('nonce-1', time());
        $this->protector->verify('nonce-2', time());

        $this->assertTrue(true);
    }

    // ─── Кэш-взаимодействие ───────────────────────────────────────────────────

    public function test_stores_nonce_with_correct_key_in_cache(): void
    {
        $this->cache->shouldReceive('has')
            ->once()
            ->with('webhook_nonce:my-nonce')
            ->andReturn(false);

        $this->cache->shouldReceive('put')
            ->once()
            ->with('webhook_nonce:my-nonce', 1, 600);

        $this->protector->verify('my-nonce', time());
    }

    public function test_nonce_not_stored_when_timestamp_invalid(): void
    {
        $this->cache->shouldNotReceive('put');

        try {
            $this->protector->verify('nonce-invalid', time() - 999);
        } catch (WebhookVerificationFailedException) {
            // ожидаемо
        }
    }

    public function test_nonce_not_stored_when_already_used(): void
    {
        $this->cache->shouldReceive('has')->once()->andReturn(true);
        $this->cache->shouldNotReceive('put');

        try {
            $this->protector->verify('used-nonce', time());
        } catch (WebhookVerificationFailedException) {
            // ожидаемо
        }
    }
}
