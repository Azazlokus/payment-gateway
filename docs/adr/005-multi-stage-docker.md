# ADR-005: Multi-stage Docker builds

## Status
Accepted

## Context

Единый Docker образ для всех окружений создаёт проблемы:
- **Размер**: dev-инструменты (Xdebug, PHPStan, Pint, тесты) не нужны в продакшне, но попадают в образ
- **Безопасность**: Xdebug в продакшне — потенциальная уязвимость; запуск от root — нарушение принципа least privilege
- **Производительность**: в продакшне нужен OPcache + JIT, в разработке они мешают (кэшируют изменения кода)
- **Разные точки входа**: `app` запускает PHP-FPM, `horizon` запускает `artisan horizon`

## Decision

Используем **4-stage multi-stage Dockerfile**:

```
base ──→ dev          (разработка: Xdebug, dev-зависимости Composer)
     └─→ prod ──→ prod-worker  (продакшн: OPcache+JIT, non-root user)
```

| Стадия | На основе | Ключевые отличия |
|---|---|---|
| `base` | php:8.4-fpm-alpine | PHP расширения, Composer deps `--no-dev`, код |
| `dev` | base | + Xdebug, + dev Composer deps |
| `prod` | base | + OPcache+JIT, non-root `appuser` (uid 1001) |
| `prod-worker` | prod | Тот же образ, другой ENTRYPOINT → `artisan horizon` |

Разделение окружений через Docker Compose override-файлы:
- `docker-compose.override.yml` — автоматически для локальной разработки
- `docker-compose.prod.yml` — явно через `-f` (override не применяется)

## Consequences

**Плюсы:**
- Prod образ не содержит Xdebug, PHPUnit, PHPStan — меньше поверхность атаки
- Слои `base` кэшируются: `dev` и `prod` не пересобирают зависимости
- Non-root user в продакшне: даже при RCE атакующий не получает root
- `prod-worker` переиспользует prod образ — один build, два контейнера

**Минусы:**
- Сложнее Dockerfile (70+ строк vs 15)
- `prod-worker` временно переключается на root для `chmod` entrypoint-скрипта
- `unsafe-inline` в CSP из-за Swagger UI и Horizon — TODO: перейти на nonce
