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


namespace Tests\YooKassa\Model;

use Exception;
use Tests\YooKassa\AbstractTestCase;
use YooKassa\Request\Payments\StatementData\DeliveryMethod\DeliveryMethodEmail;

/**
 * DeliveryMethodEmailTest
 *
 * @category    ClassTest
 * @author      cms@yoomoney.ru
 * @link        https://yookassa.ru/developers/api
*/
class DeliveryMethodEmailTest extends AbstractTestCase
{
    protected DeliveryMethodEmail $object;

    /**
    * @return DeliveryMethodEmail
    */
    protected function getTestInstance(): DeliveryMethodEmail
    {
        return new DeliveryMethodEmail();
    }

    /**
    * @return void
    */
    public function testDeliveryMethodEmailClassExists(): void
    {
        $this->object = $this->getMockBuilder(DeliveryMethodEmail::class)->getMockForAbstractClass();
        $this->assertTrue(class_exists(DeliveryMethodEmail::class));
        $this->assertInstanceOf(DeliveryMethodEmail::class, $this->object);
    }

    /**
    * Test property "email"
    * @dataProvider validEmailDataProvider
    * @param mixed $value
    *
    * @return void
    * @throws Exception
    */
    public function testEmail(mixed $value): void
    {
        $instance = $this->getTestInstance();
        $instance->setEmail($value);
        self::assertNotNull($instance->getEmail());
        self::assertNotNull($instance->email);
        self::assertEquals($value, $instance->getEmail());
        self::assertEquals($value, $instance->email);
    }

    /**
    * Test invalid property "email"
    * @dataProvider invalidEmailDataProvider
    * @param mixed $value
    * @param string $exceptionClass
    *
    * @return void
    */
    public function testInvalidEmail(mixed $value, string $exceptionClass): void
    {
        $instance = $this->getTestInstance();

        $this->expectException($exceptionClass);
        $instance->setEmail($value);
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function validEmailDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_email'));
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function invalidEmailDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_email'));
    }
}
