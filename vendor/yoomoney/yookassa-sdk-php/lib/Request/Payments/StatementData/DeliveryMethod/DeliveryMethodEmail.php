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

namespace YooKassa\Request\Payments\StatementData\DeliveryMethod;

use YooKassa\Common\AbstractObject;
use YooKassa\Request\Payments\StatementData\StatementType;
use YooKassa\Validator\Constraints as Assert;

/**
 * Класс, представляющий модель DeliveryMethodEmail.
 *
 * Данные о способе доставки справки на электронную почту.
 *
 * @category Class
 * @package  YooKassa\Model
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 *
 * @property string $email Электронная почта пользователя для отправки справки. Адрес электронной почты должен соответствовать стандарту RFC 5322. Пример: user@example.com.
 */
class DeliveryMethodEmail extends AbstractDeliveryMethod
{
    /**
     * Электронная почта пользователя для отправки справки. Адрес электронной почты должен соответствовать стандарту [RFC 5322](https://www.ietf.org/rfc/rfc5322.txt). Пример: user@example.com.
     * @var string|null
     */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Email]
    private ?string $_email = null;

    public function __construct(?array $data = [])
    {
        parent::__construct($data);
        $this->setType(DeliveryMethodType::EMAIL);
    }

    /**
     * Возвращает электронную почту пользователя для отправки справки.
     *
     * @return string|null Электронная почта пользователя для отправки справки
     */
    public function getEmail(): ?string
    {
        return $this->_email;
    }

    /**
     * Устанавливает электронную почту пользователя для отправки справки.
     *
     * @param string|null $email Электронная почта пользователя для отправки справки
     *
     * @return self
     */
    public function setEmail(?string $email = null): self
    {
        $this->_email = $this->validatePropertyValue('_email', $email);
        return $this;
    }

}
