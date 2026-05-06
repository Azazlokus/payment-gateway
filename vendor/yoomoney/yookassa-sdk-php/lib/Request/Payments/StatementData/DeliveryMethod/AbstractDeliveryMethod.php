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
 * Класс, представляющий модель AbstractDeliveryMethod.
 *
 * Данные о выбранном способе доставки справки.
 *
 * @category Class
 * @package  YooKassa\Model
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 *
 * @property string $type Тип способа доставки справки.
 */
abstract class AbstractDeliveryMethod extends AbstractObject
{
    /**
     * Тип способа доставки справки.
     *
     * @var string|null
     */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Choice(callback: [DeliveryMethodType::class, 'getValidValues'])]
    protected ?string $_type = null;

    /**
     * Возвращает тип способа доставки справки.
     *
     * @return string|null Тип способа доставки справки
     */
    public function getType(): ?string
    {
        return $this->_type;
    }

    /**
     * Устанавливает тип способа доставки справки.
     *
     * @param string|null $type Тип способа доставки справки
     *
     * @return self
     */
    public function setType(?string $type = null): self
    {
        $this->_type = $this->validatePropertyValue('_type', $type);
        return $this;
    }

}
