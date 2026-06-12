# Changelog

## 1.0.0 (2026-06-12)


### Features

* add docker-compose.monitoring.yml with ELK + Prometheus/Grafana ([6cab0cb](https://github.com/Azazlokus/payment-gateway/commit/6cab0cb0c4c795a4d6f8413b95f23f4a8cad26ed))
* add SecurityHeaders middleware (CSP, X-Frame-Options, nosniff, Referrer-Policy) ([937e012](https://github.com/Azazlokus/payment-gateway/commit/937e012f8034bb2f51ff8b37ee8f9034dc30a6e2))
* analytics — revenue chart, conversion funnel, Telegram alerts ([4cfaec7](https://github.com/Azazlokus/payment-gateway/commit/4cfaec7c25a9f746c5ec7e278181fb99bfa1edca))
* antifraud velocity limiter — rate limiting per IP, user, payment method ([8c86ec4](https://github.com/Azazlokus/payment-gateway/commit/8c86ec46bf9860fff81f7919c3673c85979b7484))
* API key rotation, SecurityHeaders tests, Postman collection ([1f08a56](https://github.com/Azazlokus/payment-gateway/commit/1f08a565b83bc192ccb5253594d08b750a673985))
* circuit breaker on payment providers ([9524cd7](https://github.com/Azazlokus/payment-gateway/commit/9524cd75c86a161d9d1d651fc742526a5be13422))
* CloudPayments провайдер, observability (Prometheus/Grafana), вебхуки и реестр провайдеров ([87adf6f](https://github.com/Azazlokus/payment-gateway/commit/87adf6faacc968078d21f0b7b5d920181e898243))
* DevX — .devcontainer and TypeScript SDK ([c942ca6](https://github.com/Azazlokus/payment-gateway/commit/c942ca63e747de0471e02dcd3bdec865ad59e515))
* Grafana dashboard, Prometheus alerts, Alertmanager, Telescope, Sentry ([6853d45](https://github.com/Azazlokus/payment-gateway/commit/6853d454d5fe0a7be5f35440f463ad14b40189c0))
* Infection PHP, Nginx rate limiting, email alerts, SSE, soft deletes ([49e4e6d](https://github.com/Azazlokus/payment-gateway/commit/49e4e6d93d6384cdc084c6e201b1167cacd86385))
* Kubernetes Helm chart + CI/CD deploy pipeline ([742d71c](https://github.com/Azazlokus/payment-gateway/commit/742d71c43303729573aea0d1ce48e7227169e409))
* multi-chain crypto deposits — BTC, TRX, USDT-TRC20 ([1932813](https://github.com/Azazlokus/payment-gateway/commit/1932813dc333afb2203c632779a25c5c0378f794))
* multi-stage Dockerfile, Docker environments, healthcheck Redis+Horizon ([51cb73d](https://github.com/Azazlokus/payment-gateway/commit/51cb73d0fbb82f9071d16327f06f23ab603ec6a5))
* outbound webhook logs — track every notification_url delivery attempt ([ba60687](https://github.com/Azazlokus/payment-gateway/commit/ba60687d44efe19cf9644a2aaadcd959344f9c7b))
* payment links — shareable /pay/{token} pages without API ([9120015](https://github.com/Azazlokus/payment-gateway/commit/91200154cf34d2245c07e5580761d13601f25d6c))
* PDF invoice download for succeeded payments ([ca13f1e](https://github.com/Azazlokus/payment-gateway/commit/ca13f1e1d9d98268a779658b9100f0fc98e883d0))
* PHPStan fixes, тесты Robokassa+Logger, Vue метрики, рекуррентные платежи ([02a2f81](https://github.com/Azazlokus/payment-gateway/commit/02a2f810e63dd7e21ae98f68ba239a84b0189e36))
* recurring payments UI — charge saved methods without redirect ([0222e98](https://github.com/Azazlokus/payment-gateway/commit/0222e98f95f8db69339f831d6e65e61cafae9276))
* refund history — track individual refunds per payment ([3d2456c](https://github.com/Azazlokus/payment-gateway/commit/3d2456c602897b269b55eb09cfef1a2b1faa682c))
* refund saga — async processing with compensation ([d005fed](https://github.com/Azazlokus/payment-gateway/commit/d005fed6f2dde90f60b59105ccd9e0d0b63e0018))
* security audit log — table, controller, Vue page, updated AuditLogger ([3c6a21b](https://github.com/Azazlokus/payment-gateway/commit/3c6a21b276cff6df9d1231e8909f90d5261ef29c))
* security, crypto refunds, ELK, tests, PHPStan level 7 ([508173e](https://github.com/Azazlokus/payment-gateway/commit/508173eca806e45fefd660613023d7ed8f8820e0))
* split payments — marketplace revenue distribution ([b311d58](https://github.com/Azazlokus/payment-gateway/commit/b311d58d1cde356d628c11550d61891246d5853d))
* tests, reconcile, healthchecks, Nginx 429, k6 load tests, ADR-009 ([8eeebce](https://github.com/Azazlokus/payment-gateway/commit/8eeebce7740191d7338dae9e9c521d8b37bc2861))
* two-phase payments — authorize, capture, void ([907b0c2](https://github.com/Azazlokus/payment-gateway/commit/907b0c2b85ee1dd254f11a186de9540ad2f692b6))
* UI pages — WebhookLogs, PaymentLinks, nav links ([aeff5bd](https://github.com/Azazlokus/payment-gateway/commit/aeff5bdf328acb0ff3cbba9981e8a6312f4c40ed))
* USDT-TON тесты, фикс QR-payload, CryptoDepositPage (Vue) ([1880cb6](https://github.com/Azazlokus/payment-gateway/commit/1880cb659991991387e38e8a0e2461ba9071ded5))
* версионирование API v1 + обновление документации ([2ba9a86](https://github.com/Azazlokus/payment-gateway/commit/2ba9a86e9380ed5fd4fd5239e3767d747631a97b))
* идемпотентность рефандов + feature-тесты HTTP-слоя ([498ebcc](https://github.com/Azazlokus/payment-gateway/commit/498ebcc4d6af6a2dbb933cb7c479b22568087976))
* инфраструктура очередей, домен, тесты и CI/CD ([4f99aa9](https://github.com/Azazlokus/payment-gateway/commit/4f99aa9626810d8be0d24011650db247bd9c6156))
* провайдер Robokassa + обновление Swagger + README ([adf7184](https://github.com/Azazlokus/payment-gateway/commit/adf71840e5965cd27ad0bfb4dbf108b5d38422b2))
* провайдеры СБП и Альфа-Банк ([3bd0ed7](https://github.com/Azazlokus/payment-gateway/commit/3bd0ed7d31cea997dfc85fa5ab85183feefb72dd))
* реструктуризация monorepo + CryptoPayments (TON/USDT-TON) + Disputes/3DS ([b6bed4f](https://github.com/Azazlokus/payment-gateway/commit/b6bed4fe11273f1930b9edad02ae9beda950995a))
* тесты, IP-фильтрация AlfaBank, Vue 3 SPA фронтенд ([0313e7c](https://github.com/Azazlokus/payment-gateway/commit/0313e7cdf8da89832f079b08828812a0605bfc39))
* фильтр по провайдеру, CSV-экспорт, retry, resync, контрактные тесты ([f3d4a66](https://github.com/Azazlokus/payment-gateway/commit/f3d4a66996e575620b6b92e2fce47ccf03bacb00))


### Bug Fixes

* missing TxHash import in BlockchainClientInterface + null deprecation in logging.php ([06d1ce8](https://github.com/Azazlokus/payment-gateway/commit/06d1ce8a3ba1d1661f1e3a593ad4bf6ada9d7bf6))
* phpstan config, pint style fixes, pint.json exclusions ([8acc7eb](https://github.com/Azazlokus/payment-gateway/commit/8acc7eb2df10ce7f1513cd06d296456924ba002d))
* все тесты зелёные (234/234, 17 skipped Redis) ([74973dd](https://github.com/Azazlokus/payment-gateway/commit/74973ddf7fcfb4155dad98fd12cb29a23e9958ec))
* устранены все ошибки PHPStan level 6 и стилевые замечания Pint ([d10349f](https://github.com/Azazlokus/payment-gateway/commit/d10349fc2a5c01040851203ee5dd0b1b1e192c10))

## Changelog

All notable changes to this project will be documented in this file.

This file is auto-generated by [release-please](https://github.com/googleapis/release-please)
based on [Conventional Commits](https://www.conventionalcommits.org/).

<!-- Release notes will be automatically added here by release-please -->
