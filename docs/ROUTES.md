# Маршруты API (v1)

Ниже перечислены основные маршруты, сгруппированные по областям. Для каждого указан метод, путь, краткая логика, данные и примеры.

Базовый префикс: `/api/v1`

## Общие

- `OPTIONS /{any}` — CORS preflight для любых путей внутри `v1`; зеркалит заголовки Origin/Requested-Headers

## Аутентификация

- `POST /auth/register` — регистрация пользователя; входные поля: `name`, `email`, `password`
- `POST /auth/login` — вход; куки Sanctum
- `POST /auth/logout` (auth) — выход
- `GET /auth/user` (auth) — профиль текущего пользователя

## Курсы

- `GET /courses` — список курсов с модулями и уроками
- `GET /courses/{id}` — детальная информация о курсе
- `GET /courses/enrolled` (auth) — список курсов, на которые зачислен пользователь, с прогрессом
- `POST /courses/{id}/enroll` (auth) — зачисление в курс (все курсы бесплатны)
- `GET /courses/{id}/access` (auth) — проверка доступа к курсу
- `GET /courses/{id}/progress` (auth) — прогресс по курсу
- `POST /courses/{courseId}/lessons/{lessonId}/complete` (auth) — отметить урок завершённым

Админ CRUD (auth):
- `POST /courses` — создать курс
- `PUT /courses/{id}` — обновить курс
- `DELETE /courses/{id}` — удалить курс

## Модули

- `GET /modules/{id}` — детальная информация о модуле
- `apiResource /modules` (auth) — полный CRUD
- `POST /admin/modules/reorder` (auth) — переупорядочить модули внутри курса
- `PATCH /admin/modules/{id}/toggle-publication` (auth) — переключить публикацию

## Уроки

- `GET /lessons/{id}` — получить урок: `lesson`, `video_url`, `subtitles`, `fullText`, `gesture_data`
- `GET /lessons/{id}/subtitles` — отдельно получить субтитры

Защищённые (auth):
- `apiResource /lessons` — CRUD (административное использование)
- `POST /lessons/{id}/complete` — отметить завершение
- `GET /lessons/{id}/progress` — получить прогресс
- `POST /lessons/{id}/progress` — сохранить прогресс (позиция, длительность)
- `POST /lessons/{id}/progress/sessions` — сохранить сессию просмотра

## Прогресс

- `GET /progress` (auth) — список записей прогресса
- `GET /progress/summary` (auth) — сводка по курсам/урокам
- `POST /progress` (auth) — создать/обновить запись
- `GET /progress/{id}` (auth) — получить запись
- `PUT /progress/{id}` (auth) — обновить запись
- `DELETE /progress/{id}` (auth) — удалить запись
- `GET /courses/{courseId}/stats` (auth) — статистика по курсу

## Карьерные тесты

- `GET /career-tests` — список тестов
- `GET /career-tests/{id}` — детальная информация о тесте
- `POST /career-tests/{id}/submit` (auth) — отправить ответы, сохранить результат
- `GET /career-tests/results` (auth) — список результатов пользователя
- `GET /career-tests/results/{id}` (auth) — детальный результат

## Хранилище (static serve)

- `GET /storage/lessons/models/{filename}` — GLB-модель (`model/gltf-binary`)
- `GET /storage/lessons/subtitles/{filename}` — VTT-субтитры (`text/vtt`)
- `GET /storage/lessons/videos/{filename}` — MP4-видео (`video/mp4`)

## Админ: медиа и статистика (auth)

- `GET /admin/courses` — курсы с вложенными модулями/уроками
- `POST /admin/courses` — создать курс
- `PUT /admin/courses/{id}` — обновить курс
- `DELETE /admin/courses/{id}` — удалить курс
- `PATCH /admin/courses/{id}/toggle-publication` — публикация
- `GET /admin/modules` — модули
- `POST /admin/modules` — создать модуль
- `PUT /admin/modules/{id}` — обновить модуль
- `DELETE /admin/modules/{id}` — удалить модуль
- `POST /admin/modules/reorder` — порядок модулей
- `PATCH /admin/modules/{id}/toggle-publication` — публикация
- `GET /admin/lessons` — уроки
- `POST /admin/lessons` — создать урок
- `PUT /admin/lessons/{id}` — обновить урок
- `DELETE /admin/lessons/{id}` — удалить урок
- `POST /admin/lessons/reorder` — порядок уроков
- `PATCH /admin/lessons/{id}/toggle-publication` — публикация
- `GET /admin/stats` — сводная статистика
- `POST /admin/upload/video` — загрузка видео (multipart: `lesson_id`, `file`)
- `POST /admin/upload/3d-model` — загрузка GLB-модели (multipart: `lesson_id`, `file`)
- `POST /admin/upload/subtitles` — загрузка VTT (multipart: `lesson_id`, `file`)

## Жесты (заглушка)

- `POST /gesture/recognize` (auth) — заглушка; возвращает `{ success, message }`

## Примеры ответов

### GET /lessons/{id}

```json
{
  "success": true,
  "data": {
    "lesson": { "id": 123, "title": "…", "module": { … } },
    "subtitles": [ { "id": "1", "start": 0.0, "end": 2.5, "text": "…" } ],
    "fullText": "Полный текст урока",
    "gesture_data": { "subtitles_url": "/storage/lessons/subtitles/…" },
    "video_url": "https://…/storage/lessons/videos/…"
  }
}
```

### POST /lessons/{id}/progress

```json
{
  "lesson_id": 123,
  "course_id": 45,
  "status": "in_progress",
  "last_position_seconds": 82,
  "watched_duration_seconds": 300
}
```

Возвращает: `{ "success": true }`

