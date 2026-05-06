# [YooKassa API SDK](../home.md)

# Class: \YooKassa\Request\Payments\StatementData\DeliveryMethod\DeliveryMethodFactory
### Namespace: [\YooKassa\Request\Payments\StatementData\DeliveryMethod](../namespaces/yookassa-request-payments-statementdata-deliverymethod.md)
---
**Summary:**

Класс, представляющий модель DeliveryMethodFactory.

**Description:**

Фабрика создания объекта способа доставки справки из массива.

---
### Constants
* No constants found

---
### Methods
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [factory()](../classes/YooKassa-Request-Payments-StatementData-DeliveryMethod-DeliveryMethodFactory.md#method_factory) |  | Фабричный метод создания объекта способа доставки справки по типу. |
| public | [factoryFromArray()](../classes/YooKassa-Request-Payments-StatementData-DeliveryMethod-DeliveryMethodFactory.md#method_factoryFromArray) |  | Фабричный метод создания объекта способа доставки справки из массива. |

---
### Details
* File: [lib/Request/Payments/StatementData/DeliveryMethod/DeliveryMethodFactory.php](../../lib/Request/Payments/StatementData/DeliveryMethod/DeliveryMethodFactory.php)
* Package: YooKassa\Request
* Class Hierarchy:
  * \YooKassa\Request\Payments\StatementData\DeliveryMethod\DeliveryMethodFactory

* See Also:
  * [](https://yookassa.ru/developers/api)

---
### Tags
| Tag | Version | Description |
| --- | ------- | ----------- |
| category |  | Class |
| author |  | cms@yoomoney.ru |

---
## Methods
<a name="method_factory" class="anchor"></a>
#### public factory() : \YooKassa\Request\Payments\StatementData\DeliveryMethod\AbstractDeliveryMethod

```php
public factory(string|null $type = null) : \YooKassa\Request\Payments\StatementData\DeliveryMethod\AbstractDeliveryMethod
```

**Summary**

Фабричный метод создания объекта способа доставки справки по типу.

**Details:**
* Inherited From: [\YooKassa\Request\Payments\StatementData\DeliveryMethod\DeliveryMethodFactory](../classes/YooKassa-Request-Payments-StatementData-DeliveryMethod-DeliveryMethodFactory.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">string OR null</code> | type  | Тип способа доставки справки |

**Returns:** \YooKassa\Request\Payments\StatementData\DeliveryMethod\AbstractDeliveryMethod - 


<a name="method_factoryFromArray" class="anchor"></a>
#### public factoryFromArray() : \YooKassa\Request\Payments\StatementData\DeliveryMethod\AbstractDeliveryMethod

```php
public factoryFromArray(array|null $data = null, string|null $type = null) : \YooKassa\Request\Payments\StatementData\DeliveryMethod\AbstractDeliveryMethod
```

**Summary**

Фабричный метод создания объекта способа доставки справки из массива.

**Details:**
* Inherited From: [\YooKassa\Request\Payments\StatementData\DeliveryMethod\DeliveryMethodFactory](../classes/YooKassa-Request-Payments-StatementData-DeliveryMethod-DeliveryMethodFactory.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">array OR null</code> | data  | Массив платежных данных |
| <code lang="php">string OR null</code> | type  | Тип способа доставки справки |

**Returns:** \YooKassa\Request\Payments\StatementData\DeliveryMethod\AbstractDeliveryMethod - 



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