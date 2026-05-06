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

namespace Tests\YooKassa\Helpers;

use DateTime;
use Exception;
use PHPUnit\Framework\TestCase;
use stdClass;
use YooKassa\Helpers\StringObject;
use YooKassa\Helpers\TypeCast;

/**
 * @internal
 */
class TypeCastTest extends TestCase
{
    /**
     * @dataProvider canCastToStringDataProvider
     *
     * @param mixed $value
     * @param bool $can
     */
    public function testCanCastToString(mixed $value, bool $can): void
    {
        if ($can) {
            self::assertTrue(TypeCast::canCastToString($value));
        } else {
            self::assertFalse(TypeCast::canCastToString($value));
        }
    }

    /**
     * @dataProvider canCastToEnumStringDataProvider
     *
     * @param mixed $value
     * @param bool $can
     */
    public function testCanCastToEnumString(mixed $value, bool $can): void
    {
        if ($can) {
            self::assertTrue(TypeCast::canCastToEnumString($value));
        } else {
            self::assertFalse(TypeCast::canCastToEnumString($value));
        }
    }

    /**
     * @dataProvider canCastToDateTimeDataProvider
     *
     * @param mixed $value
     * @param bool $can
     */
    public function testCanCastToDateTime(mixed $value, bool $can): void
    {
        if ($can) {
            self::assertTrue(TypeCast::canCastToDateTime($value));
        } else {
            self::assertFalse(TypeCast::canCastToDateTime($value));
        }
    }

    /**
     * @dataProvider castToDateTimeDataProvider
     *
     * @param mixed $value
     * @param int $expected
     * @param mixed $valid
     */
    public function testCastToDateTime(mixed $value, int $expected, mixed $valid): void
    {
        $instance = TypeCast::castToDateTime($value);
        if ($valid) {
            if ($value instanceof DateTime) {
                self::assertEquals($value->getTimestamp(), $instance->getTimestamp());
                self::assertNotSame($value, $instance);
            } else {
                self::assertEquals($expected, $instance->getTimestamp());
            }
        } else {
            self::assertNull($instance);
        }
    }

    public static function canCastToStringDataProvider(): array
    {
        return [
            ['', true],
            ['test_string', true],
            [0, true],
            [1, true],
            [-1, true],
            [0.0, true],
            [-0.001, true],
            [0.001, true],
            [true, false],
            [false, false],
            [null, false],
            [[], false],
            [new stdClass(), false],
            [fopen(__FILE__, 'rb'), false],
            [new StringObject('test'), true],
        ];
    }

    public static function canCastToEnumStringDataProvider(): array
    {
        return [
            ['', false],
            ['test_string', true],
            [0, false],
            [1, false],
            [-1, false],
            [0.0, false],
            [-0.001, false],
            [0.001, false],
            [true, false],
            [false, false],
            [null, false],
            [[], false],
            [new stdClass(), false],
            [fopen(__FILE__, 'rb'), false],
            [new StringObject('test'), true],
        ];
    }

    public static function canCastToDateTimeDataProvider(): array
    {
        return [
            ['', false],
            ['test_string', true],
            [0, true],
            [1, true],
            [-1, false],
            [0.0, true],
            [-0.001, false],
            [0.001, true],
            [true, false],
            [false, false],
            [null, false],
            [[], false],
            [new stdClass(), false],
            [fopen(__FILE__, 'rb'), false],
            [new StringObject('test'), true],
            [new DateTime(), true],
        ];
    }

    /**
     * @throws Exception
     */
    public static function castToDateTimeDataProvider(): array
    {
        $result = [];
        date_default_timezone_set('UTC');
        $time = time();
        $result[] = [$time, $time, true];
        $result[] = [date(YOOKASSA_DATE, $time), $time, true];
        $result[] = [new DateTime(date(YOOKASSA_DATE, $time)), $time, true];
        $result[] = ['3234-new-23', $time, false];
        $result[] = [[], $time, false];

        return $result;
    }
}
