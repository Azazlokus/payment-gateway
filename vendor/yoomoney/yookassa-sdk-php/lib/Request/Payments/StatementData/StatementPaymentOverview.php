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

use YooKassa\Request\Payments\StatementData\DeliveryMethod\AbstractDeliveryMethod;
use YooKassa\Request\Payments\StatementData\DeliveryMethod\DeliveryMethodFactory;
use YooKassa\Validator\Constraints as Assert;

/**
 * Класс, представляющий модель StatementPaymentOverview.
 *
 * Реквизиты для пополнения баланса телефона.
 *
 * @category Class
 * @package  YooKassa\Model
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 *
 * @property AbstractDeliveryMethod $delivery_method Данные о выбранном способе доставки справки.
 */
class StatementPaymentOverview extends AbstractStatement
{
    /**
     * @var AbstractDeliveryMethod|null Данные о выбранном способе доставки справки.
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    #[Assert\Type(AbstractDeliveryMethod::class)]
    private ?AbstractDeliveryMethod $_delivery_method = null;

    public function __construct(?array $data = [])
    {
        parent::__construct($data);
        $this->setType(StatementType::PAYMENT_OVERVIEW);
    }

    /**
     * Возвращает данные о выбранном способе доставки справки.
     *
     * @return AbstractDeliveryMethod|null
     */
    public function getDeliveryMethod(): ?AbstractDeliveryMethod
    {
        return $this->_delivery_method;
    }

    /**
     * Устанавливает данные о выбранном способе доставки справки.
     *
     * @param AbstractDeliveryMethod|array|null $delivery_method
     *
     * @return self
     */
    public function setDeliveryMethod(mixed $delivery_method = null): self
    {
        if (is_array($delivery_method)) {
            $delivery_method = (new DeliveryMethodFactory())->factoryFromArray($delivery_method);
        }
        $this->_delivery_method = $this->validatePropertyValue('_delivery_method', $delivery_method);
        return $this;
    }

}

