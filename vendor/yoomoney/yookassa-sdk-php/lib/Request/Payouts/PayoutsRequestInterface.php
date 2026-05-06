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

/**
 * Interface PaymentsRequestInterface.
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
interface PayoutsRequestInterface
{
    /**
     * Возвращает страницу выдачи результатов или null, если она до этого не была установлена.
     *
     * @return null|string Страница выдачи результатов
     */
    public function getCursor(): ?string;

    /**
     * Проверяет, была ли установлена страница выдачи результатов.
     *
     * @return bool True если страница выдачи результатов была установлена, false если нет
     */
    public function hasCursor(): bool;

    /**
     * Устанавливает страницу выдачи результатов.
     *
     * @param string $cursor Страница
     *
     * @return self
     */
    public function setCursor(string $cursor): self;

    /**
     * Возвращает ограничение количества объектов выплаты или null, если оно до этого не было установлено.
     *
     * @return null|int Ограничение количества объектов выплаты
     */
    public function getLimit(): ?int;

    /**
     * Проверяет, было ли установлено ограничение количества объектов выплаты.
     *
     * @return bool True если ограничение количества объектов выплаты было установлено, false если нет
     */
    public function hasLimit(): bool;

    /**
     * Устанавливает ограничение количества объектов выплаты.
     *
     * @param int $limit Количества объектов выплаты на странице
     *
     * @return self
     */
    public function setLimit(int $limit): self;

    /**
     * Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена.
     *
     * @return null|DateTime Время создания, от (включительно)
     */
    public function getCreatedAtGte(): ?DateTime;

    /**
     * Проверяет, была ли установлена дата создания от которой выбираются выплаты.
     *
     * @return bool True если дата была установлена, false если нет
     */
    public function hasCreatedAtGte(): bool;

    /**
     * Устанавливает дату создания от которой выбираются выплаты.
     *
     * @param DateTime|string|null $_created_at_gte Дата
     *
     * @return self
     */
    public function setCreatedAtGte(mixed $_created_at_gte): self;

    /**
     * Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена.
     *
     * @return null|DateTime Время создания, от (не включая)
     */
    public function getCreatedAtGt(): ?DateTime;

    /**
     * Проверяет, была ли установлена дата создания от которой выбираются выплаты.
     *
     * @return bool True если дата была установлена, false если нет
     */
    public function hasCreatedAtGt(): bool;

    /**
     * Устанавливает дату создания от которой выбираются выплаты.
     *
     * @param DateTime|string|null $created_at_gt Дата создания
     *
     * @return self
     */
    public function setCreatedAtGt(mixed $created_at_gt): self;

    /**
     * Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена.
     *
     * @return null|DateTime Время создания, до (включительно)
     */
    public function getCreatedAtLte(): ?DateTime;

    /**
     * Проверяет, была ли установлена дата создания до которой выбираются выплаты.
     *
     * @return bool True если дата была установлена, false если нет
     */
    public function hasCreatedAtLte(): bool;

    /**
     * Устанавливает дату создания до которой выбираются выплаты.
     *
     * @param DateTime|string|null $created_at_lte Дата
     *
     * @return self
     */
    public function setCreatedAtLte(mixed $created_at_lte): self;

    /**
     * Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена.
     *
     * @return null|DateTime Время создания, до (не включая)
     */
    public function getCreatedAtLt(): ?DateTime;

    /**
     * Проверяет, была ли установлена дата создания до которой выбираются выплаты.
     *
     * @return bool True если дата была установлена, false если нет
     */
    public function hasCreatedAtLt(): bool;

    /**
     * Устанавливает дату создания до которой выбираются выплаты.
     *
     * @param DateTime|string|null $created_at_lt Дата
     *
     * @return self
     */
    public function setCreatedAtLt(mixed $created_at_lt): self;

    /**
     * Возвращает статус выбираемых выплат или null, если он до этого не был установлен.
     *
     * @return null|string Статус выбираемых выплат
     */
    public function getStatus(): ?string;

    /**
     * Проверяет, был ли установлен статус выбираемых выплат.
     *
     * @return bool True если статус был установлен, false если нет
     */
    public function hasStatus(): bool;

    /**
     * Устанавливает статус выбираемых выплат.
     *
     * @param string $status Статус выплат
     *
     * @return self
     */
    public function setStatus(string $status): self;

    /**
     * Возвращает код способа получения выбираемых выплат или null, если он до этого не был установлен.
     *
     * @return null|string Код способа получения выбираемых выплат
     */
    public function getPayoutDestinationType(): ?string;

    /**
     * Проверяет, был ли установлен код способа получения выбираемых выплат.
     *
     * @return bool True если код способа получения выплаты был установлен, false если нет
     */
    public function hasPayoutDestinationType(): bool;

    /**
     * Устанавливает код способа получения выбираемых выплат.
     *
     * @param string $payout_destination_type Код способа получения выплаты
     *
     * @return self
     */
    public function setPayoutDestinationType(string $payout_destination_type): self;
}
