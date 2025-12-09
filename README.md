# Backend (Laravel) — Gesture Path Learn

Backend-проект на Laravel, обеспечивающий REST API для платформы обучения жестовому языку и синхронизированного медиаплеера. Реализует:

- Аутентификацию и авторизацию (Sanctum, куки/токены)
- Каталог курсов, модулей и уроков (CRUD, публикация)
- Хранение и выдачу медиа (видео, субтитры VTT, 3D-модели, постеры)
- Прогресс пользователя по урокам (просмотр, сохранение позиций, длительность)
- Карьерные тесты (вопросы, результаты)
- Административные операции: загрузка медиа, статистика

Документы для разработчиков:
- `docs/CODEBASE_OVERVIEW.md` — обзор кодовой базы, ключевые сущности и связности
- `docs/ROUTES.md` — маршруты API, примеры запросов/ответов, бизнес-логика

## Требования к системе

- PHP `>= 8.2`
- Composer `>= 2.x`
- MySQL/MariaDB или другой поддерживаемый драйвер БД
- Расширения PHP: `openssl`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`
- Node.js (не обязательно для бэкенда, но полезно для фронта)
- Windows (разработка), Linux/macOS (совместимо)

Опционально:
- Ngrok для туннелирования локального сервера
- S3-совместимое хранилище для медиа (через `FILESYSTEM_DISK=s3`)

## Установка и настройка

1) Клонирование и зависимости
- Склонируйте репозиторий и перейдите в папку `backend-laravel`
- Установите зависимости: `composer install`

2) Конфигурация окружения
- Скопируйте `.env.example` → `.env`
- Установите ключ приложения: `php artisan key:generate`
- Настройте подключение к БД (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
- Установите `APP_URL` (например, `http://127.0.0.1:8000` или публичный ngrok-URL)
- Для работы с куками (Sanctum) скорректируйте `SESSION_DOMAIN` и `SANCTUM_STATEFUL_DOMAINS` при использовании доменов фронтенда

3) Миграции и сиды
- Миграции: `php artisan migrate`
- Сиды данных: `php artisan db:seed` (доступны `CareerTestSeeder`, `CourseSeeder`, `ModulesAndLessonsSeeder`, `CourseContentWithTextsAndMediaSeeder` и др.)

4) Статика и файловая система
- Создайте симлинк для публичного доступа: `php artisan storage:link`
- По умолчанию `FILESYSTEM_DISK=local` хранит файлы в `storage/app/public` и отдаёт их по `APP_URL/storage/...`
- Для S3 заполните `AWS_*` переменные и установите `FILESYSTEM_DISK=s3`

## Запуск

- Локально: `php artisan serve` → `http://127.0.0.1:8000`
- Через ngrok: `ngrok http 8000` → получите публичный URL и установите его в `APP_URL`
- Взаимодействие с фронтендом:
  - Разрешите домены фронта в `config/cors.php` (`allowed_origins`/`allowed_origins_patterns`)
  - Для `supports_credentials=true` укажите корректные `SESSION_DOMAIN`/`SANCTUM_STATEFUL_DOMAINS`

## Тестирование

- Запуск тестов: `php artisan test`
- Базовые тесты размещены в `tests/Feature` и `tests/Unit`
- Для тестов, требующих БД, убедитесь, что настройки `.env.testing` корректны

## Переменные окружения (ключевые)

- `APP_URL` — базовый публичный URL API, влияет на генерацию ссылок `asset('storage/...')`
- `DB_*` — параметры подключения к БД
- `FILESYSTEM_DISK` — `local` или `s3`
- `SESSION_DRIVER` — рекомендуется `database` для разработки вместе с фронтендом
- `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` — домены фронта для корректной работы куков
- `AWS_*` — при использовании S3 для хранения медиа

## Архитектура и ключевые компоненты

- Контроллеры (`app/Http/Controllers/Api`):
  - `AuthController` — регистрация/логин/профиль (Sanctum)
  - `CourseController` — выдача курсов, зачисление, каталог
  - `ModuleController` — модули курса и их упорядочивание
  - `LessonController` — детали урока, видео-URL, субтитры (VTT/генерация из текста), сохранение прогресса
  - `ProgressController` — сбор и выдача прогресса пользователя, последняя позиция воспроизведения
  - `AdminController` — CRUD курсов/модулей/уроков, загрузка медиа (видео/субтитры/GLB), статистика

- Модели (`app/Models`):
  - `Course`, `Module`, `Lesson` — базовые учебные сущности
  - `LessonMedia` — медиафайлы урока (тип: `video`/`subtitles`/`model`/`document`), `full_url`-аксессор
  - `LessonText` — текст урока, разбиение на предложения и оценка длительности
  - `Progress`, `Purchase` — прогресс и покупки/зачисления
  - `Career*` — карьерные тесты, вопросы и результаты

- Хранилище (`config/filesystems.php`):
  - `public` локально маппится на `APP_URL/storage`
  - `s3` доступно при заполнении `AWS_*`

- CORS (`config/cors.php`):
  - Белые списки локальных и облачных доменов фронта (Vercel/ngrok)
  - `supports_credentials=true` для работы с куками

## Взаимодействие компонентов (общее)

- Фронтенд запрашивает `GET /api/v1/lessons/{id}` → `LessonController@show` возвращает `lesson`, `video_url`, `subtitles`, `fullText`, `gesture_data`
- Если есть `LessonMedia` с типом `video`, используется `defaultVideo->full_url` или первый `videos`
- VTT парсится из `lesson_media` (`subtitles`), из `gesture_data['subtitles_url']` или генерируется из `LessonText`
- Прогресс сохраняется через `ProgressController` (позиция, длительность, статус)

## Примеры запросов

- Получить урок: `GET /api/v1/lessons/{id}`
- Сохранить прогресс: `POST /api/v1/progress/save` `{ lesson_id, course_id, status, last_position_seconds, watched_duration_seconds }`
- Загрузить видео (админ): `POST /api/v1/admin/media/upload-video` multipart: `{ lesson_id, file }`

Больше примеров и маршрутов — в `docs/ROUTES.md`.

## Точки расширения

- Поддержка HLS/DASH с генерацией плейлистов при загрузке видео
- Расширение карьерных тестов (шкалы, сторонние провайдеры оценок)
- Вебхуки при завершении урока/курса
- Мульти-язычные субтитры и автоматическое распознавание

## Лицензия

Внутренний проект. Распространение за пределы компании не допускается без разрешения.
