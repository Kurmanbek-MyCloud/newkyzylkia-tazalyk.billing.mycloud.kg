# ВАЖНО: Правила работы с проектом

## Что НЕЛЬЗЯ делать

### 1. НЕ обновлять PHP выше 8.1
Проект основан на vtiger CRM, который написан под PHP 5-7. На PHP 8.1 работает с подавлением warnings. На PHP 8.2+ часть warnings станет fatal errors — система упадёт.

### 2. НЕ делать `composer update`
Команда `composer update` обновит ВСЕ пакеты до последних минорных версий. Это может сломать совместимость. Используй только:
- `composer install` — безопасно, ставит ровно то что в `composer.lock`
- `composer require пакет/имя` — безопасно, добавляет новый пакет

### 3. НЕ добавлять `adodb` в composer.json
vtiger использует собственную версию adodb из `libraries/adodb_vtigerfix/`. Установка `adodb/adodb-php` через composer вызовет конфликт классов и падение соединения с БД.

### 4. НЕ менять `error_reporting` в config.inc.php
Текущая настройка:
```php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
```
Если включить `E_ALL` или `display_errors = 1` — PHP warnings от legacy-кода vtiger попадут в HTTP-ответ, переполнят буфер nginx и вызовут **502 Bad Gateway**. Особенно для пользователей с ролями отличными от admin (id=1).

### 5. НЕ удалять папку `test/logo/`
В ней хранится логотип системы (`oimo_billing_logo.png`). vtiger загружает его по пути `test/logo/`. Без неё логотип в шапке сайта не отображается.

### 6. НЕ удалять/менять `vendor/` и `user_privileges/`
- `vendor/` — зависимости PHP (не в git, копировать с сервера или `composer install`)
- `user_privileges/` — права пользователей (не в git, копировать с сервера)

## Что НУЖНО делать после поднятия нового инстанса

### 1. Запустить скрипт очистки БД
```bash
cd db_scripts
bash db_cleanup.sh --config config.local.sh    # локально (Docker)
bash db_cleanup.sh --config config.server.sh   # на сервере
```
Скрипт:
- Очищает мусорные таблицы (modtracker, workflow queue, login history)
- Удаляет неиспользуемые таблицы (bot_auth, temp_table_invoice и др.)
- Удаляет висячие записи из vtiger_inventorychargesrel
- Добавляет необходимые столбцы (prev_reading_id, cur_reading_id)
- Освобождает ~800 MB дискового пространства

### 2. Убедиться что `vendor/` и `user_privileges/` на месте
Если нет — скопировать с продакшн-сервера или:
```bash
composer install   # для vendor/
```

## Docker

- Web: `1_billing_web` (порт 8002 → 80)
- DB: `1_billing_mycloud_db` (порт 3309 → 3306)
- DB credentials: root / root
- DB name: `1_billing_mycloud_db`
- Путь внутри контейнера: `/var/www/html/`

## Конфигурация

- `config.inc.php` — основной конфиг (БД, URL, пути)
- `config.security.php` — настройки безопасности
- Часовой пояс: Asia/Bishkek
- Валюта: Кыргызский сом
- Язык: ru_ru
