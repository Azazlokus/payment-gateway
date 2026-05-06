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

namespace YooKassa\Request\PaymentMethods;

use YooKassa\Common\AbstractRequestInterface;
use YooKassa\Request\PaymentMethods\ConfirmationData\AbstractConfirmation;

/**
 * Класс, представляющий модель CreatePaymentMethodRequest.
 *
 * Данные для проверки и сохранения способа оплаты.
 *
 * @category Class
 * @package  YooKassa\Model
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 *
 * @property string $type Код способа оплаты. Возможное значение: ~`bank_card` — банковская карта.
 * @property PaymentMethodCard $card Данные банковской карты (необходимы, если вы собираете данные карты пользователей на своей стороне).
 * @property PaymentMethodHolder $holder Данные магазина, для которого сохраняется способ оплаты.
 * @property string $client_ip IPv4 или IPv6-адрес пользователя. Если не указан, используется IP-адрес TCP-подключения.
 * @property string $clientIp IPv4 или IPv6-адрес пользователя. Если не указан, используется IP-адрес TCP-подключения.
 * @property AbstractConfirmation $confirmation Данные, необходимые для инициирования сценария подтверждения привязки.
 */
interface CreatePaymentMethodRequestInterface extends AbstractRequestInterface
{
    /**
     * Возвращает type.
     *
     * @return string|null
     */
    public function getType(): ?string;

    /**
     * Устанавливает type.
     *
     * @param string|null $type Код способа оплаты. Возможное значение: ~`bank_card` — банковская карта.
     *
     * @return CreatePaymentMethodRequest
     */
    public function setType(?string $type = null): CreatePaymentMethodRequest;

    /**
     * Возвращает card.
     *
     * @return PaymentMethodCard|null
     */
    public function getCard(): ?PaymentMethodCard;

    /**
     * Устанавливает card.
     *
     * @param PaymentMethodCard|array|null $card Данные для проверки и сохранения банковской карты.
     *
     * @return CreatePaymentMethodRequest
     */
    public function setCard(mixed $card): CreatePaymentMethodRequest;

    /**
     * Возвращает holder.
     *
     * @return PaymentMethodHolder|null
     */
    public function getHolder(): ?PaymentMethodHolder;

    /**
     * Устанавливает holder.
     *
     * @param PaymentMethodHolder|array|null $holder Данные магазина, для которого сохраняется способ оплаты.
     *
     * @return CreatePaymentMethodRequest
     */
    public function setHolder(mixed $holder = null): CreatePaymentMethodRequest;

    /**
     * Возвращает client_ip.
     *
     * @return string|null
     */
    public function getClientIp(): ?string;

    /**
     * Устанавливает client_ip.
     *
     * @param string|null $client_ip IPv4 или IPv6-адрес пользователя. Если не указан, используется IP-адрес TCP-подключения.
     *
     * @return CreatePaymentMethodRequest
     */
    public function setClientIp(?string $client_ip = null): CreatePaymentMethodRequest;

    /**
     * Возвращает confirmation.
     *
     * @return AbstractConfirmation|null
     */
    public function getConfirmation(): ?AbstractConfirmation;

    /**
     * Устанавливает confirmation.
     *
     * @param AbstractConfirmation|array|null $confirmation Данные, необходимые для инициирования сценария подтверждения привязки.
     *
     * @return CreatePaymentMethodRequest
     */
    public function setConfirmation(mixed $confirmation = null): CreatePaymentMethodRequest;
}
