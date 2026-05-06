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

namespace Tests\YooKassa\Helpers\Config;

use PHPUnit\Framework\TestCase;
use YooKassa\Helpers\Config\ConfigurationLoader;

/**
 * @internal
 */
class ConfigurationLoaderTest extends TestCase
{
    /**
     * @dataProvider validDataProvider
     *
     * @param mixed $fileName
     */
    public function testLoad($fileName): void
    {
        $loader = new ConfigurationLoader();
        $loader->load($fileName);
        if (empty($fileName)) {
            $fileName = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'configuration.json';
        }
        $data = file_get_contents($fileName);
        self::assertEquals(json_decode($data, true), $loader->getConfig());
    }

    public static function validDataProvider()
    {
        return [
            [null],
            [''],
            [__DIR__ . DIRECTORY_SEPARATOR . 'test_config.json'],
        ];
    }
}
