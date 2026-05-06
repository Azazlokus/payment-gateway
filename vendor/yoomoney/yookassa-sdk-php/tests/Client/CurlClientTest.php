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

namespace Tests\YooKassa\Client;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use YooKassa\Client\CurlClient;
use YooKassa\Common\Exceptions\ApiConnectionException;
use YooKassa\Common\Exceptions\ApiException;
use YooKassa\Common\Exceptions\AuthorizeException;
use YooKassa\Common\Exceptions\ExtensionNotFoundException;
use YooKassa\Common\HttpVerb;
use YooKassa\Common\LoggerWrapper;

/**
 * @internal
 */
class CurlClientTest extends TestCase
{
    public function testConnectionTimeout(): void
    {
        $client = new CurlClient();
        $client->setConnectionTimeout(10);
        $this->assertEquals(10, $client->getConnectionTimeout());
    }

    public function testTimeout(): void
    {
        $client = new CurlClient();
        $client->setTimeout(10);
        $this->assertEquals(10, $client->getTimeout());
    }

    public function testProxy(): void
    {
        $client = new CurlClient();
        $client->setProxy('proxy_url:8889');
        $this->assertEquals('proxy_url:8889', $client->getProxy());
    }

    /**
     * @dataProvider curlErrorCodeProvider
     *
     * @param mixed $error
     * @param mixed $errn
     * @throws ReflectionException
     */
    public function testHandleCurlError(mixed $error, mixed $errn): void
    {
        $this->expectException(ApiConnectionException::class);
        $client = new CurlClient();
        $reflector = new ReflectionClass(CurlClient::class);
        $method = $reflector->getMethod('handleCurlError');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $method->invokeArgs($client, [$error, $errn]);
    }

    public function testConfig(): void
    {
        $config = ['url' => 'test:url'];
        $client = new CurlClient();
        $client->setConfig($config);
        $this->assertEquals($config, $client->getConfig());
    }

    /**
     * @throws ApiException
     * @throws ExtensionNotFoundException
     * @throws ApiConnectionException
     * @throws AuthorizeException
     */
    public function testCloseConnection(): void
    {
        $wrapped = new ArrayLogger();
        $logger = new LoggerWrapper($wrapped);
        $curlClientMock = $this->getMockBuilder(CurlClient::class)
            ->onlyMethods(['closeCurlConnection', 'sendRequest'])
            ->getMock()
        ;
        $curlClientMock->setLogger($logger);
        $curlClientMock->setConfig(['url' => 'test:url']);
        $curlClientMock->setKeepAlive(false);
        $curlClientMock->setShopId(123);
        $curlClientMock->setShopPassword(234);
        $curlClientMock->expects($this->once())->method('sendRequest')->willReturn([
            ['Header-Name' => 'HeaderValue'],
            '{body:sample}',
            ['http_code' => 200],
        ]);
        $curlClientMock->expects($this->once())->method('closeCurlConnection');
        $curlClientMock->call(
            '',
            HttpVerb::HEAD,
            ['queryParam' => 'value'],
            'testBodyValue',
            ['testHeader' => 'testValue']
        );
    }

    /**
     * @throws ExtensionNotFoundException
     * @throws ApiConnectionException
     * @throws ApiException
     */
    public function testAuthorizeException(): void
    {
        $this->expectException(AuthorizeException::class);
        $client = new CurlClient();
        $client->call(
            '',
            HttpVerb::HEAD,
            ['queryParam' => 'value'],
            'testValue',
            ['testHeader' => 'testValue']
        );
    }

    public static function curlErrorCodeProvider(): array
    {
        return [
            ['error message', CURLE_SSL_CACERT],
            ['error message', CURLE_COULDNT_CONNECT],
            ['error message', 0],
        ];
    }
}
