# Redis Invoice System — Документация

Система асинхронной генерации счетов через Redis очередь.
Каждое МП (Муниципальное Предприятие) имеет свой воркер, хендлер и профиль.

---

## Структура папок

```
redis-invoices/
├── config.php                  # Redis конфиг (хост, порт)
├── invoiceModal.php            # Создание задач в очередь (UI)
│
├── workers/                    # Воркеры — долгоживущие процессы
│   ├── worker_58.php           # МП 58: Тамчы ТазаСуу
│   └── worker_59.php           # МП 59: Таза Аймак
│
├── mp_handlers/                # Логика генерации счетов
│   ├── tamchy_tazasuu.php      # Хендлер МП 58
│   └── tamchy_taza_aimak.php   # Хендлер МП 59
│
├── profiles/                   # JSON-конфиги МП
│   ├── tamchy_tazasuu.json     # Профиль МП 58
│   └── tamchy_taza_aimak.json  # Профиль МП 59
│
├── profile_manager/            # UI управления профилями
│   ├── index.php
│   └── api.php
│
└── page_status/                # Дашборд статусов задач
    ├── tabs_dashboard.php
    └── api.php
```

---

## Как работает система

```
[Пользователь нажимает "Сгенерировать"]
        ↓
[invoiceModal.php] → пушит задачу в Redis очередь
        ↓
[worker_XX.php] — бесконечный цикл, забирает задачи из очереди
        ↓
[mp_handlers/handler.php] — создаёт счет в vtiger
        ↓
Успех → очередь success
Ошибка → очередь retry (макс 1 повтор) → очередь errors
```

### Ключи Redis (шаблон: `task:invoice:{тип}:mp{id}`)

| Ключ | Описание |
|------|----------|
| `task:invoice:queue:mp58` | Основная очередь МП 58 |
| `task:invoice:scheduled:mp58` | Запланированные задачи |
| `task:invoice:retry:mp58` | На повтор (через 30 сек) |
| `task:invoice:success:mp58` | Успешно выполненные |
| `task:invoice:errors:mp58` | Ошибки |
| `task:invoice:progress:mp58` | Прогресс текущей генерации |

---

## Добавление нового МП — пошаговая инструкция

### Что нужно знать заранее

- **ID группы МП** — из таблицы `vtiger_groups` в БД
- **ID услуг** — из vtiger (модуль Services)
- **Тип расчёта** для каждой услуги: `fixed`, `by_meters`, `by_residents`, `by_area`
- **Серверный путь** к проекту (например `/var/www/dev.1.billing.mycloud.kg`)

---

### Шаг 1 — Создать профиль JSON

Создай файл `redis-invoices/profiles/{название_мп}.json`:

```json
{
  "name": "Название МП",
  "group_id": 60,
  "account_filter_id": null,
  "reading_period": "last_two",
  "handler_file": "mp_handlers/{название_мп}.php",
  "handler_function": "processНазваниеМПInvoice",
  "services": {
    "12345": { "calc": "by_meters",   "label": "Водоснабжение" },
    "12346": { "calc": "by_residents","label": "Вывоз мусора" },
    "12347": { "calc": "fixed",       "label": "Обслуживание" }
  }
}
```

**Поля:**

| Поле | Тип | Описание |
|------|-----|----------|
| `name` | string | Отображаемое название |
| `group_id` | int | ID группы МП из `vtiger_groups` |
| `account_filter_id` | int\|null | Фильтр по счёту. `null` = без фильтра |
| `reading_period` | string | `"last_two"` или `"next_month"` |
| `services` | object | Ключ = ID услуги в vtiger |

**Типы расчёта (`calc`):**

| Значение | Описание |
|----------|----------|
| `fixed` | Фиксированная сумма |
| `by_meters` | По показаниям счётчика |
| `by_residents` | Сумма × кол-во жильцов |
| `by_area` | Сумма × площадь объекта |

**`reading_period`:**

| Значение | Когда использовать |
|----------|-------------------|
| `last_two` | Берёт последние 2 показания (текущее и предыдущее) |
| `next_month` | Показания следующего месяца (закрытие периода) |

---

### Шаг 2 — Создать хендлер

Создай файл `redis-invoices/mp_handlers/{название_мп}.php`.
Скопируй существующий хендлер как основу:

```bash
cp mp_handlers/tamchy_taza_aimak.php mp_handlers/{название_мп}.php
```

**Измени в файле 3 вещи:**

```php
// 1. Имя функции
function processНазваниеМПInvoice($task, $logger) {

// 2. Путь к профилю
$profile = json_decode(file_get_contents(
    __DIR__ . '/../profiles/{название_мп}.json'
), true);

// 3. Логи — префикс для удобства поиска
$logger->log("[МП60] Начало обработки объекта {$task['objectID']}");
```

**Сигнатура функции — строго такая:**

```php
function processНазваниеМПInvoice(array $task, $logger): array {
    // ... логика ...

    // Обязательно вернуть один из вариантов:
    return ['success' => true,  'invoiceId' => '123x456'];
    return ['success' => false, 'error' => 'Описание ошибки'];
}
```

**Структура `$task`:**

```php
$task = [
    'objectID'      => 18232,           // ID объекта (estates) в vtiger
    'months'        => ['Январь'],      // Список месяцев для генерации
    'year'          => 2025,            // Год
    'entityTypes'   => ['Estates'],
    'retryCount'    => 0,               // Сколько раз уже повторяли
    'scheduledTime' => null,            // Дата/время если задача запланирована
];
```

---

### Шаг 3 — Создать воркер

Создай файл `redis-invoices/workers/worker_60.php`:

```php
<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '1024M');

// ===== НАСТРОЙКИ ВОРКЕРА =====
$MP_ID = 60;  // <-- ID группы МП
$HANDLER_FILE = '/var/www/dev.1.billing.mycloud.kg/redis-invoices/mp_handlers/{название_мп}.php';
$HANDLER_FUNCTION = 'processНазваниеМПInvoice';
// =============================

// Дальше код не менять — он одинаковый для всех воркеров.
// Скопируй всё остальное из worker_58.php начиная со строки:
// "Воркер для обработки задач генерации счетов из Redis очереди"
```

> **Важно:** в `$HANDLER_FILE` укажи **абсолютный** серверный путь.

---

### Шаг 4 — Запустить воркер

Воркер — это долгоживущий PHP процесс. Запускать вручную:

```bash
# Запуск в фоне с логами
nohup php /var/www/dev.1.billing.mycloud.kg/redis-invoices/workers/worker_60.php \
  > /var/www/dev.1.billing.mycloud.kg/redis-invoices/workers/worker_60.log 2>&1 &

# Проверить что запустился
ps aux | grep worker_60
```

Остановить воркер:
```bash
# Найти PID
ps aux | grep worker_60.php

# Остановить
kill {PID}
```

---

### Шаг 5 — Проверить через Profile Manager

1. Открой `/redis-invoices/profile_manager/` в браузере
2. Убедись что профиль нового МП появился в списке
3. Нажми **"Тестировать"** — введи ЛС объекта, месяц, год
4. Проверь что расчёт выполнился корректно (счёт НЕ создаётся при тесте)

---

## Чеклист при добавлении нового МП

```
[ ] Узнал ID группы МП в vtiger_groups
[ ] Узнал ID услуг в vtiger (Services)
[ ] Определил тип расчёта для каждой услуги
[ ] Создал profiles/{название}.json
[ ] Создал mp_handlers/{название}.php
[ ] Изменил имя функции в хендлере
[ ] Изменил путь к профилю в хендлере
[ ] Создал workers/worker_{id}.php
[ ] Указал правильный абсолютный путь в $HANDLER_FILE
[ ] Запустил воркер командой nohup
[ ] Проверил через Profile Manager (тестовый расчёт)
[ ] Проверил логи: workers/worker_{id}.log
```

---

## Частые ошибки

### Воркер не запускается
```bash
# Запусти в режиме отладки (без nohup) и смотри вывод
php redis-invoices/workers/worker_60.php
```

### Задачи зависают в очереди retry
- Смотри лог: `redis-invoices/workers/worker_60.log`
- Проверь что ID услуг в профиле правильные
- Проверь что объект привязан к нужной группе МП

### Хендлер не находит профиль
- Путь к профилю в хендлере должен быть относительно файла хендлера: `__DIR__ . '/../profiles/...'`

### "Объект не найден"
- Убедись что `group_id` в профиле совпадает с группой объекта в vtiger
- Проверь через Profile Manager — тест покажет детали объекта

---

## Мониторинг

**Дашборд задач:** `/redis-invoices/page_status/tabs_dashboard.php`

**Просмотр очередей напрямую:**
```bash
# Все ключи Redis
redis-cli keys "task:invoice:*:mp60"

# Длина очереди
redis-cli llen task:invoice:queue:mp60

# Последняя ошибка
redis-cli lrange task:invoice:errors:mp60 0 0
```

**Логи воркера:**
```bash
tail -f redis-invoices/workers/worker_60.log
```
