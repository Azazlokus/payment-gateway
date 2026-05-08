# Contributing Guide

## Формат коммитов

Проект использует [Conventional Commits](https://www.conventionalcommits.org/).
Это обязательно — иначе `release-please` не сможет собрать changelog автоматически.

### Структура

```
<type>(<scope>): <subject>

[body]

[footer]
```

### Типы (type)

| Тип        | Когда использовать                           | Влияет на версию |
|------------|----------------------------------------------|------------------|
| `feat`     | Новая функциональность                       | minor `0.X.0`    |
| `fix`      | Исправление бага                             | patch `0.0.X`    |
| `perf`     | Улучшение производительности                 | patch `0.0.X`    |
| `refactor` | Рефакторинг без изменения поведения          | —                |
| `docs`     | Только документация                          | —                |
| `test`     | Тесты                                        | —                |
| `chore`    | Зависимости, конфиги, билд                   | —                |
| `ci`       | GitHub Actions, CI/CD                        | —                |
| `revert`   | Откат коммита                                | patch `0.0.X`    |

> `BREAKING CHANGE:` в теле коммита или `!` после type → major `X.0.0`

### Scope (необязательно)

Компонент который меняешь: `payments`, `webhooks`, `auth`, `k8s`, `docs`

### Примеры

```bash
# Новая фича → попадает в ## Features changelog
feat(payment-links): add shareable /pay/{token} pages

# Фикс бага → попадает в ## Bug Fixes
fix(sse): close event stream on terminal payment status

# Breaking change → major версия
feat(api)!: replace offset pagination with cursor-based

BREAKING CHANGE: query params ?page= and ?per_page= replaced by ?cursor=

# Документация (не попадает в changelog — hidden: true)
docs: add ADR-009 for soft deletes decision

# Тест (не попадает в changelog)
test(webhooks): add CloudPayments webhook contract tests
```

### Установка хуков (один раз после clone)

```bash
npm install          # устанавливает husky + commitlint
npm run prepare      # активирует git hooks
```

После этого при каждом `git commit` commitlint автоматически проверит формат.

### Если хук мешает (hotfix)

```bash
git commit --no-verify -m "fix: hotfix prod"
```

Используй редко — это обходит проверку локально, но CI всё равно проверит в PR.

## Процесс релиза

1. Мерджишь PR с conventional commits в `master`
2. `release-please` автоматически создаёт **Release PR**:
   - Обновляет `CHANGELOG.md`
   - Бампает версию в `.release-please-manifest.json`
3. Ты ревьюишь Release PR и мерджишь
4. `release-please` создаёт тег `v1.2.3` и GitHub Release
5. `deploy.yml` подхватывает тег → деплой в production (с ручным approve)
