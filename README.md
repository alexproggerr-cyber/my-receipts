# Книга рецептов (PHP + SQLite)

Сайт с рецептами и админкой для их добавления.

## Запуск на компьютере

Дважды щёлкните `start.bat` и откройте http://localhost:8000.

- Админка: http://localhost:8000/admin/
- При первом входе в админку вы сами придумываете логин и пароль администратора.

## Что умеет

**Сайт:** список рецептов карточками, поиск по названию и ингредиентам, фильтр по категориям, страница рецепта с ингредиентами и пошаговым приготовлением.

**Админка:** вход по паролю, добавление / редактирование / удаление рецептов, загрузка фото (JPG, PNG, WEBP, GIF до 5 МБ).
Раздел «Категории»: добавление, переименование и удаление категорий (рецепты при удалении категории остаются,
просто без категории). Новую категорию можно создать и прямо из формы рецепта.

## Структура

```
index.php          — главная (список, поиск, категории)
recipe.php         — страница рецепта
admin/             — админка (login, index, edit, delete, logout)
includes/          — база данных, общие функции, шапка/подвал
assets/style.css   — стили
data/              — файл базы recipes.sqlite (создаётся сам)
uploads/           — загруженные фото
router.php         — защита служебных папок для встроенного сервера
```

## На хостинге

Нужен PHP 8.1+ с расширением `pdo_sqlite` (есть почти везде). Залейте файлы,
дайте права на запись папкам `data/` и `uploads/`. Для Apache уже лежат `.htaccess`,
закрывающие `data/` и `includes/`. Для nginx закройте их вручную (`location ~ ^/(data|includes)/ { deny all; }`).

## Kubernetes и CI/CD (GitHub Actions)

Настроено так же, как в проекте daily-questions.

Файлы: `Dockerfile`, `docker/apache-site.conf`, `.github/workflows/cicd.yml`, папка `k8s/`
(диск PVC, Deployment, Service типа NodePort). Всё разворачивается в namespace `my-apps`.

На каждый push в `master` workflow:
1. проверяет синтаксис всех PHP-файлов;
2. собирает Docker-образ и публикует его в GHCR:
   `ghcr.io/alexproggerr-cyber/my-receipts:<sha>` и `:latest`;
3. применяет `k8s/pvc.yaml`, `deployment.yaml`, `service.yaml` в `my-apps`,
   перезапускает deployment `my-receipts` и ждёт, пока под поднимется.

Секреты репозитория (Settings → Secrets and variables → Actions) — те же, что в daily-questions:

| Секрет | Зачем |
|---|---|
| `GHCR_TOKEN` | токен с правом `write:packages` для публикации образа |
| `KUBECONFIG` | содержимое kubeconfig с доступом к кластеру |

В кластере уже должны быть namespace `my-apps` и секрет `ghcr-secret` для скачивания образов из GHCR
(они есть, если daily-questions там работает).

Если репозиторий называется не `my-receipts`, поправьте `image:` в `k8s/deployment.yaml`.

Открыть сайт: узнайте порт `kubectl -n my-apps get svc my-receipts` и зайдите на `http://<IP-узла>:<NodePort>`,
либо `kubectl -n my-apps port-forward svc/my-receipts 8080:80` → http://localhost:8080

Важно: база SQLite — один файл на диске, поэтому под всегда один (`replicas: 1`).
Чтобы масштабировать на несколько подов, нужно перевести сайт на MySQL/PostgreSQL
и хранить фото в S3-совместимом хранилище.

Резервная копия базы:
`kubectl -n my-apps cp <имя-пода>:/var/www/html/data/recipes.sqlite ./backup.sqlite`

Забыли пароль — удалите строку из таблицы `users` (или весь файл `data/recipes.sqlite`,
но тогда пропадут и рецепты), и при следующем входе админка предложит создать администратора заново.
