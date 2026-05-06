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

namespace Tests\YooKassa\Common\Exceptions;

use DateTime;
use stdClass;
use YooKassa\Common\Exceptions\InvalidPropertyException;
use YooKassa\Common\Exceptions\InvalidPropertyValueTypeException;

/**
 * @internal
 */
class InvalidPropertyValueTypeExceptionTest extends InvalidPropertyExceptionTest
{
    /**
     * @dataProvider validTypeDataProvider
     *
     * @param mixed $value
     */
    public function testGetType($value, string $type): void
    {
        $instance = $this->getTestInstance('', '', $value);
        self::assertEquals($type, $instance->getType());
    }

    public static function validTypeDataProvider()
    {
        return [
            [null, 'null'],
            ['', 'string'],
            ['value', 'string'],
            [['test'], 'array'],
            [new stdClass(), 'stdClass'],
            [new DateTime(), 'DateTime'],
            [new InvalidPropertyException(), 'YooKassa\\Common\\Exceptions\\InvalidPropertyException'],
            [fopen(__FILE__, 'rb'), 'resource'],
            [true, 'boolean'],
            [false, 'boolean'],
            [0, 'integer'],
            [0.01, 'double'],
        ];
    }

    /**
     * @param null $value
     */
    protected function getTestInstance(string $message, string $property, $value = null): InvalidPropertyValueTypeException
    {
        return new InvalidPropertyValueTypeException($message, 0, $property, $value);
    }
}
