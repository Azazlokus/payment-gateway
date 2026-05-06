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

namespace Tests\YooKassa\Request\Receipts;

use Exception;
use YooKassa\Helpers\Random;
use YooKassa\Request\Receipts\RefundReceiptResponse;

/**
 * RefundReceiptResponseTest
 *
 * @category    ClassTest
 * @author      cms@yoomoney.ru
 * @link        https://yookassa.ru/developers/api
 */
class RefundReceiptResponseTest extends AbstractTestReceiptResponse
{
    protected string $type = 'refund';

    protected RefundReceiptResponse $object;

    /**
     * @return void
     */
    public function testReceiptClassExists(): void
    {
        $this->object = $this->getMockBuilder(RefundReceiptResponse::class)->getMockForAbstractClass();
        $this->assertTrue(class_exists(RefundReceiptResponse::class));
        $this->assertInstanceOf(RefundReceiptResponse::class, $this->object);
    }

    /**
     * Test property "refund_id"
     * @dataProvider validRefundIdDataProvider
     * @param mixed $value
     *
     * @return void
     * @throws Exception
     */
    public function testRefundId(mixed $value): void
    {
        $instance = $this->getTestInstance();
        self::assertEmpty($instance->getRefundId());
        self::assertEmpty($instance->refund_id);
        $instance->setRefundId($value);
        self::assertEquals($value, is_array($value) ? $instance->getRefundId()->toArray() : $instance->getRefundId());
        self::assertEquals($value, is_array($value) ? $instance->refund_id->toArray() : $instance->refund_id);
        if (!empty($value)) {
            self::assertNotNull($instance->getRefundId());
            self::assertNotNull($instance->refund_id);
            self::assertLessThanOrEqual(36, is_string($instance->getRefundId()) ? mb_strlen($instance->getRefundId()) : $instance->getRefundId());
            self::assertLessThanOrEqual(36, is_string($instance->refund_id) ? mb_strlen($instance->refund_id) : $instance->refund_id);
            self::assertGreaterThanOrEqual(36, is_string($instance->getRefundId()) ? mb_strlen($instance->getRefundId()) : $instance->getRefundId());
            self::assertGreaterThanOrEqual(36, is_string($instance->refund_id) ? mb_strlen($instance->refund_id) : $instance->refund_id);
        }
    }

    /**
     * Test invalid property "refund_id"
     * @dataProvider invalidRefundIdDataProvider
     * @param mixed $value
     * @param string $exceptionClass
     *
     * @return void
     */
    public function testInvalidRefundId(mixed $value, string $exceptionClass): void
    {
        $instance = $this->getTestInstance();

        $this->expectException($exceptionClass);
        $instance->setRefundId($value);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function validRefundIdDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_refund_id'));
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function invalidRefundIdDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_refund_id'));
    }

    /**
     * @param array $options
     * @return void
     */
    public function testSpecificProperties(array $options = []): void
    {
        $options = ['refund_id' => Random::str(36, 36)];
        $instance = $this->getTestInstance($options);
        self::assertEquals($options['refund_id'], $instance->getRefundId());
    }

    /**
     * @param mixed|null $options
     * @return RefundReceiptResponse
     */
    protected function getTestInstance(mixed $options = null): RefundReceiptResponse
    {
        return new RefundReceiptResponse($options);
    }

    protected function addSpecificProperties(mixed $options, mixed $i): array
    {
        $array = [
            Random::str(30),
            Random::str(40),
        ];
        $options['refund_id'] = !$this->valid
            ? (Random::value($array))
            : Random::value([null, '', Random::str(RefundReceiptResponse::LENGTH_REFUND_ID)]);

        return $options;
    }
}
