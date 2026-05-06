# [YooKassa API SDK](../home.md)

# Class: \YooKassa\Request\Payouts\PayoutsRequestSerializer
### Namespace: [\YooKassa\Request\Payouts](../namespaces/yookassa-request-payouts.md)
---
**Summary:**

Класс, представляющий модель PayoutsRequestSerializer.

**Description:**

Класс объекта осуществляющего сериализацию запроса к API для получения списка выплат.

---
### Constants
* No constants found

---
### Methods
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [serialize()](../classes/YooKassa-Request-Payouts-PayoutsRequestSerializer.md#method_serialize) |  | Сериализует объект запроса к API для дальнейшей его отправки. |

---
### Details
* File: [lib/Request/Payouts/PayoutsRequestSerializer.php](../../lib/Request/Payouts/PayoutsRequestSerializer.php)
* Package: YooKassa\Request
* Class Hierarchy:
  * \YooKassa\Request\Payouts\PayoutsRequestSerializer

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
<a name="method_serialize" class="anchor"></a>
#### public serialize() : array

```php
public serialize(\YooKassa\Request\Payouts\PayoutsRequestInterface $request) : array
```

**Summary**

Сериализует объект запроса к API для дальнейшей его отправки.

**Details:**
* Inherited From: [\YooKassa\Request\Payouts\PayoutsRequestSerializer](../classes/YooKassa-Request-Payouts-PayoutsRequestSerializer.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">\YooKassa\Request\Payouts\PayoutsRequestInterface</code> | request  | Сериализуемый объект |

**Returns:** array - Массив с информацией, отправляемый в дальнейшем в API



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