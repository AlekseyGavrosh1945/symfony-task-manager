# Как работает этот проект — конспект для собеседования

## 1. Путь запроса: открываем /tasks

```
Браузер → nginx → public/index.php → Роутер → Проверка доступа → Контроллер → Doctrine → Twig → HTML
```

1. **nginx** (docker/nginx/default.conf): если URL — не картинка/файл, запрос уходит в `public/index.php`. В Laravel то же самое — `public/index.php`.
2. **index.php создаёт Kernel («ядро»)** — дирижёр. Он собирает **DI-контейнер**: список всех объектов приложения и правил их создания. Почти каждый класс из `src/` попадает туда автоматически — за это отвечает `App\: resource: '../src/'` в `config/services.yaml`.
3. **Роутер**: URL `/tasks` → ищет, кто обрабатывает. Это `TaskController::index()`, над которым висит `#[Route('/tasks', name: 'app_task_index')]`. В Laravel маршруты лежат в `routes/web.php` — то же самое, другое место.
4. **Проверка доступа**: `config/packages/security.yaml`, секция `access_control` — всё кроме `/login` требует `ROLE_USER`. Не залогинен → редирект на `/login`, контроллер даже не запустится. (= `middleware('auth')` в Laravel.)
5. **Контроллер**: Symfony смотрит на аргументы метода и **сам** их подставляет:
   ```php
   public function index(Request $request, TaskRepository $taskRepository, ...): Response
   ```
   `Request` — объект запроса, `TaskRepository` — достаёт из контейнера. Нигде не пишем `new` — Symfony смотрит на type-hint. Это и есть **dependency injection**.
6. **Doctrine** (получение данных): `$taskRepository->searchForUser(...)` строит SQL через QueryBuilder (WHERE owner_id = ... + фильтры + LIMIT/OFFSET) и превращает строки БД в **объекты Task**.
7. **Twig**: `return $this->render('task/index.html.twig', ['tasks' => ...])`. Шаблон: `{{ task.title }}`, `{% for %}` (= `{{ $task->title }}`, `@foreach` в Blade).

## 2. Главное отличие ORM: Doctrine vs Eloquent

- **Eloquent = Active Record.** Модель `Task extends Model` сама умеет всё: `Task::where(...)->get()`, `$task->save()`. Модель = строка таблицы.
- **Doctrine = Data Mapper.** Сущность `Task` (`src/Entity/Task.php`) — «глупый» объект-данные с геттерами, о БД не знает ничего. Запросы — в **Repository**, сохранение — через **EntityManager**: `$em->persist($task); $em->flush();`.

Формулировка для собеседования: «В Doctrine модель и доступ к данным разделены: сущность — чистый объект, вся работа с БД — в репозитории».

## 3. Как работает логин

Настраивается в `security.yaml`: `password_hashers` (чем сверять пароль), `providers` (откуда брать юзера — из таблицы по email), `firewalls` (правила).

При POST `/login`:
1. Контроллер **не выполняется** — запрос перехватывает `form_login` из firewall. Symfony сам: находит юзера по email, сверяет пароль, кладёт в сессию.
2. `SecurityController::login()` выполняется только на GET — рисует форму и показывает ошибку прошлой попытки.
3. На каждом следующем запросе ядро достаёт юзера из сессии → в контроллере доступен `$this->getUser()`.
4. `logout()` пустой с throw — Symfony перехватывает URL и сам чистит сессию.

В Laravel ты это же пишешь руками (`Auth::attempt()` в LoginController). В Symfony — конфиг вместо кода.

**Voter** (= Policy в Laravel): в `TaskController::edit()` первая строка `denyAccessUnlessGranted(TaskVoter::EDIT, $task)` → Symfony вызывает `TaskVoter::voteOnAttribute()`: владелец задачи === юзер (или админ)? Пускаем. Иначе 403.

## 4. Как работает API

`GET /api/tasks` с заголовком `X-AUTH-TOKEN: alex-demo-token`:

1. У firewall два «охранника»-аутентификатора. `form_login` отказывается (не POST /login). `ApiTokenAuthenticator::supports()`: URL начинается с `/api` и есть заголовок? Моё.
2. `authenticate()`: токен из заголовка → `findOneBy(['apiToken' => ...])` в БД. Нашли юзера — аутентифицирован. Нет → 401 JSON.
3. Ответ: `$this->json($items, context: ['groups' => ['task:read']])`. **Serializer** превращает объекты Task в JSON, но берёт только поля с атрибутом `#[Groups(['task:read'])]`. Поэтому в JSON нет password и apiToken. (= API Resource в Laravel.)
4. `POST /api/tasks`: JSON → DTO `TaskInput` (просто класс с полями), `Validator` проверяет правила, ошибки → 422 со списком. (= FormRequest.)

## 5. События

После создания задачи в контроллере:
```php
$this->eventDispatcher->dispatch(new TaskCreatedEvent($task)); // «выстрел в воздух»
```
`TaskActivitySubscriber::getSubscribedEvents()` говорит «слушаю TaskCreatedEvent» → пишет строку в отдельный лог-канал (`var/log/dev.task_activity.log`).

Смысл: контроллер не знает про логирование. Нужен email при создании — добавляем нового подписчика, контроллер не трогаем. В Laravel: Event + Listener, идея та же.

## 6. Docker

`compose.yaml`: **nginx** (принимает HTTP:8081, отдаёт статику) → **app/php-fpm** (выполняет PHP; в Dockerfile ставятся расширения pdo_pgsql/intl/zip) → **postgres** (БД, данные в docker-томе). Плюс mailpit (ловушка писем) из скелета.

База для приложения — по хосту `database` (имя сервиса резолвится внутри docker-сети), поэтому в `.env`: `DATABASE_URL="postgresql://app:app@database:5432/app"`.

## 7. Рассказ про проект (заучить)

«Демо-проект на Symfony 7 — менеджер задач. Веб-часть: форма-логин, CRUD задач с фильтрами и пагинацией, Twig + Bootstrap. JSON API с авторизацией по токену и валидацией через DTO. Данные — Doctrine + PostgreSQL. Всё в Docker: nginx, php-fpm, postgres. Из архитектуры: сервис-слой для статистики, доменные события с подписчиком, Voter для проверки владельца, миграции и фикстуры».

## 8. «Чем Symfony отличается от Laravel?» (заучить)

«Архитектурно похожи, разница в стиле. Symfony — набор независимых компонентов и явная конфигурация: всё видно в DI-контейнере и yaml-конфигах, магии мало. Laravel — про конвенции и скорость разработки: фасады, хелперы, Eloquent. Практически главная разница — ORM: Eloquent — Active Record, модель сама умеет save(); Doctrine — Data Mapper: сущность — просто объект, запросы в Repository, сохранение через EntityManager».

## 9. Вопросы в лоб — ответы одной строкой

- **Что такое DI-контейнер?** Реестр всех сервисов приложения; сам создаёт объекты и подставляет зависимости по type-hint.
- **Как связаны контроллер и БД?** Контроллер → Repository (QueryBuilder) → Doctrine → SQL → массив объектов-сущностей.
- **Как пользователь авторизован?** Веб — сессия (form_login), API — токен из заголовка через кастомный Authenticator.
- **Как добавить email-уведомление при создании задачи?** Новый подписчик на уже существующее событие TaskCreatedEvent. Контроллер не трогаю.
- **Миграции?** `make:migration` генерирует PHP-файл с SQL по изменению сущностей, `doctrine:migrations:migrate` применяет.
