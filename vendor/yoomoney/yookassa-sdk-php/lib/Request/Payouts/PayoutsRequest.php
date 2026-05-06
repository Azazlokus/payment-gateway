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

namespace YooKassa\Request\Payouts;

use DateTime;
use YooKassa\Common\AbstractRequest;
use YooKassa\Model\Payout\PayoutDestinationType;
use YooKassa\Model\Payout\PayoutStatus;
use YooKassa\Validator\Constraints as Assert;

/**
 * Класс, представляющий модель PayoutsRequest.
 *
 * Класс объекта запроса к API для получения списка выплат магазина.
 *
 * @category Class
 * @package  YooKassa\Request
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 *
 * @property null|DateTime $createdAtGte Время создания, от (включительно)
 * @property null|DateTime $created_at_gte Фильтр по времени создания: время должно быть больше указанного значения или равно ему («с такого-то момента включительно»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.gte&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional)
 * @property null|DateTime $createdAtGt Время создания, от (не включая)
 * @property null|DateTime $created_at_gt Фильтр по времени создания: время должно быть больше указанного значения («с такого-то момента, не включая его»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.gt&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional)
 * @property null|DateTime $createdAtLte Время создания, до (включительно)
 * @property null|DateTime $created_at_lte Фильтр по времени создания: время должно быть меньше указанного значения или равно ему («по такой-то момент включительно»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.lte&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional)
 * @property null|DateTime $createdAtLt Время создания, до (не включая)
 * @property null|string $payoutDestinationType Фильтр по коду %[способа получения выплаты](/developers/payouts/getting-started/payout-types-and-limits#types-destination). Пример: ~&#x60;payout_destination.type&#x3D;bank_card&#x60; (optional)
 * @property null|string $payout_destination_type Фильтр по коду %[способа получения выплаты](/developers/payouts/getting-started/payout-types-and-limits#types-destination). Пример: ~&#x60;payout_destination.type&#x3D;bank_card&#x60; (optional)
 * @property null|string $status Статус выплаты
 * @property null|int $limit Ограничение количества объектов выплаты, отображаемых на одной странице выдачи
 * @property null|string $cursor Страница выдачи результатов, которую необходимо отобразить
 */
class PayoutsRequest extends AbstractRequest implements PayoutsRequestInterface
{
    /** Максимальное количество объектов выплаты в выборке */
    public const MAX_LIMIT_VALUE = 100;

    /**
     * @var DateTime|null Время создания, от (включительно)
     */
    #[Assert\DateTime(format: YOOKASSA_DATE)]
    #[Assert\Type('DateTime')]
    private ?DateTime $_created_at_gte = null;

    /**
     * @var DateTime|null Время создания, от (не включая)
     */
    #[Assert\DateTime(format: YOOKASSA_DATE)]
    #[Assert\Type('DateTime')]
    private ?DateTime $_created_at_gt = null;

    /**
     * @var DateTime|null Время создания, до (включительно)
     */
    #[Assert\DateTime(format: YOOKASSA_DATE)]
    #[Assert\Type('DateTime')]
    private ?DateTime $_created_at_lte = null;

    /**
     * @var DateTime|null Время создания, до (не включая)
     */
    #[Assert\DateTime(format: YOOKASSA_DATE)]
    #[Assert\Type('DateTime')]
    private ?DateTime $_created_at_lt = null;

    /**
     * @var string|null Статус выплаты
     */
    #[Assert\Type('string')]
    #[Assert\Choice(callback: [PayoutStatus::class, 'getValidValues'])]
    private ?string $_status = null;

    /**
     * @var string|null Способ получения выплаты
     */
    #[Assert\Type('string')]
    #[Assert\Choice(callback: [PayoutDestinationType::class, 'getValidValues'])]
    private ?string $_payout_destination_type = null;

    /**
     * @var null|int Ограничение количества объектов выплаты
     */
    #[Assert\Type('int')]
    #[Assert\GreaterThanOrEqual(value: 1)]
    #[Assert\LessThanOrEqual(value: self::MAX_LIMIT_VALUE)]
    private ?int $_limit = null;

    /**
     * @var string|null Страница выдачи результатов, которую необходимо отобразить
     */
    #[Assert\Type('string')]
    private ?string $_cursor = null;

    /**
     * Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена.
     *
     * @return null|DateTime Время создания, от (включительно)
     */
    public function getCreatedAtGte(): ?DateTime
    {
        return $this->_created_at_gte;
    }

    /**
     * Проверяет, была ли установлена дата создания от которой выбираются выплаты.
     *
     * @return bool True если дата была установлена, false если нет
     */
    public function hasCreatedAtGte(): bool
    {
        return null !== $this->_created_at_gte;
    }

    /**
     * Устанавливает дату создания от которой выбираются выплаты.
     *
     * @param DateTime|string|null $_created_at_gte Время создания, от (включительно) или null, чтобы удалить значение
     *
     * @return self
     */
    public function setCreatedAtGte(mixed $_created_at_gte = null): self
    {
        $this->_created_at_gte = $this->validatePropertyValue('_created_at_gte', $_created_at_gte);
        return $this;
    }

    /**
     * Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена.
     *
     * @return null|DateTime Время создания, от (не включая)
     */
    public function getCreatedAtGt(): ?DateTime
    {
        return $this->_created_at_gt;
    }

    /**
     * Проверяет, была ли установлена дата создания от которой выбираются выплаты.
     *
     * @return bool True если дата была установлена, false если нет
     */
    public function hasCreatedAtGt(): bool
    {
        return null !== $this->_created_at_gt;
    }

    /**
     * Устанавливает дату создания от которой выбираются выплаты.
     *
     * @param null|DateTime|int|string $created_at_gt Время создания, от (не включая) или null, чтобы удалить значение
     *
     * @return self
     */
    public function setCreatedAtGt(mixed $created_at_gt): self
    {
        $this->_created_at_gt = $this->validatePropertyValue('_created_at_gt', $created_at_gt);
        return $this;
    }

    /**
     * Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена.
     *
     * @return null|DateTime Время создания, до (включительно)
     */
    public function getCreatedAtLte(): ?DateTime
    {
        return $this->_created_at_lte;
    }

    /**
     * Проверяет, была ли установлена дата создания до которой выбираются выплаты.
     *
     * @return bool True если дата была установлена, false если нет
     */
    public function hasCreatedAtLte(): bool
    {
        return null !== $this->_created_at_lte;
    }

    /**
     * Устанавливает дату создания до которой выбираются выплаты.
     *
     * @param null|DateTime|int|string $created_at_lte Время создания, до (включительно) или null, чтобы удалить значение
     *
     * @return self
     */
    public function setCreatedAtLte(mixed $created_at_lte): self
    {
        $this->_created_at_lte = $this->validatePropertyValue('_created_at_lte', $created_at_lte);
        return $this;
    }

    /**
     * Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена.
     *
     * @return null|DateTime Время создания, до (не включая)
     */
    public function getCreatedAtLt(): ?DateTime
    {
        return $this->_created_at_lt;
    }

    /**
     * Проверяет, была ли установлена дата создания до которой выбираются выплаты.
     *
     * @return bool True если дата была установлена, false если нет
     */
    public function hasCreatedAtLt(): bool
    {
        return null !== $this->_created_at_lt;
    }

    /**
     * Устанавливает дату создания до которой выбираются выплаты.
     *
     * @param null|DateTime|int|string $created_at_lt Время создания, до (не включая) или null, чтобы удалить значение
     *
     * @return self
     */
    public function setCreatedAtLt(mixed $created_at_lt): self
    {
        $this->_created_at_lt = $this->validatePropertyValue('_created_at_lt', $created_at_lt);
        return $this;
    }

    /**
     * Возвращает статус выбираемых выплат или null, если он до этого не был установлен.
     *
     * @return null|string Статус выбираемых выплат
     */
    public function getStatus(): ?string
    {
        return $this->_status;
    }

    /**
     * Проверяет, был ли установлен статус выбираемых выплат.
     *
     * @return bool True если статус был установлен, false если нет
     */
    public function hasStatus(): bool
    {
        return null !== $this->_status;
    }

    /**
     * Устанавливает статус выбираемых выплат.
     *
     * @param string|null $status Статус выбираемых выплат или null, чтобы удалить значение
     *
     * @return self
     */
    public function setStatus(?string $status): self
    {
        $this->_status = $this->validatePropertyValue('_status', $status);
        return $this;
    }

    /**
     * Возвращает способ получения выбираемых выплат или null, если он до этого не был установлен.
     *
     * @return null|string Способ получения выбираемых выплат
     */
    public function getPayoutDestinationType(): ?string
    {
        return $this->_payout_destination_type;
    }

    /**
     * Проверяет, был ли установлен способ получения выбираемых выплат.
     *
     * @return bool True если способ получения был установлен, false если нет
     */
    public function hasPayoutDestinationType(): bool
    {
        return null !== $this->_payout_destination_type;
    }

    /**
     * Устанавливает способ получения выбираемых выплат.
     *
     * @param string|null $payout_destination_type Способ получения выбираемых выплат или null, чтобы удалить значение
     *
     * @return self
     */
    public function setPayoutDestinationType(?string $payout_destination_type): self
    {
        $this->_payout_destination_type = $this->validatePropertyValue('_payout_destination_type', $payout_destination_type);
        return $this;
    }

    /**
     * Ограничение количества объектов выплаты.
     *
     * @return null|int Ограничение количества объектов выплаты
     */
    public function getLimit(): ?int
    {
        return $this->_limit;
    }

    /**
     * Проверяет, было ли установлено ограничение количества объектов выплаты.
     *
     * @return bool True если было установлено, false если нет
     */
    public function hasLimit(): bool
    {
        return null !== $this->_limit;
    }

    /**
     * Устанавливает ограничение количества объектов выплаты.
     *
     * @param null|int|string $limit Ограничение количества объектов выплаты или null, чтобы удалить значение
     *
     * @return self
     */
    public function setLimit(mixed $limit): self
    {
        $this->_limit = $this->validatePropertyValue('_limit', $limit);
        return $this;
    }

    /**
     * Страница выдачи результатов, которую необходимо отобразить.
     */
    public function getCursor(): ?string
    {
        return $this->_cursor;
    }

    /**
     * Проверяет, была ли установлена страница выдачи результатов, которую необходимо отобразить.
     *
     * @return bool True если была установлена, false если нет
     */
    public function hasCursor(): bool
    {
        return null !== $this->_cursor;
    }

    /**
     * Устанавливает страницу выдачи результатов, которую необходимо отобразить
     *
     * @param string|null $cursor Страница выдачи результатов или null, чтобы удалить значение
     *
     * @return self
     */
    public function setCursor(?string $cursor): self
    {
        $this->_cursor = $this->validatePropertyValue('_cursor', $cursor);
        return $this;
    }

    /**
     * Проверяет валидность текущего объекта запроса.
     *
     * @return bool True если объект валиден, false если нет
     */
    public function validate(): bool
    {
        return true;
    }

    /**
     * Возвращает инстанс билдера объектов запросов списка выплат магазина.
     *
     * @return PayoutsRequestBuilder Билдер объектов запросов списка выплат
     */
    public static function builder(): PayoutsRequestBuilder
    {
        return new PayoutsRequestBuilder();
    }
}
