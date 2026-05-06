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

use YooKassa\Common\Errors\ErrorCode;
use YooKassa\Common\Exceptions\ResponseProcessingException;

/**
 * @internal
 */
class ResponseProcessingExceptionTest extends ApiExceptionTest
{
    public function getTestInstance($message = '', $code = 0, $responseHeaders = [], $responseBody = ''): ResponseProcessingException
    {
        return new ResponseProcessingException($responseHeaders, $responseBody);
    }

    public function expectedHttpCode(): int
    {
        return ResponseProcessingException::HTTP_CODE;
    }

    /**
     * @dataProvider descriptionDataProvider
     */
    public function testDescription(string $body): void
    {
        $instance = $this->getTestInstance('', 0, [], $body);
        $tmp = json_decode($body, true);
        if (empty($tmp['description'])) {
            self::assertEquals('Error code: unknown.', $instance->getMessage());
        } else {
            self::assertEquals($tmp['description'] . '. Error code: unknown.', $instance->getMessage());
        }
    }

    public static function descriptionDataProvider(): array
    {
        return [
            ['{}'],
            ['{"description":"test"}'],
            ['{"description":"У попа была собака"}'],
        ];
    }

    /**
     * @dataProvider retryAfterDataProvider
     */
    public function testRetryAfter(string $body): void
    {
        $instance = $this->getTestInstance('', 0, [], $body);
        $tmp = json_decode($body, true);
        if (empty($tmp['retry_after'])) {
            self::assertNull($instance->retryAfter);
        } else {
            self::assertEquals($tmp['retry_after'], $instance->retryAfter);
        }
    }

    public static function retryAfterDataProvider(): array
    {
        return [
            ['{}'],
            ['{"retry_after":-20}'],
            ['{"retry_after":123}'],
        ];
    }

    /**
     * @dataProvider typeDataProvider
     */
    public function testType(string $body): void
    {
        $instance = $this->getTestInstance('', 0, [], $body);
        $tmp = json_decode($body, true);
        if (empty($tmp['type'])) {
            self::assertNull($instance->type);
        } else {
            self::assertEquals($tmp['type'], $instance->type);
        }
    }

    public static function typeDataProvider(): array
    {
        return [
            ['{}'],
            ['{"type":"server_error"}'],
            ['{"type":"error"}'],
        ];
    }

    /**
     * @dataProvider messageDataProvider
     *
     * @param mixed $body
     */
    public function testMessage($body): void
    {
        $instance = $this->getTestInstance('', 0, [], $body);

        $tmp = json_decode($body, true);
        $message = '';

        if (!empty($tmp['description'])) {
            $message = $tmp['description'] . '. ';
        }
        if (empty($tmp['code']) || !in_array($tmp['code'], ErrorCode::getValidValues(), true)) {
            $message .= 'Error code: unknown. ';
        } else {
            $message .= 'Error code: ' . $tmp['code'] . '. ';
        }
        if (!empty($tmp['parameter'])) {
            $message .= 'Parameter name: ' . $tmp['parameter'] . '. ';
        }
        self::assertEquals(trim($message), trim($instance->getMessage()));

        if (empty($tmp['retry_after'])) {
            self::assertNull($instance->retryAfter);
            self::assertNull($instance->getError()->getRetryAfter());
        } else {
            self::assertEquals($tmp['retry_after'], $instance->retryAfter);
            self::assertEquals($tmp['retry_after'], $instance->getError()->getRetryAfter());
        }
        if (empty($tmp['type'])) {
            self::assertNull($instance->type);
            self::assertNull($instance->getError()->getType());
        } else {
            self::assertEquals($tmp['type'], $instance->type);
            self::assertEquals($tmp['type'], $instance->getError()->getType());
        }
    }

    public static function messageDataProvider(): array
    {
        return [
            ['{}'],
            ['{"code":"internal_server_error","description":"Internal server error"}'],
            ['{"code":"internal_server_error","description":"Invalid parameter value","parameter":"shop_id"}'],
            ['{"code":"internal_server_error","description":"Invalid parameter value","parameter":"shop_id","type":"test"}'],
            ['{"code":"internal_server_error","description":"Invalid parameter value","parameter":"shop_id","retry_after":333}'],
        ];
    }

    public function testExceptionCode(): void
    {
        $instance = $this->getTestInstance();
        self::assertEquals($this->expectedHttpCode(), $instance->getCode());
    }
}
