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

namespace YooKassa\Request\Invoices\DeliveryMethodData;

use YooKassa\Model\Invoice\DeliveryMethod\DeliveryMethodType;
use YooKassa\Validator\Constraints as Assert;

/**
 * Класс, представляющий модель DeliveryMethodDataSms.
 *
 * Данные для выставления счета с доставкой ссылки на счет в смс.
 *
 * @category Class
 * @package  YooKassa\Model
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 *
 * @property string $phone
*/
class DeliveryMethodDataSms extends AbstractDeliveryMethodData
{
    /**
     * @var string|null Телефон для выставления счета
     */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    private ?string $_phone = null;

    public function __construct(?array $data = [])
    {
        parent::__construct($data);
        $this->setType(DeliveryMethodType::SMS);
    }

    /**
     * Возвращает phone.
     *
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->_phone;
    }

    /**
     * Устанавливает phone.
     *
     * @param string|array|null $phone
     *
     * @return self
     */
    public function setPhone(mixed $phone = null): self
    {
        $this->_phone = $this->validatePropertyValue('_phone', $phone);
        return $this;
    }

}

