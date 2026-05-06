# [YooKassa API SDK](../home.md)

# Class: \YooKassa\Request\Payouts\PayoutsRequest
### Namespace: [\YooKassa\Request\Payouts](../namespaces/yookassa-request-payouts.md)
---
**Summary:**

Класс, представляющий модель PayoutsRequest.

**Description:**

Класс объекта запроса к API для получения списка выплат магазина.

---
### Constants
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [MAX_LIMIT_VALUE](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#constant_MAX_LIMIT_VALUE) |  | Максимальное количество объектов выплаты в выборке |

---
### Properties
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [$created_at_gt](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_created_at_gt) |  | Фильтр по времени создания: время должно быть больше указанного значения («с такого-то момента, не включая его»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.gt&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional) |
| public | [$created_at_gte](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_created_at_gte) |  | Фильтр по времени создания: время должно быть больше указанного значения или равно ему («с такого-то момента включительно»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.gte&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional) |
| public | [$created_at_lte](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_created_at_lte) |  | Фильтр по времени создания: время должно быть меньше указанного значения или равно ему («по такой-то момент включительно»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.lte&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional) |
| public | [$createdAtGt](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_createdAtGt) |  | Время создания, от (не включая) |
| public | [$createdAtGte](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_createdAtGte) |  | Время создания, от (включительно) |
| public | [$createdAtLt](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_createdAtLt) |  | Время создания, до (не включая) |
| public | [$createdAtLte](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_createdAtLte) |  | Время создания, до (включительно) |
| public | [$cursor](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_cursor) |  | Страница выдачи результатов, которую необходимо отобразить |
| public | [$limit](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_limit) |  | Ограничение количества объектов выплаты, отображаемых на одной странице выдачи |
| public | [$payout_destination_type](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_payout_destination_type) |  | Фильтр по коду %[способа получения выплаты](/developers/payouts/getting-started/payout-types-and-limits#types-destination). Пример: ~&#x60;payout_destination.type&#x3D;bank_card&#x60; (optional) |
| public | [$payoutDestinationType](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_payoutDestinationType) |  | Фильтр по коду %[способа получения выплаты](/developers/payouts/getting-started/payout-types-and-limits#types-destination). Пример: ~&#x60;payout_destination.type&#x3D;bank_card&#x60; (optional) |
| public | [$status](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#property_status) |  | Статус выплаты |

---
### Methods
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [__construct()](../classes/YooKassa-Common-AbstractObject.md#method___construct) |  | AbstractObject constructor. |
| public | [__get()](../classes/YooKassa-Common-AbstractObject.md#method___get) |  | Возвращает значение свойства. |
| public | [__isset()](../classes/YooKassa-Common-AbstractObject.md#method___isset) |  | Проверяет наличие свойства. |
| public | [__set()](../classes/YooKassa-Common-AbstractObject.md#method___set) |  | Устанавливает значение свойства. |
| public | [__unset()](../classes/YooKassa-Common-AbstractObject.md#method___unset) |  | Удаляет свойство. |
| public | [builder()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_builder) |  | Возвращает инстанс билдера объектов запросов списка выплат магазина. |
| public | [clearValidationError()](../classes/YooKassa-Common-AbstractRequest.md#method_clearValidationError) |  | Очищает статус валидации текущего запроса. |
| public | [fromArray()](../classes/YooKassa-Common-AbstractObject.md#method_fromArray) |  | Устанавливает значения свойств текущего объекта из массива. |
| public | [getCreatedAtGt()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_getCreatedAtGt) |  | Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена. |
| public | [getCreatedAtGte()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_getCreatedAtGte) |  | Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена. |
| public | [getCreatedAtLt()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_getCreatedAtLt) |  | Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена. |
| public | [getCreatedAtLte()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_getCreatedAtLte) |  | Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена. |
| public | [getCursor()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_getCursor) |  | Страница выдачи результатов, которую необходимо отобразить. |
| public | [getLastValidationError()](../classes/YooKassa-Common-AbstractRequest.md#method_getLastValidationError) |  | Возвращает последнюю ошибку валидации. |
| public | [getLimit()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_getLimit) |  | Ограничение количества объектов выплаты. |
| public | [getPayoutDestinationType()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_getPayoutDestinationType) |  | Возвращает способ получения выбираемых выплат или null, если он до этого не был установлен. |
| public | [getStatus()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_getStatus) |  | Возвращает статус выбираемых выплат или null, если он до этого не был установлен. |
| public | [getValidator()](../classes/YooKassa-Common-AbstractObject.md#method_getValidator) |  |  |
| public | [hasCreatedAtGt()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_hasCreatedAtGt) |  | Проверяет, была ли установлена дата создания от которой выбираются выплаты. |
| public | [hasCreatedAtGte()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_hasCreatedAtGte) |  | Проверяет, была ли установлена дата создания от которой выбираются выплаты. |
| public | [hasCreatedAtLt()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_hasCreatedAtLt) |  | Проверяет, была ли установлена дата создания до которой выбираются выплаты. |
| public | [hasCreatedAtLte()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_hasCreatedAtLte) |  | Проверяет, была ли установлена дата создания до которой выбираются выплаты. |
| public | [hasCursor()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_hasCursor) |  | Проверяет, была ли установлена страница выдачи результатов, которую необходимо отобразить. |
| public | [hasLimit()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_hasLimit) |  | Проверяет, было ли установлено ограничение количества объектов выплаты. |
| public | [hasPayoutDestinationType()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_hasPayoutDestinationType) |  | Проверяет, был ли установлен способ получения выбираемых выплат. |
| public | [hasStatus()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_hasStatus) |  | Проверяет, был ли установлен статус выбираемых выплат. |
| public | [jsonSerialize()](../classes/YooKassa-Common-AbstractObject.md#method_jsonSerialize) |  | Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации. |
| public | [offsetExists()](../classes/YooKassa-Common-AbstractObject.md#method_offsetExists) |  | Проверяет наличие свойства. |
| public | [offsetGet()](../classes/YooKassa-Common-AbstractObject.md#method_offsetGet) |  | Возвращает значение свойства. |
| public | [offsetSet()](../classes/YooKassa-Common-AbstractObject.md#method_offsetSet) |  | Устанавливает значение свойства. |
| public | [offsetUnset()](../classes/YooKassa-Common-AbstractObject.md#method_offsetUnset) |  | Удаляет свойство. |
| public | [setCreatedAtGt()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_setCreatedAtGt) |  | Устанавливает дату создания от которой выбираются выплаты. |
| public | [setCreatedAtGte()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_setCreatedAtGte) |  | Устанавливает дату создания от которой выбираются выплаты. |
| public | [setCreatedAtLt()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_setCreatedAtLt) |  | Устанавливает дату создания до которой выбираются выплаты. |
| public | [setCreatedAtLte()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_setCreatedAtLte) |  | Устанавливает дату создания до которой выбираются выплаты. |
| public | [setCursor()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_setCursor) |  | Устанавливает страницу выдачи результатов, которую необходимо отобразить |
| public | [setLimit()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_setLimit) |  | Устанавливает ограничение количества объектов выплаты. |
| public | [setPayoutDestinationType()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_setPayoutDestinationType) |  | Устанавливает способ получения выбираемых выплат. |
| public | [setStatus()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_setStatus) |  | Устанавливает статус выбираемых выплат. |
| public | [toArray()](../classes/YooKassa-Common-AbstractObject.md#method_toArray) |  | Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации Является алиасом метода AbstractObject::jsonSerialize(). |
| public | [validate()](../classes/YooKassa-Request-Payouts-PayoutsRequest.md#method_validate) |  | Проверяет валидность текущего объекта запроса. |
| protected | [getUnknownProperties()](../classes/YooKassa-Common-AbstractObject.md#method_getUnknownProperties) |  | Возвращает массив свойств которые не существуют, но были заданы у объекта. |
| protected | [setValidationError()](../classes/YooKassa-Common-AbstractRequest.md#method_setValidationError) |  | Устанавливает ошибку валидации. |
| protected | [validatePropertyValue()](../classes/YooKassa-Common-AbstractObject.md#method_validatePropertyValue) |  |  |

---
### Details
* File: [lib/Request/Payouts/PayoutsRequest.php](../../lib/Request/Payouts/PayoutsRequest.php)
* Package: YooKassa\Request
* Class Hierarchy:  
  * [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)
  * [\YooKassa\Common\AbstractRequest](../classes/YooKassa-Common-AbstractRequest.md)
  * \YooKassa\Request\Payouts\PayoutsRequest
* Implements:
  * [\YooKassa\Request\Payouts\PayoutsRequestInterface](../classes/YooKassa-Request-Payouts-PayoutsRequestInterface.md)

* See Also:
  * [](https://yookassa.ru/developers/api)

---
### Tags
| Tag | Version | Description |
| --- | ------- | ----------- |
| category |  | Class |
| author |  | cms@yoomoney.ru |

---
## Constants
<a name="constant_MAX_LIMIT_VALUE" class="anchor"></a>
###### MAX_LIMIT_VALUE
Максимальное количество объектов выплаты в выборке

```php
MAX_LIMIT_VALUE = 100
```



---
## Properties
<a name="property_created_at_gt"></a>
#### public $created_at_gt : null|\DateTime
---
***Description***

Фильтр по времени создания: время должно быть больше указанного значения («с такого-то момента, не включая его»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.gt&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional)

**Type:** <a href="../null|\DateTime"><abbr title="null|\DateTime">DateTime</abbr></a>

**Details:**


<a name="property_created_at_gte"></a>
#### public $created_at_gte : null|\DateTime
---
***Description***

Фильтр по времени создания: время должно быть больше указанного значения или равно ему («с такого-то момента включительно»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.gte&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional)

**Type:** <a href="../null|\DateTime"><abbr title="null|\DateTime">DateTime</abbr></a>

**Details:**


<a name="property_created_at_lte"></a>
#### public $created_at_lte : null|\DateTime
---
***Description***

Фильтр по времени создания: время должно быть меньше указанного значения или равно ему («по такой-то момент включительно»). Указывается в формате [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Пример: ~&#x60;created_at.lte&#x3D;2018-07-18T10:51:18.139Z&#x60; (optional)

**Type:** <a href="../null|\DateTime"><abbr title="null|\DateTime">DateTime</abbr></a>

**Details:**


<a name="property_createdAtGt"></a>
#### public $createdAtGt : null|\DateTime
---
***Description***

Время создания, от (не включая)

**Type:** <a href="../null|\DateTime"><abbr title="null|\DateTime">DateTime</abbr></a>

**Details:**


<a name="property_createdAtGte"></a>
#### public $createdAtGte : null|\DateTime
---
***Description***

Время создания, от (включительно)

**Type:** <a href="../null|\DateTime"><abbr title="null|\DateTime">DateTime</abbr></a>

**Details:**


<a name="property_createdAtLt"></a>
#### public $createdAtLt : null|\DateTime
---
***Description***

Время создания, до (не включая)

**Type:** <a href="../null|\DateTime"><abbr title="null|\DateTime">DateTime</abbr></a>

**Details:**


<a name="property_createdAtLte"></a>
#### public $createdAtLte : null|\DateTime
---
***Description***

Время создания, до (включительно)

**Type:** <a href="../null|\DateTime"><abbr title="null|\DateTime">DateTime</abbr></a>

**Details:**


<a name="property_cursor"></a>
#### public $cursor : null|string
---
***Description***

Страница выдачи результатов, которую необходимо отобразить

**Type:** <a href="../null|string"><abbr title="null|string">null|string</abbr></a>

**Details:**


<a name="property_limit"></a>
#### public $limit : null|int
---
***Description***

Ограничение количества объектов выплаты, отображаемых на одной странице выдачи

**Type:** <a href="../null|int"><abbr title="null|int">null|int</abbr></a>

**Details:**


<a name="property_payout_destination_type"></a>
#### public $payout_destination_type : null|string
---
***Description***

Фильтр по коду %[способа получения выплаты](/developers/payouts/getting-started/payout-types-and-limits#types-destination). Пример: ~&#x60;payout_destination.type&#x3D;bank_card&#x60; (optional)

**Type:** <a href="../null|string"><abbr title="null|string">null|string</abbr></a>

**Details:**


<a name="property_payoutDestinationType"></a>
#### public $payoutDestinationType : null|string
---
***Description***

Фильтр по коду %[способа получения выплаты](/developers/payouts/getting-started/payout-types-and-limits#types-destination). Пример: ~&#x60;payout_destination.type&#x3D;bank_card&#x60; (optional)

**Type:** <a href="../null|string"><abbr title="null|string">null|string</abbr></a>

**Details:**


<a name="property_status"></a>
#### public $status : null|string
---
***Description***

Статус выплаты

**Type:** <a href="../null|string"><abbr title="null|string">null|string</abbr></a>

**Details:**



---
## Methods
<a name="method___construct" class="anchor"></a>
#### public __construct() : mixed

```php
public __construct(array|null $data = []) : mixed
```

**Summary**

AbstractObject constructor.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">array OR null</code> | data  |  |

**Returns:** mixed - 


<a name="method___get" class="anchor"></a>
#### public __get() : mixed

```php
public __get(string $propertyName) : mixed
```

**Summary**

Возвращает значение свойства.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | propertyName  | Имя свойства |

**Returns:** mixed - Значение свойства


<a name="method___isset" class="anchor"></a>
#### public __isset() : bool

```php
public __isset(string $propertyName) : bool
```

**Summary**

Проверяет наличие свойства.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | propertyName  | Имя проверяемого свойства |

**Returns:** bool - True если свойство имеется, false если нет


<a name="method___set" class="anchor"></a>
#### public __set() : void

```php
public __set(string $propertyName, mixed $value) : void
```

**Summary**

Устанавливает значение свойства.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | propertyName  | Имя свойства |
| <code lang="php">mixed</code> | value  | Значение свойства |

**Returns:** void - 


<a name="method___unset" class="anchor"></a>
#### public __unset() : void

```php
public __unset(string $propertyName) : void
```

**Summary**

Удаляет свойство.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | propertyName  | Имя удаляемого свойства |

**Returns:** void - 


<a name="method_builder" class="anchor"></a>
#### public builder() : \YooKassa\Request\Payouts\PayoutsRequestBuilder

```php
Static public builder() : \YooKassa\Request\Payouts\PayoutsRequestBuilder
```

**Summary**

Возвращает инстанс билдера объектов запросов списка выплат магазина.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** \YooKassa\Request\Payouts\PayoutsRequestBuilder - Билдер объектов запросов списка выплат


<a name="method_clearValidationError" class="anchor"></a>
#### public clearValidationError() : void

```php
public clearValidationError() : void
```

**Summary**

Очищает статус валидации текущего запроса.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractRequest](../classes/YooKassa-Common-AbstractRequest.md)

**Returns:** void - 


<a name="method_fromArray" class="anchor"></a>
#### public fromArray() : void

```php
public fromArray(array|\Traversable $sourceArray) : void
```

**Summary**

Устанавливает значения свойств текущего объекта из массива.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">array OR \Traversable</code> | sourceArray  | Ассоциативный массив с настройками |

**Returns:** void - 


<a name="method_getCreatedAtGt" class="anchor"></a>
#### public getCreatedAtGt() : null|\DateTime

```php
public getCreatedAtGt() : null|\DateTime
```

**Summary**

Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** null|\DateTime - Время создания, от (не включая)


<a name="method_getCreatedAtGte" class="anchor"></a>
#### public getCreatedAtGte() : null|\DateTime

```php
public getCreatedAtGte() : null|\DateTime
```

**Summary**

Возвращает дату создания от которой будут возвращены выплаты или null, если дата не была установлена.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** null|\DateTime - Время создания, от (включительно)


<a name="method_getCreatedAtLt" class="anchor"></a>
#### public getCreatedAtLt() : null|\DateTime

```php
public getCreatedAtLt() : null|\DateTime
```

**Summary**

Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** null|\DateTime - Время создания, до (не включая)


<a name="method_getCreatedAtLte" class="anchor"></a>
#### public getCreatedAtLte() : null|\DateTime

```php
public getCreatedAtLte() : null|\DateTime
```

**Summary**

Возвращает дату создания до которой будут возвращены выплаты или null, если дата не была установлена.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** null|\DateTime - Время создания, до (включительно)


<a name="method_getCursor" class="anchor"></a>
#### public getCursor() : null|string

```php
public getCursor() : null|string
```

**Summary**

Страница выдачи результатов, которую необходимо отобразить.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** null|string - Страница выдачи результатов


<a name="method_getLastValidationError" class="anchor"></a>
#### public getLastValidationError() : string|null

```php
public getLastValidationError() : string|null
```

**Summary**

Возвращает последнюю ошибку валидации.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractRequest](../classes/YooKassa-Common-AbstractRequest.md)

**Returns:** string|null - Последняя произошедшая ошибка валидации


<a name="method_getLimit" class="anchor"></a>
#### public getLimit() : null|int

```php
public getLimit() : null|int
```

**Summary**

Ограничение количества объектов выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** null|int - Ограничение количества объектов выплаты


<a name="method_getPayoutDestinationType" class="anchor"></a>
#### public getPayoutDestinationType() : null|string

```php
public getPayoutDestinationType() : null|string
```

**Summary**

Возвращает способ получения выбираемых выплат или null, если он до этого не был установлен.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** null|string - Способ получения выбираемых выплат


<a name="method_getStatus" class="anchor"></a>
#### public getStatus() : null|string

```php
public getStatus() : null|string
```

**Summary**

Возвращает статус выбираемых выплат или null, если он до этого не был установлен.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** null|string - Статус выбираемых выплат


<a name="method_getValidator" class="anchor"></a>
#### public getValidator() : \YooKassa\Validator\Validator

```php
public getValidator() : \YooKassa\Validator\Validator
```

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

**Returns:** \YooKassa\Validator\Validator - 


<a name="method_hasCreatedAtGt" class="anchor"></a>
#### public hasCreatedAtGt() : bool

```php
public hasCreatedAtGt() : bool
```

**Summary**

Проверяет, была ли установлена дата создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** bool - True если дата была установлена, false если нет


<a name="method_hasCreatedAtGte" class="anchor"></a>
#### public hasCreatedAtGte() : bool

```php
public hasCreatedAtGte() : bool
```

**Summary**

Проверяет, была ли установлена дата создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** bool - True если дата была установлена, false если нет


<a name="method_hasCreatedAtLt" class="anchor"></a>
#### public hasCreatedAtLt() : bool

```php
public hasCreatedAtLt() : bool
```

**Summary**

Проверяет, была ли установлена дата создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** bool - True если дата была установлена, false если нет


<a name="method_hasCreatedAtLte" class="anchor"></a>
#### public hasCreatedAtLte() : bool

```php
public hasCreatedAtLte() : bool
```

**Summary**

Проверяет, была ли установлена дата создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** bool - True если дата была установлена, false если нет


<a name="method_hasCursor" class="anchor"></a>
#### public hasCursor() : bool

```php
public hasCursor() : bool
```

**Summary**

Проверяет, была ли установлена страница выдачи результатов, которую необходимо отобразить.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** bool - True если была установлена, false если нет


<a name="method_hasLimit" class="anchor"></a>
#### public hasLimit() : bool

```php
public hasLimit() : bool
```

**Summary**

Проверяет, было ли установлено ограничение количества объектов выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** bool - True если было установлено, false если нет


<a name="method_hasPayoutDestinationType" class="anchor"></a>
#### public hasPayoutDestinationType() : bool

```php
public hasPayoutDestinationType() : bool
```

**Summary**

Проверяет, был ли установлен способ получения выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** bool - True если способ получения был установлен, false если нет


<a name="method_hasStatus" class="anchor"></a>
#### public hasStatus() : bool

```php
public hasStatus() : bool
```

**Summary**

Проверяет, был ли установлен статус выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** bool - True если статус был установлен, false если нет


<a name="method_jsonSerialize" class="anchor"></a>
#### public jsonSerialize() : array

```php
public jsonSerialize() : array
```

**Summary**

Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

**Returns:** array - Ассоциативный массив со свойствами текущего объекта


<a name="method_offsetExists" class="anchor"></a>
#### public offsetExists() : bool

```php
public offsetExists(string $offset) : bool
```

**Summary**

Проверяет наличие свойства.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | offset  | Имя проверяемого свойства |

**Returns:** bool - True если свойство имеется, false если нет


<a name="method_offsetGet" class="anchor"></a>
#### public offsetGet() : mixed

```php
public offsetGet(string $offset) : mixed
```

**Summary**

Возвращает значение свойства.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | offset  | Имя свойства |

**Returns:** mixed - Значение свойства


<a name="method_offsetSet" class="anchor"></a>
#### public offsetSet() : void

```php
public offsetSet(string $offset, mixed $value) : void
```

**Summary**

Устанавливает значение свойства.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | offset  | Имя свойства |
| <code lang="php">mixed</code> | value  | Значение свойства |

**Returns:** void - 


<a name="method_offsetUnset" class="anchor"></a>
#### public offsetUnset() : void

```php
public offsetUnset(string $offset) : void
```

**Summary**

Удаляет свойство.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | offset  | Имя удаляемого свойства |

**Returns:** void - 


<a name="method_setCreatedAtGt" class="anchor"></a>
#### public setCreatedAtGt() : self

```php
public setCreatedAtGt(null|\DateTime|int|string $created_at_gt) : self
```

**Summary**

Устанавливает дату создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR \DateTime OR int OR string</code> | created_at_gt  | Время создания, от (не включая) или null, чтобы удалить значение |

**Returns:** self - 


<a name="method_setCreatedAtGte" class="anchor"></a>
#### public setCreatedAtGte() : self

```php
public setCreatedAtGte(\DateTime|string|null $_created_at_gte = null) : self
```

**Summary**

Устанавливает дату создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\DateTime OR string OR null</code> | _created_at_gte  | Время создания, от (включительно) или null, чтобы удалить значение |

**Returns:** self - 


<a name="method_setCreatedAtLt" class="anchor"></a>
#### public setCreatedAtLt() : self

```php
public setCreatedAtLt(null|\DateTime|int|string $created_at_lt) : self
```

**Summary**

Устанавливает дату создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR \DateTime OR int OR string</code> | created_at_lt  | Время создания, до (не включая) или null, чтобы удалить значение |

**Returns:** self - 


<a name="method_setCreatedAtLte" class="anchor"></a>
#### public setCreatedAtLte() : self

```php
public setCreatedAtLte(null|\DateTime|int|string $created_at_lte) : self
```

**Summary**

Устанавливает дату создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR \DateTime OR int OR string</code> | created_at_lte  | Время создания, до (включительно) или null, чтобы удалить значение |

**Returns:** self - 


<a name="method_setCursor" class="anchor"></a>
#### public setCursor() : self

```php
public setCursor(string|null $cursor) : self
```

**Summary**

Устанавливает страницу выдачи результатов, которую необходимо отобразить

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR null</code> | cursor  | Страница выдачи результатов или null, чтобы удалить значение |

**Returns:** self - 


<a name="method_setLimit" class="anchor"></a>
#### public setLimit() : self

```php
public setLimit(null|int|string $limit) : self
```

**Summary**

Устанавливает ограничение количества объектов выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR int OR string</code> | limit  | Ограничение количества объектов выплаты или null, чтобы удалить значение |

**Returns:** self - 


<a name="method_setPayoutDestinationType" class="anchor"></a>
#### public setPayoutDestinationType() : self

```php
public setPayoutDestinationType(string|null $payout_destination_type) : self
```

**Summary**

Устанавливает способ получения выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR null</code> | payout_destination_type  | Способ получения выбираемых выплат или null, чтобы удалить значение |

**Returns:** self - 


<a name="method_setStatus" class="anchor"></a>
#### public setStatus() : self

```php
public setStatus(string|null $status) : self
```

**Summary**

Устанавливает статус выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR null</code> | status  | Статус выбираемых выплат или null, чтобы удалить значение |

**Returns:** self - 


<a name="method_toArray" class="anchor"></a>
#### public toArray() : array

```php
public toArray() : array
```

**Summary**

Возвращает ассоциативный массив со свойствами текущего объекта для его дальнейшей JSON сериализации
Является алиасом метода AbstractObject::jsonSerialize().

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

**Returns:** array - Ассоциативный массив со свойствами текущего объекта


<a name="method_validate" class="anchor"></a>
#### public validate() : bool

```php
public validate() : bool
```

**Summary**

Проверяет валидность текущего объекта запроса.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequest](../classes/YooKassa-Request-Payouts-PayoutsRequest.md)

**Returns:** bool - True если объект валиден, false если нет


<a name="method_getUnknownProperties" class="anchor"></a>
#### protected getUnknownProperties() : array

```php
protected getUnknownProperties() : array
```

**Summary**

Возвращает массив свойств которые не существуют, но были заданы у объекта.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

**Returns:** array - Ассоциативный массив с не существующими у текущего объекта свойствами


<a name="method_setValidationError" class="anchor"></a>
#### protected setValidationError() : void

```php
protected setValidationError(string $value) : void
```

**Summary**

Устанавливает ошибку валидации.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractRequest](../classes/YooKassa-Common-AbstractRequest.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | value  | Ошибка, произошедшая при валидации объекта |

**Returns:** void - 


<a name="method_validatePropertyValue" class="anchor"></a>
#### protected validatePropertyValue() : mixed

```php
protected validatePropertyValue(string $propertyName, mixed $propertyValue) : mixed
```

**Details:**
* Inherited From: [\YooKassa\Common\AbstractObject](../classes/YooKassa-Common-AbstractObject.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string</code> | propertyName  |  |
| <code lang="php">mixed</code> | propertyValue  |  |

**Returns:** mixed - 



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