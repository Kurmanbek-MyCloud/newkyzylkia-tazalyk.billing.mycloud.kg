#!/bin/bash
# ============================================================
# db_cleanup.sh — Очистка мусора в базе данных vtiger CRM
# ============================================================
# Локально:   bash db_cleanup.sh --config config.local.sh
# На сервере: bash db_cleanup.sh --config config.server.sh
# ============================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONFIG_FILE="config.local.sh"  # по умолчанию — локальный

# Парсим аргументы
while [[ $# -gt 0 ]]; do
    case $1 in
        --config) CONFIG_FILE="$2"; shift ;;
        *) echo "Неизвестный параметр: $1"; exit 1 ;;
    esac
    shift
done

CONFIG="$SCRIPT_DIR/$CONFIG_FILE"

if [ ! -f "$CONFIG" ]; then
    echo "❌ Файл конфига не найден: $CONFIG"
    echo ""
    echo "Доступные конфиги:"
    echo "  --config config.local.sh   (локально через Docker)"
    echo "  --config config.server.sh  (боевой сервер)"
    exit 1
fi

source "$CONFIG"

# ===== Функция выполнения SQL =====
run_sql() {
    if [ "$DOCKER" = true ]; then
        docker exec "$DOCKER_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "$1" 2>/dev/null
    else
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "$1" 2>/dev/null
    fi
}

run_sql_silent() {
    if [ "$DOCKER" = true ]; then
        docker exec "$DOCKER_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1" 2>/dev/null
    else
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1" 2>/dev/null
    fi
}

echo ""
echo "============================================================"
echo "  Очистка базы данных: $DB_NAME"
if [ "$DOCKER" = true ]; then
    echo "  Режим: Docker ($DOCKER_CONTAINER)"
else
    echo "  Режим: Прямое подключение ($DB_HOST:$DB_PORT)"
fi
echo "============================================================"

# ===== Размер ДО =====
SIZE_BEFORE=$(run_sql "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) FROM information_schema.tables WHERE table_schema = '$DB_NAME'")
echo ""
echo "📊 Размер БД сейчас: ${SIZE_BEFORE} MB"
echo ""

# ===== Статистика перед очисткой =====
echo "--- Что будет очищено ---"
MOD_DETAIL=$(run_sql "SELECT COUNT(*) FROM vtiger_modtracker_detail")
MOD_BASIC=$(run_sql "SELECT COUNT(*) FROM vtiger_modtracker_basic")
MOD_RELATIONS=$(run_sql "SELECT COUNT(*) FROM vtiger_modtracker_relations")
ASTERISK=$(run_sql "SELECT COUNT(*) FROM vtiger_asteriskextensions")
WF_QUEUE=$(run_sql "SELECT COUNT(*) FROM com_vtiger_workflowtask_queue")
IMPORT_EXISTS=$(run_sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='vtiger_import_55'")
BOT_AUTH_EXISTS=$(run_sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='bot_auth'")
TEMP_INVOICE_EXISTS=$(run_sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='temp_table_invoice'")
TEST_INVOICE_EXISTS=$(run_sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='test_temporary_invoice_3704'")
INV_CHARGES_ORPHANS=$(run_sql "SELECT COUNT(*) FROM vtiger_inventorychargesrel WHERE recordid NOT IN (SELECT invoiceid FROM vtiger_invoice)")
LOGIN_HISTORY=$(run_sql "SELECT COUNT(*) FROM vtiger_loginhistory")

echo "  vtiger_modtracker_detail      : ${MOD_DETAIL} строк  (~799 MB)"
echo "  vtiger_modtracker_basic       : ${MOD_BASIC} строк  (~26 MB)"
echo "  vtiger_modtracker_relations   : ${MOD_RELATIONS} строк"
echo "  com_vtiger_workflowtask_queue : ${WF_QUEUE} строк  (~38 MB)"
if [ "$IMPORT_EXISTS" = "1" ]; then
    IMPORT=$(run_sql "SELECT COUNT(*) FROM vtiger_import_55")
    echo "  vtiger_import_55              : ${IMPORT} строк  (~3.6 MB)"
else
    echo "  vtiger_import_55              : таблица уже удалена"
fi
if [ "$BOT_AUTH_EXISTS" = "1" ]; then
    echo "  bot_auth                      : будет удалена (бот не используется)"
else
    echo "  bot_auth                      : таблица уже удалена"
fi
if [ "$TEMP_INVOICE_EXISTS" = "1" ]; then
    TEMP_INV=$(run_sql "SELECT COUNT(*) FROM temp_table_invoice")
    echo "  temp_table_invoice            : ${TEMP_INV} строк — будет удалена"
else
    echo "  temp_table_invoice            : таблица уже удалена"
fi
if [ "$TEST_INVOICE_EXISTS" = "1" ]; then
    echo "  test_temporary_invoice_3704   : будет удалена (тестовая таблица)"
else
    echo "  test_temporary_invoice_3704   : таблица уже удалена"
fi
echo "  vtiger_inventorychargesrel    : ${INV_CHARGES_ORPHANS} висячих записей — будут удалены"
echo "  vtiger_asteriskextensions     : ${ASTERISK} строк (IP-телефония не используется)"
echo "  vtiger_loginhistory           : ${LOGIN_HISTORY} строк"
echo ""

# --- Осиротевшие CF-записи ---
echo "--- Осиротевшие CF-записи (customfields без основной записи) ---"
CF_ESTATES=$(run_sql "SELECT COUNT(*) FROM vtiger_estatescf WHERE estatesid NOT IN (SELECT estatesid FROM vtiger_estates)")
CF_PAYMENTS=$(run_sql "SELECT COUNT(*) FROM vtiger_paymentscf WHERE paymentsid NOT IN (SELECT paymentsid FROM vtiger_payments)")
CF_METERS=$(run_sql "SELECT COUNT(*) FROM vtiger_meterscf WHERE metersid NOT IN (SELECT metersid FROM vtiger_meters)")
CF_READINGS=$(run_sql "SELECT COUNT(*) FROM vtiger_readingscf WHERE readingsid NOT IN (SELECT readingsid FROM vtiger_readings)")
echo "  vtiger_estatescf              : ${CF_ESTATES} осиротевших записей"
echo "  vtiger_paymentscf             : ${CF_PAYMENTS} осиротевших записей"
echo "  vtiger_meterscf               : ${CF_METERS} осиротевших записей"
echo "  vtiger_readingscf             : ${CF_READINGS} осиротевших записей"
CF_INVOICE=$(run_sql "SELECT COUNT(*) FROM vtiger_invoicecf WHERE invoiceid NOT IN (SELECT invoiceid FROM vtiger_invoice)")
CF_INVPRODUCTREL=$(run_sql "SELECT COUNT(*) FROM vtiger_inventoryproductrel WHERE id NOT IN (SELECT invoiceid FROM vtiger_invoice)")
echo "  vtiger_invoicecf              : ${CF_INVOICE} осиротевших записей"
echo "  vtiger_inventoryproductrel    : ${CF_INVPRODUCTREL} осиротевших записей"
echo ""

# --- Мусорные/тестовые записи ---
echo "--- Мусорные и тестовые записи ---"
ORPHAN_ESTATES_CRMENTITY=$(run_sql "SELECT COUNT(*) FROM vtiger_crmentity WHERE setype = 'Estates' AND deleted = 0 AND crmid NOT IN (SELECT estatesid FROM vtiger_estates)")
EMPTY_ESTATES=$(run_sql "SELECT COUNT(*) FROM vtiger_estates WHERE cf_municipal_enterprise = 0 AND (estate_number IS NULL OR estate_number = '')")
ALL_HELPDESK=$(run_sql "SELECT COUNT(*) FROM vtiger_crmentity WHERE setype = 'HelpDesk'")
ORPHAN_INVOICES=$(run_sql "SELECT COUNT(*) FROM vtiger_crmentity WHERE setype = 'Invoice' AND crmid NOT IN (SELECT invoiceid FROM vtiger_invoice)")
echo "  Сироты в crmentity (Estates)  : ${ORPHAN_ESTATES_CRMENTITY} записей (есть в crmentity, нет в vtiger_estates)"
echo "  Пустые Estates (group=0)      : ${EMPTY_ESTATES} записей (без номера, без группы)"
echo "  Все HelpDesk записи           : ${ALL_HELPDESK} записей (модуль не используется)"
echo "  Сироты Invoice в crmentity    : ${ORPHAN_INVOICES} записей (есть в crmentity, нет в vtiger_invoice)"
echo ""

# ===== Подтверждение =====
read -p "❓ Продолжить очистку? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "❌ Отменено."
    exit 0
fi

echo ""
echo "🔄 Начинаем очистку..."

STEP=1
TOTAL=20

echo -n "  [$STEP/$TOTAL] Очищаем vtiger_modtracker_detail... "
run_sql_silent "TRUNCATE TABLE vtiger_modtracker_detail"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Очищаем vtiger_modtracker_basic... "
run_sql_silent "TRUNCATE TABLE vtiger_modtracker_basic"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Очищаем vtiger_modtracker_relations... "
run_sql_silent "TRUNCATE TABLE vtiger_modtracker_relations"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Очищаем com_vtiger_workflowtask_queue... "
run_sql_silent "TRUNCATE TABLE com_vtiger_workflowtask_queue"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем vtiger_import_55... "
run_sql_silent "DROP TABLE IF EXISTS vtiger_import_55"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем bot_auth... "
run_sql_silent "DROP TABLE IF EXISTS bot_auth"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем temp_table_invoice... "
run_sql_silent "DROP TABLE IF EXISTS temp_table_invoice"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем test_temporary_invoice_3704... "
run_sql_silent "DROP TABLE IF EXISTS test_temporary_invoice_3704"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем висячие записи из vtiger_inventorychargesrel... "
run_sql_silent "DELETE FROM vtiger_inventorychargesrel WHERE recordid NOT IN (SELECT invoiceid FROM vtiger_invoice)"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Очищаем vtiger_asteriskextensions... "
run_sql_silent "TRUNCATE TABLE vtiger_asteriskextensions"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Очищаем vtiger_loginhistory... "
run_sql_silent "TRUNCATE TABLE vtiger_loginhistory"
echo "✅"; STEP=$((STEP+1))

# --- Очистка осиротевших CF-записей ---
echo ""
echo "  --- Очистка осиротевших CF-записей ---"

echo -n "  [$STEP/$TOTAL] Удаляем осиротевшие записи из vtiger_estatescf... "
run_sql_silent "DELETE FROM vtiger_estatescf WHERE estatesid NOT IN (SELECT estatesid FROM vtiger_estates)"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем осиротевшие записи из vtiger_paymentscf... "
run_sql_silent "DELETE FROM vtiger_paymentscf WHERE paymentsid NOT IN (SELECT paymentsid FROM vtiger_payments)"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем осиротевшие записи из vtiger_meterscf/readingscf... "
run_sql_silent "DELETE FROM vtiger_meterscf WHERE metersid NOT IN (SELECT metersid FROM vtiger_meters)"
run_sql_silent "DELETE FROM vtiger_readingscf WHERE readingsid NOT IN (SELECT readingsid FROM vtiger_readings)"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем осиротевшие записи из vtiger_invoicecf... "
run_sql_silent "DELETE FROM vtiger_invoicecf WHERE invoiceid NOT IN (SELECT invoiceid FROM vtiger_invoice)"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем осиротевшие записи из vtiger_inventoryproductrel... "
run_sql_silent "DELETE FROM vtiger_inventoryproductrel WHERE id NOT IN (SELECT invoiceid FROM vtiger_invoice)"
echo "✅"; STEP=$((STEP+1))

# --- Очистка мусорных/тестовых записей ---
echo ""
echo "  --- Очистка мусорных и тестовых записей ---"

echo -n "  [$STEP/$TOTAL] Удаляем сироты Estates из vtiger_crmentity... "
run_sql_silent "DELETE FROM vtiger_crmentity WHERE setype = 'Estates' AND deleted = 0 AND crmid NOT IN (SELECT estatesid FROM vtiger_estates)"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем пустые Estates (group=0, без номера)... "
run_sql_silent "DELETE vc, ve FROM vtiger_crmentity vc JOIN vtiger_estates ve ON ve.estatesid = vc.crmid WHERE ve.cf_municipal_enterprise = 0 AND (ve.estate_number IS NULL OR ve.estate_number = '')"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем все HelpDesk записи (модуль не используется)... "
run_sql_silent "DELETE FROM vtiger_crmentity WHERE setype = 'HelpDesk'"
echo "✅"; STEP=$((STEP+1))

echo -n "  [$STEP/$TOTAL] Удаляем сироты Invoice из vtiger_crmentity... "
run_sql_silent "DELETE FROM vtiger_crmentity WHERE setype = 'Invoice' AND crmid NOT IN (SELECT invoiceid FROM vtiger_invoice)"
echo "✅"

echo ""
echo -n "  OPTIMIZE TABLE (возврат дискового места)... "
run_sql_silent "OPTIMIZE TABLE vtiger_modtracker_detail"
run_sql_silent "OPTIMIZE TABLE vtiger_modtracker_basic"
run_sql_silent "OPTIMIZE TABLE com_vtiger_workflowtask_queue"
run_sql_silent "OPTIMIZE TABLE vtiger_inventorychargesrel"
echo "✅"

# ===== Миграции схемы БД =====
echo ""
echo "--- Миграции схемы ---"

echo -n "  vtiger_inventoryproductrel: проверяем prev_reading_id, cur_reading_id... "
run_sql_silent "ALTER TABLE vtiger_inventoryproductrel
  ADD COLUMN IF NOT EXISTS prev_reading_id INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS cur_reading_id INT(11) DEFAULT NULL"
echo "✅"

echo ""

# ===== Размер ПОСЛЕ =====
SIZE_AFTER=$(run_sql "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) FROM information_schema.tables WHERE table_schema = '$DB_NAME'")
SAVED=$(awk "BEGIN {printf \"%.1f\", $SIZE_BEFORE - $SIZE_AFTER}")

echo ""
echo "============================================================"
echo "  ✅ Очистка завершена!"
echo "  📊 До:           ${SIZE_BEFORE} MB"
echo "  📊 После:        ${SIZE_AFTER} MB"
echo "  💾 Освобождено:  ~${SAVED} MB"
echo "============================================================"
echo ""
