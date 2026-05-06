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

namespace YooKassa\Request\Payments\StatementData;

use InvalidArgumentException;
use YooKassa\Request\Payments\ReceiverData\AbstractReceiver;

/**
 * Класс, представляющий модель StatementFactory.
 *
 * Фабрика создания объекта данных для отправки справки из массива.
 *
 * @category Class
 * @package  YooKassa\Request
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 */
class StatementFactory
{
    private array $typeClassMap = [
        StatementType::PAYMENT_OVERVIEW => 'StatementPaymentOverview',
    ];

    /**
     * Фабричный метод создания объекта данных для отправки справки по типу.
     *
     * @param string|null $type Тип справки
     *
     * @return AbstractStatement
     */
    public function factory(?string $type = null): AbstractStatement
    {
        if (empty($type)) {
            throw new InvalidArgumentException('Invalid statement type value in statement factory');
        }
        if (!array_key_exists($type, $this->typeClassMap)) {
            throw new InvalidArgumentException('Invalid statement data type "' . $type . '"');
        }
        $className = __NAMESPACE__ . '\\' . $this->typeClassMap[$type];

        return new $className();
    }

    /**
     * Фабричный метод создания объекта данных для отправки справки из массива.
     *
     * @param array|null $data Массив платежных данных
     * @param string|null $type Тип справки
     */
    public function factoryFromArray(?array $data = null, ?string $type = null): AbstractStatement
    {
        if (null === $type) {
            if (array_key_exists('type', $data)) {
                $type = $data['type'];
                unset($data['type']);
            } else {
                throw new InvalidArgumentException(
                    'Parameter type not specified in StatementFactory.factoryFromArray()'
                );
            }
        }
        $paymentData = $this->factory($type);
        foreach ($data as $key => $value) {
            if ($paymentData->offsetExists($key)) {
                $paymentData->offsetSet($key, $value);
            }
        }

        return $paymentData;
    }
}
