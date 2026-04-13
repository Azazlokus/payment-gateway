<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_creates_money_in_rub(): void
    {
        $money = Money::ofRub(10_000);

        $this->assertSame(10_000, $money->amount());
        $this->assertSame(Currency::RUB, $money->currency());
    }

    public function test_throws_on_negative_amount(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/negative/');

        new Money(-1, Currency::RUB);
    }

    public function test_throws_when_amount_below_minimum(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Minimum/');

        Money::ofRub(99);
    }

    public function test_minimum_valid_amount_is_100(): void
    {
        $money = Money::ofRub(100);

        $this->assertSame(100, $money->amount());
    }

    public function test_formatted_shows_rub_with_two_decimals(): void
    {
        $money = Money::ofRub(12_350);

        $this->assertSame('123.50 RUB', $money->formatted());
    }

    public function test_equals_same_amount_and_currency(): void
    {
        $a = Money::ofRub(5_000);
        $b = Money::ofRub(5_000);

        $this->assertTrue($a->equals($b));
    }

    public function test_not_equals_different_amount(): void
    {
        $a = Money::ofRub(5_000);
        $b = Money::ofRub(6_000);

        $this->assertFalse($a->equals($b));
    }

    public function test_is_greater_than(): void
    {
        $big   = Money::ofRub(10_000);
        $small = Money::ofRub(5_000);

        $this->assertTrue($big->isGreaterThan($small));
        $this->assertFalse($small->isGreaterThan($big));
    }

    public function test_is_not_greater_than_equal(): void
    {
        $a = Money::ofRub(5_000);
        $b = Money::ofRub(5_000);

        $this->assertFalse($a->isGreaterThan($b));
    }

    public function test_throws_comparing_different_currencies(): void
    {
        $rub = Money::ofRub(5_000);
        $usd = new Money(5_000, Currency::USD);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/different currencies/');

        $rub->isGreaterThan($usd);
    }
}
