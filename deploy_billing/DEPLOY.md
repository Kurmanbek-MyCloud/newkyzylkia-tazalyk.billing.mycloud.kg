# Развёртывание биллинга MyCloud.kg

## Содержимое папки

```
deploy_billing/
├── DEPLOY.md   — эта инструкция
└── dump.sql    — чистая база данных (шаблон)
```

---

## 1. Клонирование репозитория

```bash
git clone <repo_url> /var/www/dev.megabilling.mycloud.kg
cd /var/www/dev.megabilling.mycloud.kg
```

---

## 2. Установка PHP зависимостей

```bash
composer install --no-dev
```

> `--no-dev` — не устанавливает тестовые пакеты, только продакшн зависимости.
> Версии берутся из `composer.lock` — всегда точные, без сюрпризов.

---

## 3. Создание директорий

```bash
mkdir -p cache/images cache/import cache/upload storage logs user_privileges
```

Также нужно скопировать `user_privileges/` с боевого сервера (содержит права пользователей vtiger, не хранится в git).

---

## 4. Конфигурация

Создай файл `config.inc.php` на основе шаблона ниже:

```php
<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

include('vtigerversion.php');

ini_set('memory_limit', '256M');

$HELPDESK_SUPPORT_EMAIL_ID    = 'support@example.com';
$HELPDESK_SUPPORT_NAME        = 'Support';
$HELPDESK_SUPPORT_EMAIL_REPLY_ID = $HELPDESK_SUPPORT_EMAIL_ID;

// === НАСТРОЙКИ БД ===
$dbconfig['db_server']   = 'localhost';        // хост БД (для Docker: 'db')
$dbconfig['db_port']     = ':3306';
$dbconfig['db_username'] = 'YOUR_DB_USER';
$dbconfig['db_password'] = 'YOUR_DB_PASSWORD';
$dbconfig['db_name']     = 'YOUR_DB_NAME';
$dbconfig['db_type']     = 'mysqli';
$dbconfig['db_status']   = 'true';

$dbconfig['db_hostname'] = $dbconfig['db_server'] . $dbconfig['db_port'];
$dbconfig['log_sql']     = false;

$dbconfigoption['persistent']     = true;
$dbconfigoption['autofree']       = false;
$dbconfigoption['debug']          = 0;
$dbconfigoption['seqname_format'] = '%s_seq';
$dbconfigoption['portability']    = 0;
$dbconfigoption['ssl']            = false;

$host_name = $dbconfig['db_hostname'];

// === НАСТРОЙКИ САЙТА ===
$site_URL        = 'https://YOUR_DOMAIN/';    // с трейлинг-слешем!
$root_directory  = '/var/www/YOUR_PROJECT/';  // с трейлинг-слешем!

// Пути к кешу
$cache_dir  = 'cache/';
$tmp_dir    = 'cache/images/';
$import_dir = 'cache/import/';
$upload_dir = 'cache/upload/';

$upload_maxsize = 52428800;
$allow_exports  = 'all';
$upload_badext  = array('php','php3','php4','php5','pl','cgi','py','asp','cfm','js','vbs','html','htm','exe','bin','bat','sh','dll','phps','phtml','xhtml','rb','msi','jsp','shtml','sth','shtm');

$list_max_entries_per_page = '20';
$history_max_viewed        = '5';
$default_action            = 'index';
$default_theme             = 'softed';
$default_user_name         = '';
$default_password          = '';
$create_default_user       = false;

$currency_name    = 'Kyrgyz Som';
$default_charset  = 'UTF-8';
$default_language = 'ru_ru';

$display_empty_home_blocks = false;
$disable_stats_tracking    = false;

$application_unique_key = 'elayum_schools_educrm_2024';

$listview_max_textlength = 40;
$php_max_execution_time  = 0;
$max_mailboxes           = 3;
$default_timezone        = 'Asia/Bishkek';

if (isset($default_timezone) && function_exists('date_default_timezone_set')) {
    @date_default_timezone_set($default_timezone);
}

$default_layout = 'v7';

include_once 'config.security.php';
```

---

## 5. Развёртывание базы данных

Создай базу данных, затем залей дамп:

```bash
# Создать базу
mysql -u root -p -e "CREATE DATABASE YOUR_DB_NAME CHARACTER SET utf8 COLLATE utf8_general_ci;"

# Залить дамп
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < deploy_billing/dump.sql
```

---

## 6. Очистка и миграции БД

После заливки дампа запусти скрипт очистки:

```bash
# Вписать реальные креды в db_scripts/config.server.sh, затем:
bash db_scripts/db_cleanup.sh --config db_scripts/config.server.sh
```

---

## 7. Проверка целостности

```bash
php db_scripts/test_integrity.php
```

Должно быть: **0 ошибок**.

---

## 8. Права доступа

```bash
chown -R www-data:www-data /var/www/dev.megabilling.mycloud.kg
chmod -R 755 /var/www/dev.megabilling.mycloud.kg
chmod -R 777 cache/ logs/ storage/
```

---

## Важно

- **Не делать** `composer update` — только `composer install`
- **Не менять** PHP выше 8.1
- **Не добавлять** `adodb/adodb-php` в composer.json
- Подробнее: `IMPORTANT_READ_FIRST.md`
