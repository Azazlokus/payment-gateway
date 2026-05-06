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


namespace Tests\YooKassa\Request\Payments\StatementData;

use Exception;
use Tests\YooKassa\AbstractTestCase;
use Datetime;
use YooKassa\Request\Payments\StatementData\StatementPaymentOverview;

/**
 * StatementPaymentOverviewTest
 *
 * @category    ClassTest
 * @author      cms@yoomoney.ru
 * @link        https://yookassa.ru/developers/api
*/
class StatementPaymentOverviewTest extends AbstractTestCase
{
    protected StatementPaymentOverview $object;

    /**
    * @return StatementPaymentOverview
    */
    protected function getTestInstance(): StatementPaymentOverview
    {
        return new StatementPaymentOverview();
    }

    /**
    * @return void
    */
    public function testStatementPaymentOverviewClassExists(): void
    {
        $this->object = $this->getMockBuilder(StatementPaymentOverview::class)->getMockForAbstractClass();
        $this->assertTrue(class_exists(StatementPaymentOverview::class));
        $this->assertInstanceOf(StatementPaymentOverview::class, $this->object);
    }

    /**
    * Test property "delivery_method"
    * @dataProvider validDeliveryMethodDataProvider
    * @param mixed $value
    *
    * @return void
    * @throws Exception
    */
    public function testDeliveryMethod(mixed $value): void
    {
        $instance = $this->getTestInstance();
        $instance->setDeliveryMethod($value);
        self::assertNotNull($instance->getDeliveryMethod());
        self::assertNotNull($instance->delivery_method);
        self::assertEquals($value, is_array($value) ? $instance->getDeliveryMethod()->toArray() : $instance->getDeliveryMethod());
        self::assertEquals($value, is_array($value) ? $instance->delivery_method->toArray() : $instance->delivery_method);
    }

    /**
    * Test invalid property "delivery_method"
    * @dataProvider invalidDeliveryMethodDataProvider
    * @param mixed $value
    * @param string $exceptionClass
    *
    * @return void
    */
    public function testInvalidDeliveryMethod(mixed $value, string $exceptionClass): void
    {
        $instance = $this->getTestInstance();

        $this->expectException($exceptionClass);
        $instance->setDeliveryMethod($value);
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function validDeliveryMethodDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_delivery_method'));
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function invalidDeliveryMethodDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_delivery_method'));
    }
}
