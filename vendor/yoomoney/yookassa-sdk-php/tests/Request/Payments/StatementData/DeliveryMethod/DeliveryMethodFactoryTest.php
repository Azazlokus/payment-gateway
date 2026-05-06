<?php

/*
 * The MIT License
 *
 * Copyright (c) 2026 "YooMoney", NBСO LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Tests\YooKassa\Request\Payments\StatementData\DeliveryMethod;

use Faker\Factory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Random;
use YooKassa\Request\Payments\StatementData\DeliveryMethod\AbstractDeliveryMethod;
use YooKassa\Request\Payments\StatementData\DeliveryMethod\DeliveryMethodFactory;
use YooKassa\Request\Payments\StatementData\DeliveryMethod\DeliveryMethodType;
use YooKassa\Request\Payments\StatementData\StatementType;

/**
 * DeliveryMethodFactoryTest
 *
 * @category    ClassTest
 * @author      cms@yoomoney.ru
 * @link        https://yookassa.ru/developers/api
 */
class DeliveryMethodFactoryTest extends TestCase
{
    /**
     * @dataProvider validTypeDataProvider
     */
    public function testFactory(string $type): void
    {
        $instance = $this->getTestInstance();
        $paymentData = $instance->factory($type);
        self::assertNotNull($paymentData);
        self::assertInstanceOf(AbstractDeliveryMethod::class, $paymentData);
        self::assertEquals($type, $paymentData->getType());
    }

    /**
     * @dataProvider invalidTypeDataProvider
     *
     * @param mixed $type
     */
    public function testInvalidFactory($type): void
    {
        $this->expectException(InvalidArgumentException::class);
        $instance = $this->getTestInstance();
        $instance->factory($type);
    }

    /**
     * @dataProvider validArrayDataProvider
     */
    public function testFactoryFromArray(array $options): void
    {
        $instance = $this->getTestInstance();
        $paymentData = $instance->factoryFromArray($options);
        self::assertNotNull($paymentData);
        self::assertInstanceOf(AbstractDeliveryMethod::class, $paymentData);

        foreach ($options as $property => $value) {
            self::assertEquals($paymentData->{$property}, $value);
        }

        $type = $options['type'];
        unset($options['type']);
        $paymentData = $instance->factoryFromArray($options, $type);
        self::assertNotNull($paymentData);
        self::assertInstanceOf(AbstractDeliveryMethod::class, $paymentData);

        self::assertEquals($type, $paymentData->getType());
        foreach ($options as $property => $value) {
            self::assertEquals($paymentData->{$property}, $value);
        }
    }

    /**
     * @dataProvider invalidDataArrayDataProvider
     *
     * @param mixed $options
     */
    public function testInvalidFactoryFromArray($options): void
    {
        $this->expectException(InvalidArgumentException::class);
        $instance = $this->getTestInstance();
        $instance->factoryFromArray($options);
    }

    public static function validTypeDataProvider(): array
    {
        $result = [];
        foreach (DeliveryMethodType::getEnabledValues() as $value) {
            $result[] = [$value];
        }

        return $result;
    }

    public static function invalidTypeDataProvider(): array
    {
        return [
            [''],
            [0],
            [1],
            [-1],
            ['5'],
            [Random::str(10)],
        ];
    }

    public static function validArrayDataProvider(): array
    {
        return [
            [
                [
                    'type' => DeliveryMethodType::EMAIL,
                    'email' => Factory::create()->email,
                ],
                [
                    'type' => DeliveryMethodType::EMAIL,
                    'email' => Factory::create()->email,
                ],
            ],
        ];
    }

    public static function invalidDataArrayDataProvider(): array
    {
        return [
            [[]],
            [['type' => 'test']],
            [
                [
                    'type' => DeliveryMethodType::EMAIL,
                    'email' => Factory::create()->text(20),
                ],
            ],
            [
                [
                    'type' => StatementType::PAYMENT_OVERVIEW,
                ],
            ],
            [
                [
                    'test' => DeliveryMethodType::EMAIL,
                ],
            ],
        ];
    }

    protected function getTestInstance(): DeliveryMethodFactory
    {
        return new DeliveryMethodFactory();
    }
}
