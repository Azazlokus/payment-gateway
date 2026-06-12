<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Contexts\Payments\Domain\Aggregates\PaymentMethod;
use App\Contexts\Payments\Domain\Enums\PaymentMethodType;
use App\Contexts\Payments\Domain\Events\PaymentMethodWasDeleted;
use App\Contexts\Payments\Domain\Events\PaymentMethodWasTokenized;
use App\Contexts\Payments\Domain\ValueObjects\CardFingerprint;
use App\Contexts\Payments\Domain\ValueObjects\PaymentMethodId;
use App\Contexts\Payments\Domain\ValueObjects\TenantId;
use PHPUnit\Framework\TestCase;

final class PaymentMethodAggregateTest extends TestCase
{
    public function test_create_records_tokenized_event(): void
    {
        $method = PaymentMethod::create(
            id: PaymentMethodId::generate(),
            tenantId: TenantId::generate(),
            customerId: 'cust_123',
            provider: 'yookassa',
            type: PaymentMethodType::Card,
            token: 'tok_abc123',
            last4: '4242',
            brand: 'Visa',
            expiresAt: '12/2028',
            fingerprint: CardFingerprint::compute('4242', 'Visa', '12', '2028'),
        );

        $events = $method->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentMethodWasTokenized::class, $events[0]);
        $this->assertSame('cust_123', $events[0]->customerId);
        $this->assertSame('yookassa', $events[0]->provider);
        $this->assertSame('card', $events[0]->type);
        $this->assertSame('4242', $events[0]->last4);
    }

    public function test_deactivate_records_deleted_event(): void
    {
        $method = PaymentMethod::create(
            id: PaymentMethodId::generate(),
            tenantId: null,
            customerId: 'cust_456',
            provider: 'cloudpayments',
            type: PaymentMethodType::Card,
            token: 'tok_def456',
            last4: '1234',
            brand: 'MasterCard',
        );

        $method->pullDomainEvents();

        $method->deactivate();

        $this->assertFalse($method->isActive());

        $events = $method->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentMethodWasDeleted::class, $events[0]);
        $this->assertSame('cust_456', $events[0]->customerId);
    }

    public function test_reactivate_refreshes_card_and_records_tokenized_event(): void
    {
        $method = PaymentMethod::restore(
            id: PaymentMethodId::generate(),
            tenantId: null,
            customerId: 'cust_555',
            provider: 'yookassa',
            type: PaymentMethodType::Card,
            token: 'tok_old',
            last4: '0000',
            brand: 'Visa',
            expiresAt: '01/2027',
            fingerprint: CardFingerprint::fromString('hash_value'),
            isActive: false,
        );

        $method->reactivate('tok_new', '4242', 'MasterCard', '12/2030');

        $this->assertTrue($method->isActive());
        $this->assertSame('tok_new', $method->token());
        $this->assertSame('4242', $method->last4());
        $this->assertSame('MasterCard', $method->brand());
        $this->assertSame('12/2030', $method->expiresAt());

        $events = $method->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentMethodWasTokenized::class, $events[0]);
        $this->assertSame('cust_555', $events[0]->customerId);
        $this->assertSame('4242', $events[0]->last4);
    }

    public function test_restore_does_not_record_events(): void
    {
        $method = PaymentMethod::restore(
            id: PaymentMethodId::generate(),
            tenantId: TenantId::generate(),
            customerId: 'cust_789',
            provider: 'yookassa',
            type: PaymentMethodType::Card,
            token: 'tok_existing',
            last4: '5678',
            brand: 'Visa',
            expiresAt: '01/2030',
            fingerprint: CardFingerprint::fromString('hash_value'),
            isActive: true,
        );

        $this->assertEmpty($method->pullDomainEvents());
        $this->assertTrue($method->isActive());
        $this->assertSame('5678', $method->last4());
        $this->assertSame('Visa', $method->brand());
    }

    public function test_getters_return_correct_values(): void
    {
        $id = PaymentMethodId::generate();
        $tenantId = TenantId::generate();
        $fingerprint = CardFingerprint::compute('4242', 'Visa', '12', '2028');

        $method = PaymentMethod::create(
            id: $id,
            tenantId: $tenantId,
            customerId: 'cust_100',
            provider: 'yookassa',
            type: PaymentMethodType::Card,
            token: 'tok_test',
            last4: '4242',
            brand: 'Visa',
            expiresAt: '12/2028',
            fingerprint: $fingerprint,
            metadata: ['source' => 'checkout'],
        );

        $this->assertTrue($id->equals($method->id()));
        $this->assertTrue($tenantId->equals($method->tenantId()));
        $this->assertSame('cust_100', $method->customerId());
        $this->assertSame('yookassa', $method->provider());
        $this->assertSame(PaymentMethodType::Card, $method->type());
        $this->assertSame('tok_test', $method->token());
        $this->assertSame('12/2028', $method->expiresAt());
        $this->assertTrue($fingerprint->equals($method->fingerprint()));
        $this->assertSame(['source' => 'checkout'], $method->metadata());
    }
}
