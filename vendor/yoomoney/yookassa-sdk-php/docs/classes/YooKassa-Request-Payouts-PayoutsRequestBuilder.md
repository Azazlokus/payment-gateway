# [YooKassa API SDK](../home.md)

# Class: \YooKassa\Request\Payouts\PayoutsRequestBuilder
### Namespace: [\YooKassa\Request\Payouts](../namespaces/yookassa-request-payouts.md)
---
**Summary:**

Класс, представляющий модель PayoutsRequestBuilder.

**Description:**

Класс билдера объекта запроса для получения списка выплат магазина, передаваемого в методы клиента API.

---
### Constants
* No constants found

---
### Properties
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| protected | [$currentObject](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#property_currentObject) |  | Инстанс собираемого запроса. |

---
### Methods
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [__construct()](../classes/YooKassa-Common-AbstractRequestBuilder.md#method___construct) |  | Конструктор, инициализирует пустой запрос, который в будущем начнём собирать. |
| public | [build()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_build) |  | Собирает и возвращает объект запроса списка выплат магазина. |
| public | [setCreatedAtGt()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_setCreatedAtGt) |  | Устанавливает дату создания от которой выбираются выплаты. |
| public | [setCreatedAtGte()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_setCreatedAtGte) |  | Устанавливает дату создания от которой выбираются выплаты. |
| public | [setCreatedAtLt()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_setCreatedAtLt) |  | Устанавливает дату создания до которой выбираются выплаты. |
| public | [setCreatedAtLte()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_setCreatedAtLte) |  | Устанавливает дату создания до которой выбираются выплаты. |
| public | [setCursor()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_setCursor) |  | Устанавливает страница выдачи результатов. |
| public | [setLimit()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_setLimit) |  | Устанавливает ограничение количества объектов выплаты. |
| public | [setOptions()](../classes/YooKassa-Common-AbstractRequestBuilder.md#method_setOptions) |  | Устанавливает свойства запроса из массива. |
| public | [setPayoutDestinationType()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_setPayoutDestinationType) |  | Устанавливает способ получения выбираемых выплат. |
| public | [setStatus()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_setStatus) |  | Устанавливает статус выбираемых выплат. |
| protected | [initCurrentObject()](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md#method_initCurrentObject) |  | Возвращает новый объект запроса для получения списка выплат, который в дальнейшем будет собираться в билдере. |

---
### Details
* File: [lib/Request/Payouts/PayoutsRequestBuilder.php](../../lib/Request/Payouts/PayoutsRequestBuilder.php)
* Package: YooKassa\Request
* Class Hierarchy: 
  * [\YooKassa\Common\AbstractRequestBuilder](../classes/YooKassa-Common-AbstractRequestBuilder.md)
  * \YooKassa\Request\Payouts\PayoutsRequestBuilder

* See Also:
  * [](https://yookassa.ru/developers/api)

---
### Tags
| Tag | Version | Description |
| --- | ------- | ----------- |
| category |  | Class |
| author |  | cms@yoomoney.ru |

---
## Properties
<a name="property_currentObject"></a>
#### protected $currentObject : ?\YooKassa\Common\AbstractRequestInterface
---
**Summary**

Инстанс собираемого запроса.

**Type:** <a href="../?\YooKassa\Common\AbstractRequestInterface"><abbr title="?\YooKassa\Common\AbstractRequestInterface">AbstractRequestInterface</abbr></a>
Собираемый объект запроса списка выплат магазина
**Details:**



---
## Methods
<a name="method___construct" class="anchor"></a>
#### public __construct() : mixed

```php
public __construct() : mixed
```

**Summary**

Конструктор, инициализирует пустой запрос, который в будущем начнём собирать.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractRequestBuilder](../classes/YooKassa-Common-AbstractRequestBuilder.md)

**Returns:** mixed - 


<a name="method_build" class="anchor"></a>
#### public build() : \YooKassa\Request\Payouts\PayoutsRequest

```php
public build(null|array $options = null) : \YooKassa\Request\Payouts\PayoutsRequest
```

**Summary**

Собирает и возвращает объект запроса списка выплат магазина.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR array</code> | options  | Массив с настройками запроса |

**Returns:** \YooKassa\Request\Payouts\PayoutsRequest - Инстанс объекта запроса к API для получения списка выплат магазина


<a name="method_setCreatedAtGt" class="anchor"></a>
#### public setCreatedAtGt() : \YooKassa\Request\Payouts\PayoutsRequestBuilder

```php
public setCreatedAtGt(null|\DateTime|int|string $value) : \YooKassa\Request\Payouts\PayoutsRequestBuilder
```

**Summary**

Устанавливает дату создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR \DateTime OR int OR string</code> | value  | Время создания, от (не включая) или null, чтобы удалить значение |

##### Throws:
| Type | Description |
| ---- | ----------- |
| \YooKassa\Common\Exceptions\InvalidPropertyValueException | Генерируется если была передана дата в невалидном формате (была передана строка или число, которые не удалось преобразовать в валидную дату) |
| \Exception|\YooKassa\Common\Exceptions\InvalidPropertyValueTypeException | Генерируется если была передана дата с не тем типом (передана не строка, не число и не значение типа DateTime) |

**Returns:** \YooKassa\Request\Payouts\PayoutsRequestBuilder - Инстанс текущего билдера


<a name="method_setCreatedAtGte" class="anchor"></a>
#### public setCreatedAtGte() : \YooKassa\Request\Payouts\PayoutsRequestBuilder

```php
public setCreatedAtGte(null|\DateTime|int|string $value) : \YooKassa\Request\Payouts\PayoutsRequestBuilder
```

**Summary**

Устанавливает дату создания от которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR \DateTime OR int OR string</code> | value  | Время создания, от (включительно) или null, чтобы удалить значение |

##### Throws:
| Type | Description |
| ---- | ----------- |
| \YooKassa\Common\Exceptions\InvalidPropertyValueException | Генерируется если была передана дата в невалидном формате (была передана строка или число, которые не удалось преобразовать в валидную дату) |
| \Exception|\YooKassa\Common\Exceptions\InvalidPropertyValueTypeException | Генерируется если была передана дата с не тем типом (передана не строка, не число и не значение типа DateTime) |

**Returns:** \YooKassa\Request\Payouts\PayoutsRequestBuilder - Инстанс текущего билдера


<a name="method_setCreatedAtLt" class="anchor"></a>
#### public setCreatedAtLt() : \YooKassa\Request\Payouts\PayoutsRequestBuilder

```php
public setCreatedAtLt(null|\DateTime|int|string $value) : \YooKassa\Request\Payouts\PayoutsRequestBuilder
```

**Summary**

Устанавливает дату создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR \DateTime OR int OR string</code> | value  | Время создания, до (не включая) или null, чтобы удалить значение |

##### Throws:
| Type | Description |
| ---- | ----------- |
| \YooKassa\Common\Exceptions\InvalidPropertyValueException | Генерируется если была передана дата в невалидном формате (была передана строка или число, которые не удалось преобразовать в валидную дату) |
| \Exception|\YooKassa\Common\Exceptions\InvalidPropertyValueTypeException | Генерируется если была передана дата с не тем типом (передана не строка, не число и не значение типа DateTime) |

**Returns:** \YooKassa\Request\Payouts\PayoutsRequestBuilder - Инстанс текущего билдера


<a name="method_setCreatedAtLte" class="anchor"></a>
#### public setCreatedAtLte() : \YooKassa\Request\Payouts\PayoutsRequestBuilder

```php
public setCreatedAtLte(null|\DateTime|int|string $value) : \YooKassa\Request\Payouts\PayoutsRequestBuilder
```

**Summary**

Устанавливает дату создания до которой выбираются выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR \DateTime OR int OR string</code> | value  | Время создания, до (включительно) или null, чтобы удалить значение |

##### Throws:
| Type | Description |
| ---- | ----------- |
| \YooKassa\Common\Exceptions\InvalidPropertyValueException | Генерируется если была передана дата в невалидном формате (была передана строка или число, которые не удалось преобразовать в валидную дату) |
| \Exception|\YooKassa\Common\Exceptions\InvalidPropertyValueTypeException | Генерируется если была передана дата с не тем типом (передана не строка, не число и не значение типа DateTime) |

**Returns:** \YooKassa\Request\Payouts\PayoutsRequestBuilder - Инстанс текущего билдера


<a name="method_setCursor" class="anchor"></a>
#### public setCursor() : \YooKassa\Request\Payouts\PayoutsRequestBuilder

```php
public setCursor(null|string $value) : \YooKassa\Request\Payouts\PayoutsRequestBuilder
```

**Summary**

Устанавливает страница выдачи результатов.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">null OR string</code> | value  | Страница выдачи результатов или null, чтобы удалить значение |

##### Throws:
| Type | Description |
| ---- | ----------- |
| \YooKassa\Common\Exceptions\InvalidPropertyValueTypeException | Выбрасывается если в метод была передана не строка |

**Returns:** \YooKassa\Request\Payouts\PayoutsRequestBuilder - Инстанс текущего билдера


<a name="method_setLimit" class="anchor"></a>
#### public setLimit() : \YooKassa\Request\Payouts\PayoutsRequestBuilder

```php
public setLimit(string|int|null $value) : \YooKassa\Request\Payouts\PayoutsRequestBuilder
```

**Summary**

Устанавливает ограничение количества объектов выплаты.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR int OR null</code> | value  | Ограничение количества объектов выплаты или null, чтобы удалить значение |

##### Throws:
| Type | Description |
| ---- | ----------- |
| \YooKassa\Common\Exceptions\InvalidPropertyValueTypeException | Выбрасывается если в метод было передана не целое число |

**Returns:** \YooKassa\Request\Payouts\PayoutsRequestBuilder - Инстанс текущего билдера


<a name="method_setOptions" class="anchor"></a>
#### public setOptions() : \YooKassa\Common\AbstractRequestBuilder

```php
public setOptions(iterable|null $options) : \YooKassa\Common\AbstractRequestBuilder
```

**Summary**

Устанавливает свойства запроса из массива.

**Details:**
* Inherited From: [\YooKassa\Common\AbstractRequestBuilder](../classes/YooKassa-Common-AbstractRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">iterable OR null</code> | options  | Массив свойств запроса |

##### Throws:
| Type | Description |
| ---- | ----------- |
| \InvalidArgumentException | Выбрасывается если аргумент не массив и не итерируемый объект |
| \YooKassa\Common\Exceptions\InvalidPropertyException | Выбрасывается если не удалось установить один из параметров, переданных в массиве настроек |

**Returns:** \YooKassa\Common\AbstractRequestBuilder - Инстанс текущего билдера запросов


<a name="method_setPayoutDestinationType" class="anchor"></a>
#### public setPayoutDestinationType() : \YooKassa\Request\Payouts\PayoutsRequestBuilder

```php
public setPayoutDestinationType(string|null $value) : \YooKassa\Request\Payouts\PayoutsRequestBuilder
```

**Summary**

Устанавливает способ получения выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR null</code> | value  | Платежный метод выбираемых выплат или null, чтобы удалить значение |

##### Throws:
| Type | Description |
| ---- | ----------- |
| \YooKassa\Common\Exceptions\InvalidPropertyValueException | Выбрасывается если переданное значение не является валидным статусом |
| \YooKassa\Common\Exceptions\InvalidPropertyValueTypeException | Выбрасывается если в метод была передана не строка |

**Returns:** \YooKassa\Request\Payouts\PayoutsRequestBuilder - Инстанс текущего билдера


<a name="method_setStatus" class="anchor"></a>
#### public setStatus() : \YooKassa\Request\Payouts\PayoutsRequestBuilder

```php
public setStatus(string|null $value) : \YooKassa\Request\Payouts\PayoutsRequestBuilder
```

**Summary**

Устанавливает статус выбираемых выплат.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR null</code> | value  | Статус выбираемых выплат или null, чтобы удалить значение |

##### Throws:
| Type | Description |
| ---- | ----------- |
| \YooKassa\Common\Exceptions\InvalidPropertyValueException | Выбрасывается если переданное значение не является валидным статусом |
| \YooKassa\Common\Exceptions\InvalidPropertyValueTypeException | Выбрасывается если в метод была передана не строка |

**Returns:** \YooKassa\Request\Payouts\PayoutsRequestBuilder - Инстанс текущего билдера


<a name="method_initCurrentObject" class="anchor"></a>
#### protected initCurrentObject() : \YooKassa\Request\Payouts\PayoutsRequest

```php
protected initCurrentObject() : \YooKassa\Request\Payouts\PayoutsRequest
```

**Summary**

Возвращает новый объект запроса для получения списка выплат, который в дальнейшем будет собираться в билдере.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestBuilder](../classes/YooKassa-Request-Payouts-PayoutsRequestBuilder.md)

**Returns:** \YooKassa\Request\Payouts\PayoutsRequest - Объект запроса списка выплат магазина



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