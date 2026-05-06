# [YooKassa API SDK](../home.md)

# Interface: PayoutsRequestInterface
### Namespace: [\YooKassa\Request\Payouts](../namespaces/yookassa-request-payouts.md)
---
**Summary:**

Interface PaymentsRequestInterface.

---
### Constants
* No constants found

---
### Methods
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [getCreatedAtGt()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_getCreatedAtGt) |  | Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена. |
| public | [getCreatedAtGte()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_getCreatedAtGte) |  | Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена. |
| public | [getCreatedAtLt()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_getCreatedAtLt) |  | Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена. |
| public | [getCreatedAtLte()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_getCreatedAtLte) |  | Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена. |
| public | [getCursor()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_getCursor) |  | Возвращает страницу выдачи результатов или null, если она до этого не была установлена. |
| public | [getLimit()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_getLimit) |  | Возвращает ограничение количества объектов выплаты или null, если оно до этого не было установлено. |
| public | [getPayoutDestinationType()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_getPayoutDestinationType) |  | Возвращает код способа получения выбираемых выплат или null, если он до этого не был установлен. |
| public | [getStatus()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_getStatus) |  | Возвращает статус выбираемых выплат или null, если он до этого не был установлен. |
| public | [hasCreatedAtGt()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_hasCreatedAtGt) |  | Проверяет, была ли установлена дата создания от которой выбираются выплаты. |
| public | [hasCreatedAtGte()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_hasCreatedAtGte) |  | Проверяет, была ли установлена дата создания от которой выбираются выплаты. |
| public | [hasCreatedAtLt()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_hasCreatedAtLt) |  | Проверяет, была ли установлена дата создания до которой выбираются выплаты. |
| public | [hasCreatedAtLte()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_hasCreatedAtLte) |  | Проверяет, была ли установлена дата создания до которой выбираются выплаты. |
| public | [hasCursor()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_hasCursor) |  | Проверяет, была ли установлена страница выдачи результатов. |
| public | [hasLimit()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_hasLimit) |  | Проверяет, было ли установлено ограничение количества объектов выплаты. |
| public | [hasPayoutDestinationType()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_hasPayoutDestinationType) |  | Проверяет, был ли установлен код способа получения выбираемых выплат. |
| public | [hasStatus()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_hasStatus) |  | Проверяет, был ли установлен статус выбираемых выплат. |
| public | [setCreatedAtGt()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_setCreatedAtGt) |  | Устанавливает дату создания от которой выбираются выплаты. |
| public | [setCreatedAtGte()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_setCreatedAtGte) |  | Устанавливает дату создания от которой выбираются выплаты. |
| public | [setCreatedAtLt()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_setCreatedAtLt) |  | Устанавливает дату создания до которой выбираются выплаты. |
| public | [setCreatedAtLte()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_setCreatedAtLte) |  | Устанавливает дату создания до которой выбираются выплаты. |
| public | [setCursor()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_setCursor) |  | Устанавливает страницу выдачи результатов. |
| public | [setLimit()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_setLimit) |  | Устанавливает ограничение количества объектов выплаты. |
| public | [setPayoutDestinationType()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_setPayoutDestinationType) |  | Устанавливает код способа получения выбираемых выплат. |
| public | [setStatus()](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md#method_setStatus) |  | Устанавливает статус выбираемых выплат. |

---
### Details
* File: [lib/Request/Payouts/PayoutsRequestInterface.php](../../lib/Request/Payouts/PayoutsRequestInterface.php)
* Package: \YooKassa\Request
* See Also:
  * [](https://yookassa.ru/developers/api)

---
### Tags
| Tag | Version | Description |
| --- | ------- | ----------- |
| category |  | Class |
| author |  | cms@yoomoney.ru |
| property |  | Время создания, от (включительно) |
| property |  | Фильтр по времени создания: время должно быть больше указанного значения или равно ему («с такого-то момента включительно»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.gte&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional) |
| property |  | Время создания, от (не включая) |
| property |  | Фильтр по времени создания: время должно быть больше указанного значения («с такого-то момента, не включая его»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.gt&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional) |
| property |  | Время создания, до (включительно) |
| property |  | Фильтр по времени создания: время должно быть меньше указанного значения или равно ему («по такой-то момент включительно»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.lte&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional) |
| property |  | Время создания, до (не включая) |
| property |  | Фильтр по коду %[способа получения выплаты](/developers/payouts/getting-started/payout-types-and-limits#types-destination). Пример: ~&#x60;payout_destination.type&#x3D;bank_card&#x60; (optional) |
| property |  | Фильтр по коду %[способа получения выплаты](/developers/payouts/getting-started/payout-types-and-limits#types-destination). Пример: ~&#x60;payout_destination.type&#x3D;bank_card&#x60; (optional) |
| property |  | Статус выплаты |
| property |  | Ограничение количества объектов выплаты, отображаемых на одной странице выдачи |
| property |  | Страница выдачи результатов, которую необходимо отобразить |

---
## Methods
<a name="method_getCursor" class="anchor"></a>
#### public getCursor() : null|string

```php
public getCursor() : null|string
```

**Summary**

Возвращает страницу выдачи результатов или null, если она до этого не была установлена.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** null|string - Страница выдачи результатов


<a name="method_hasCursor" class="anchor"></a>
#### public hasCursor() : bool

```php
public hasCursor() : bool
```

**Summary**

Проверяет, была ли установлена страница выдачи результатов.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** bool - True если страница выдачи результатов была установлена, false если нет


<a name="method_setCursor" class="anchor"></a>
#### public setCursor() : self

```php
public setCursor(string $cursor) : self
```

**Summary**

Устанавливает страницу выдачи результатов.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | cursor  | Страница |

**Returns:** self - 


<a name="method_getLimit" class="anchor"></a>
#### public getLimit() : null|int

```php
public getLimit() : null|int
```

**Summary**

Возвращает ограничение количества объектов выплаты или null, если оно до этого не было установлено.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** null|int - Ограничение количества объектов выплаты


<a name="method_hasLimit" class="anchor"></a>
#### public hasLimit() : bool

```php
public hasLimit() : bool
```

**Summary**

Проверяет, было ли установлено ограничение количества объектов выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** bool - True если ограничение количества объектов выплаты было установлено, false если нет


<a name="method_setLimit" class="anchor"></a>
#### public setLimit() : self

```php
public setLimit(int $limit) : self
```

**Summary**

Устанавливает ограничение количества объектов выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">int</code> | limit  | Количества объектов выплаты на странице |

**Returns:** self - 


<a name="method_getCreatedAtGte" class="anchor"></a>
#### public getCreatedAtGte() : null|\DateTime

```php
public getCreatedAtGte() : null|\DateTime
```

**Summary**

Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** null|\DateTime - Время создания, от (включительно)


<a name="method_hasCreatedAtGte" class="anchor"></a>
#### public hasCreatedAtGte() : bool

```php
public hasCreatedAtGte() : bool
```

**Summary**

Проверяет, была ли установлена дата создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** bool - True если дата была установлена, false если нет


<a name="method_setCreatedAtGte" class="anchor"></a>
#### public setCreatedAtGte() : self

```php
public setCreatedAtGte(\DateTime|string|null $_created_at_gte) : self
```

**Summary**

Устанавливает дату создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\DateTime OR string OR null</code> | _created_at_gte  | Дата |

**Returns:** self - 


<a name="method_getCreatedAtGt" class="anchor"></a>
#### public getCreatedAtGt() : null|\DateTime

```php
public getCreatedAtGt() : null|\DateTime
```

**Summary**

Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** null|\DateTime - Время создания, от (не включая)


<a name="method_hasCreatedAtGt" class="anchor"></a>
#### public hasCreatedAtGt() : bool

```php
public hasCreatedAtGt() : bool
```

**Summary**

Проверяет, была ли установлена дата создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** bool - True если дата была установлена, false если нет


<a name="method_setCreatedAtGt" class="anchor"></a>
#### public setCreatedAtGt() : self

```php
public setCreatedAtGt(\DateTime|string|null $created_at_gt) : self
```

**Summary**

Устанавливает дату создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\DateTime OR string OR null</code> | created_at_gt  | Дата создания |

**Returns:** self - 


<a name="method_getCreatedAtLte" class="anchor"></a>
#### public getCreatedAtLte() : null|\DateTime

```php
public getCreatedAtLte() : null|\DateTime
```

**Summary**

Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** null|\DateTime - Время создания, до (включительно)


<a name="method_hasCreatedAtLte" class="anchor"></a>
#### public hasCreatedAtLte() : bool

```php
public hasCreatedAtLte() : bool
```

**Summary**

Проверяет, была ли установлена дата создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** bool - True если дата была установлена, false если нет


<a name="method_setCreatedAtLte" class="anchor"></a>
#### public setCreatedAtLte() : self

```php
public setCreatedAtLte(\DateTime|string|null $created_at_lte) : self
```

**Summary**

Устанавливает дату создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\DateTime OR string OR null</code> | created_at_lte  | Дата |

**Returns:** self - 


<a name="method_getCreatedAtLt" class="anchor"></a>
#### public getCreatedAtLt() : null|\DateTime

```php
public getCreatedAtLt() : null|\DateTime
```

**Summary**

Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** null|\DateTime - Время создания, до (не включая)


<a name="method_hasCreatedAtLt" class="anchor"></a>
#### public hasCreatedAtLt() : bool

```php
public hasCreatedAtLt() : bool
```

**Summary**

Проверяет, была ли установлена дата создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** bool - True если дата была установлена, false если нет


<a name="method_setCreatedAtLt" class="anchor"></a>
#### public setCreatedAtLt() : self

```php
public setCreatedAtLt(\DateTime|string|null $created_at_lt) : self
```

**Summary**

Устанавливает дату создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\DateTime OR string OR null</code> | created_at_lt  | Дата |

**Returns:** self - 


<a name="method_getStatus" class="anchor"></a>
#### public getStatus() : null|string

```php
public getStatus() : null|string
```

**Summary**

Возвращает статус выбираемых выплат или null, если он до этого не был установлен.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** null|string - Статус выбираемых выплат


<a name="method_hasStatus" class="anchor"></a>
#### public hasStatus() : bool

```php
public hasStatus() : bool
```

**Summary**

Проверяет, был ли установлен статус выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** bool - True если статус был установлен, false если нет


<a name="method_setStatus" class="anchor"></a>
#### public setStatus() : self

```php
public setStatus(string $status) : self
```

**Summary**

Устанавливает статус выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | status  | Статус выплат |

**Returns:** self - 


<a name="method_getPayoutDestinationType" class="anchor"></a>
#### public getPayoutDestinationType() : null|string

```php
public getPayoutDestinationType() : null|string
```

**Summary**

Возвращает код способа получения выбираемых выплат или null, если он до этого не был установлен.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** null|string - Код способа получения выбираемых выплат


<a name="method_hasPayoutDestinationType" class="anchor"></a>
#### public hasPayoutDestinationType() : bool

```php
public hasPayoutDestinationType() : bool
```

**Summary**

Проверяет, был ли установлен код способа получения выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

**Returns:** bool - True если код способа получения выплаты был установлен, false если нет


<a name="method_setPayoutDestinationType" class="anchor"></a>
#### public setPayoutDestinationType() : self

```php
public setPayoutDestinationType(string $payout_destination_type) : self
```

**Summary**

Устанавливает код способа получения выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | payout_destination_type  | Код способа получения выплаты |

**Returns:** self - 




---

### Top Namespaces

* [\YooKassa](../namespaces/yookassa.md)

---

### Reports
* [Errors - 0](../reports/errors.md)
* [Markers - 0](../reports/markers.md)
* [Deprecated - 43](../reports/deprecated.md)

---

This document was automatically generated from source code comments on 2026-03-13 using [phpDocumentor](http://www.phpdoc.org/)

&copy; 2026 YooMoney