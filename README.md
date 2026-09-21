# TaskManager — демо-проект на Symfony 7.4 + Docker

Учебно-портфолиное приложение «менеджер задач»: веб-интерфейс с авторизацией, CRUD,
фильтрами и пагинацией + JSON API с токен-аутентификацией.

## Стек

- **Symfony 7.4 LTS** (PHP 8.2, архитектура по стандартам Symfony Flex)
- **Doctrine ORM** (миграции, фикстуры, QueryBuilder, enum-поля)
- **Twig** + Bootstrap 5
- **Docker Compose**: nginx + php-fpm + PostgreSQL 16 (+ Mailpit для писем)

## Быстрый старт

```bash
docker compose up -d          # поднять nginx, php-fpm, postgres
docker compose exec app php bin/console doctrine:database:create --if-not-exists
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

Открыть: **http://localhost:8081**

Демо-аккаунты (пароль у обоих `password123`):

| Пользователь     | Роль       | API-токен         |
|------------------|------------|-------------------|
| `admin@demo.local` | ROLE_ADMIN | `admin-demo-token` |
| `alex@demo.local`  | ROLE_USER  | `alex-demo-token`  |

## JSON API

Аутентификация — заголовок `X-AUTH-TOKEN` (см. `src/Security/ApiTokenAuthenticator.php`).

```bash
# список задач с фильтрами
curl -H "X-AUTH-TOKEN: alex-demo-token" "http://localhost:8081/api/tasks?status=new&perPage=20"

# создать задачу
curl -X POST http://localhost:8081/api/tasks \
     -H "X-AUTH-TOKEN: alex-demo-token" \
     -H "Content-Type: application/json" \
     -d '{"title":"Новая задача","priority":"high","dueDate":"2026-10-01","categoryId":1}'

# без токена / с неверным токеном -> 401, с ошибками валидации -> 422
```

## Что демонстрирует проект

| Концепция Symfony | Где смотреть |
|---|---|
| Контроллеры, роутинг-атрибуты | `src/Controller/` |
| Сущности + связи (ManyToOne/OneToMany), lifecycle callbacks | `src/Entity/` |
| PHP 8.1 Enum в Doctrine (`enumType:`) | `src/Enum/`, `src/Entity/Task.php` |
| Репозитории, QueryBuilder, пагинация, поиск | `src/Repository/TaskRepository.php` |
| Формы + валидация (атрибуты Assert) | `src/Form/`, `src/Dto/TaskInput.php` |
| Сервис-слой и DI, bind параметров из конфига | `src/Service/TaskStatisticsService.php`, `config/services.yaml` |
| События и подписчики (EventDispatcher) | `src/Event/`, `src/EventSubscriber/` |
| Безопасность: form login, Voter, токен-аутентификатор | `src/Security/`, `config/packages/security.yaml` |
| JSON API + Serializer (группы сериализации) | `src/Controller/Api/TaskApiController.php` |
| Консольные команды | `src/Command/TaskStatsCommand.php` |
| Миграции и фикстуры | `migrations/`, `src/DataFixtures/` |
| Монолог: именованные каналы логов | `config/packages/monolog.yaml` |
| Шаблоны Twig, наследование, form themes | `templates/` |

## Полезные команды

```bash
docker compose exec app php bin/console app:stats        # статистика задач
docker compose exec app php bin/console app:stats alex@demo.local
docker compose exec app php bin/console debug:router     # все маршруты
docker compose exec app php bin/console debug:container  # все сервисы DI
tail -f var/log/dev.task_activity.log                    # лог доменных событий
docker compose down -v                                   # снести вместе с данными БД
```

## Структура

```
docker/                  # Dockerfile (php-fpm) и конфиг nginx
compose.yaml             # nginx + php-fpm + postgres (+ mailpit из compose.override.yaml)
public/                  # точка входа index.php
src/
  Controller/            # веб-контроллеры + Api/
  Entity/  Repository/   # Doctrine
  Form/    Dto/          # формы и DTO для API
  Security/              # ApiTokenAuthenticator, Voter
  Service/               # бизнес-логика (статистика)
  Event/  EventSubscriber/  # доменные события
  Command/               # консольные команды
templates/               # Twig
config/                  # конфигурация бандлов
```
