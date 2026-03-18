# Локальное развертывание 

Инструкция по развертыванию проекта из Git репозитория.

#### **До того как начать развертывать сделайте это:**

1) Добавьте в проект "vendor" из сервера (оригинал проект)
2) Добавьте в проект "user_privileges" из сервера (оригинал проект)
3) Снять дамп из сервера через команду: mysqldump -u username_db -p name_db > name_db_date_backup.sql
4) Создать в локальном проекте папку db_dump
5) Дамп снятый из сервера положить в db_dump (локальный проект)
6) Далее уже по инструкции снизу.

## Требования

- Docker Desktop (Mac/Windows) или Docker + Docker Compose (Linux)
- Дамп базы данных (`.sql` файл)
- Папка vendor
- Папка user_privileges

## Быстрый старт

### 1. Клонирование репозитория

```bash
git clone <repository-url>
cd elayum-schools.educrm.mycloud.kg
```

### 2. Создание необходимых директорий

```bash
mkdir -p cache/images cache/import cache/upload storage logs user_privileges
```

### 3. Копирование дампа базы данных

```bash
mkdir -p db_dump
cp /path/to/your/dump.sql db_dump/
```

### 4. Создание config.inc.php

Создайте файл `config.inc.php` в корне проекта:

```php
<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * (License); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

// Suppress PHP 8.1 deprecated warnings for legacy code compatibility
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

include('vtigerversion.php');

ini_set('memory_limit','256M');

$HELPDESK_SUPPORT_EMAIL_ID = 'support@example.com';
$HELPDESK_SUPPORT_NAME = 'Support';
$HELPDESK_SUPPORT_EMAIL_REPLY_ID = $HELPDESK_SUPPORT_EMAIL_ID;

$dbconfig['db_server'] = 'db';
$dbconfig['db_port'] = ':3306';
$dbconfig['db_username'] = 'root';
$dbconfig['db_password'] = 'root';
$dbconfig['db_name'] = 'elayum_billing_db';
$dbconfig['db_type'] = 'mysqli';
$dbconfig['db_status'] = 'true';
$dbconfig['db_hostname'] = $dbconfig['db_server'].$dbconfig['db_port'];
$dbconfig['log_sql'] = false;

$dbconfigoption['persistent'] = true;
$dbconfigoption['autofree'] = false;
$dbconfigoption['debug'] = 0;
$dbconfigoption['seqname_format'] = '%s_seq';
$dbconfigoption['portability'] = 0;
$dbconfigoption['ssl'] = false;

$host_name = $dbconfig['db_hostname'];
$site_URL = 'http://localhost:8001/';
$root_directory = '/var/www/html/';
$cache_dir = 'cache/';
$tmp_dir = 'cache/images/';
$import_dir = 'cache/import/';
$upload_dir = 'cache/upload/';
$upload_maxsize = 52428800;
$allow_exports = 'all';
$upload_badext = array('php', 'php3', 'php4', 'php5', 'pl', 'cgi', 'py', 'asp', 'cfm', 'js', 'vbs', 'html', 'htm', 'exe', 'bin', 'bat', 'sh', 'dll', 'phps', 'phtml', 'xhtml', 'rb', 'msi', 'jsp', 'shtml', 'sth', 'shtm');
$list_max_entries_per_page = '20';
$history_max_viewed = '5';
$default_action = 'index';
$default_theme = 'softed';
$default_user_name = '';
$default_password = '';
$create_default_user = false;
$currency_name = 'Kyrgyz Som';
$default_charset = 'UTF-8';
$default_language = 'ru_ru';
$display_empty_home_blocks = false;
$disable_stats_tracking = false;
$application_unique_key = 'elayum_schools_educrm_2024';
$listview_max_textlength = 40;
$php_max_execution_time = 0;
$max_mailboxes = 3;
$default_timezone = 'Asia/Bishkek';

if(isset($default_timezone) && function_exists('date_default_timezone_set')) {
	@date_default_timezone_set($default_timezone);
}

$default_layout = 'v7';

include_once 'config.security.php';
```

### 5. Запуск Docker

```bash
docker-compose up -d --build
```

### 6. Импорт базы данных

База данных импортируется автоматически при первом запуске из папки `db_dump/`.

Если база не импортировалась, выполните вручную:

```bash
cat db_dump/your_dump.sql | docker-compose exec -T db mysql -u root -proot elayum_billing_db
```

### 7. Установка прав доступа

```bash
docker-compose exec web chown -R www-data:www-data /var/www/html/cache /var/www/html/storage /var/www/html/logs /var/www/html/user_privileges
```

### 8. Проверка

Откройте в браузере: **http://localhost:8001**

---

## Проблемы и решения

### 1. "config.inc.php not found"

**Причина:** Файл конфигурации отсутствует.

**Решение:** Создайте `config.inc.php` согласно шагу 4.

---

### 2. Deprecated warnings в PHP 8.1

**Причина:** Старый код несовместим с PHP 8.1.

**Решение:** Добавьте в начало `config.inc.php`:

```php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
```

---

### 3. "Sorry! Attempt to access restricted file."

**Причина:** Отсутствуют файлы привилегий пользователей.

**Решение:**

1. Узнайте ID пользователей:

```bash
docker-compose exec db mysql -u root -proot elayum_billing_db -e "SELECT id, user_name FROM vtiger_users;"
```

2. Создайте файлы `user_privileges/user_privileges_{ID}.php` и `user_privileges/sharing_privileges_{ID}.php` для каждого пользователя.

---

### 4. Контейнер с именем уже существует

**Симптом:** `The container name "/billing_db" is already in use`

**Решение:**

```bash
docker rm -f billing_db billing_web
docker-compose up -d --build
```

Или полный сброс:

```bash
docker-compose down -v
docker-compose up -d --build
```

---

### 5. База данных пустая после запуска

**Причина:** Дамп не импортировался автоматически.

**Решение:** Импортируйте вручную:

```bash
cat db_dump/your_dump.sql | docker-compose exec -T db mysql -u root -proot elayum_billing_db
```

---

### 6. Mac M1/M2/M3 — контейнер db не запускается

**Причина:** MariaDB образ не поддерживает ARM по умолчанию.

**Решение:** Убедитесь что в `docker-compose.yml` есть:

```yaml
platform: linux/arm64
```

Для Intel/AMD эту строку нужно удалить.

---

## Полезные команды

```bash
# Запуск
docker-compose up -d

# Остановка
docker-compose down

# Пересборка
docker-compose up -d --build

# Логи веб-сервера
docker-compose logs -f web

# Логи БД
docker-compose logs -f db

# Подключение к MySQL
docker-compose exec db mysql -u root -proot elayum_billing_db

# Войти в контейнер PHP
docker-compose exec web bash

# Полный сброс (удаление данных БД)
docker-compose down -v
```

---

## Структура проекта

```
elayum-schools.educrm.mycloud.kg/
├── Dockerfile
├── docker-compose.yml
├── config.inc.php              # Создать!
├── config.php
├── config.security.php
├── includes/
│   └── runtime/
│       └── cache/
│           └── Connector.php
├── db_dump/
│   └── *.sql                   # Дамп БД
├── user_privileges/            # Создать!
│   ├── user_privileges_1.php
│   ├── sharing_privileges_1.php
│   └── ...
├── cache/                      # Создать!
├── storage/                    # Создать!
└── logs/                       # Создать!
```

---

## Доступы

| Ресурс                | URL/Порт          |
| --------------------------- | --------------------- |
| Веб-интерфейс   | http://localhost:8001 |
| MySQL (извне)          | localhost:3308        |
| MySQL (внутри Docker) | db:3306               |

**MySQL credentials:**

- User: `root`
- Password: `root`
- Database: `elayum_billing_db`
