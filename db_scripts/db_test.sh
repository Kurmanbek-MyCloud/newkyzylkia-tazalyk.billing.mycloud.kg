#!/bin/bash
# ============================================================
# db_test.sh — Проверка целостности БД после очистки
# ============================================================
# Локально:   bash db_test.sh --config config.local.sh
# На сервере: bash db_test.sh --config config.server.sh
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONFIG_FILE="config.local.sh"

while [[ $# -gt 0 ]]; do
    case $1 in
        --config) CONFIG_FILE="$2"; shift ;;
        *) echo "Неизвестный параметр: $1"; exit 1 ;;
    esac
    shift
done

CONFIG="$SCRIPT_DIR/$CONFIG_FILE"

if [ ! -f "$CONFIG" ]; then
    echo "Файл конфига не найден: $CONFIG"
    exit 1
fi

source "$CONFIG"

run_sql() {
    if [ "$DOCKER" = true ]; then
        docker exec "$DOCKER_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "$1" 2>/dev/null
    else
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "$1" 2>/dev/null
    fi
}

PASS=0
FAIL=0
WARN=0

check_pass() {
    echo "  [OK] $1"
    PASS=$((PASS+1))
}

check_fail() {
    echo "  [FAIL] $1"
    FAIL=$((FAIL+1))
}

check_warn() {
    echo "  [WARN] $1"
    WARN=$((WARN+1))
}

echo ""
echo "============================================================"
echo "  Тестирование БД: $DB_NAME"
echo "============================================================"
echo ""

# ===== 1. Подключение к БД =====
echo "--- 1. Подключение к БД ---"
DB_CHECK=$(run_sql "SELECT 1")
if [ "$DB_CHECK" = "1" ]; then
    check_pass "Подключение к БД работает"
else
    check_fail "Не удалось подключиться к БД"
    echo ""
    echo "Тестирование прервано — нет подключения."
    exit 1
fi

# ===== 2. Основные таблицы существуют и содержат данные =====
echo ""
echo "--- 2. Основные таблицы (должны содержать данные) ---"

TABLES="vtiger_estates vtiger_invoice vtiger_payments vtiger_meters vtiger_readings vtiger_contactdetails vtiger_users vtiger_crmentity"

for TBL in $TABLES; do
    CNT=$(run_sql "SELECT COUNT(*) FROM $TBL" 2>/dev/null)
    if [ -z "$CNT" ]; then
        check_fail "$TBL — таблица не найдена!"
    elif [ "$CNT" = "0" ]; then
        check_warn "$TBL — пустая (0 записей)"
    else
        check_pass "$TBL — $CNT записей"
    fi
done

# ===== 3. CF-таблицы совпадают с основными =====
echo ""
echo "--- 3. Целостность CF-таблиц (должны совпадать с основными) ---"

check_cf() {
    local MAIN_TBL=$1
    local CF_TBL=$2
    local KEY=$3

    MAIN_CNT=$(run_sql "SELECT COUNT(*) FROM $MAIN_TBL")
    CF_CNT=$(run_sql "SELECT COUNT(*) FROM $CF_TBL")
    ORPHANS=$(run_sql "SELECT COUNT(*) FROM $CF_TBL WHERE $KEY NOT IN (SELECT $KEY FROM $MAIN_TBL)")

    if [ "$MAIN_CNT" = "$CF_CNT" ] && [ "$ORPHANS" = "0" ]; then
        check_pass "$CF_TBL = $MAIN_TBL ($MAIN_CNT записей, 0 сирот)"
    else
        check_fail "$CF_TBL: $CF_CNT vs $MAIN_TBL: $MAIN_CNT (сирот: $ORPHANS)"
    fi
}

check_cf vtiger_estates vtiger_estatescf estatesid
check_cf vtiger_payments vtiger_paymentscf paymentsid
check_cf vtiger_meters vtiger_meterscf metersid
check_cf vtiger_readings vtiger_readingscf readingsid
check_cf vtiger_invoice vtiger_invoicecf invoiceid

# ===== 4. Нет висячих записей =====
echo ""
echo "--- 4. Висячие записи (должно быть 0) ---"

INV_ORPHANS=$(run_sql "SELECT COUNT(*) FROM vtiger_inventorychargesrel WHERE recordid NOT IN (SELECT invoiceid FROM vtiger_invoice)")
if [ "$INV_ORPHANS" = "0" ]; then
    check_pass "vtiger_inventorychargesrel — 0 висячих"
else
    check_fail "vtiger_inventorychargesrel — $INV_ORPHANS висячих записей"
fi

PROD_ORPHANS=$(run_sql "SELECT COUNT(*) FROM vtiger_inventoryproductrel WHERE id NOT IN (SELECT invoiceid FROM vtiger_invoice)")
if [ "$PROD_ORPHANS" = "0" ]; then
    check_pass "vtiger_inventoryproductrel — 0 висячих"
else
    check_fail "vtiger_inventoryproductrel — $PROD_ORPHANS висячих записей"
fi

# ===== 5. Мусорные таблицы удалены =====
echo ""
echo "--- 5. Мусорные таблицы (должны быть удалены) ---"

JUNK_TABLES="vtiger_import_55 bot_auth temp_table_invoice test_temporary_invoice_3704"

for TBL in $JUNK_TABLES; do
    EXISTS=$(run_sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='$TBL'")
    if [ "$EXISTS" = "0" ]; then
        check_pass "$TBL — удалена"
    else
        check_warn "$TBL — всё ещё существует"
    fi
done

# ===== 6. Мусорные таблицы очищены =====
echo ""
echo "--- 6. Очищенные таблицы (должны быть пустыми) ---"

CLEAN_TABLES="vtiger_modtracker_detail vtiger_modtracker_basic vtiger_modtracker_relations com_vtiger_workflowtask_queue vtiger_asteriskextensions vtiger_loginhistory"

for TBL in $CLEAN_TABLES; do
    CNT=$(run_sql "SELECT COUNT(*) FROM $TBL" 2>/dev/null)
    if [ -z "$CNT" ]; then
        check_fail "$TBL — таблица не найдена!"
    elif [ "$CNT" = "0" ]; then
        check_pass "$TBL — пустая"
    else
        check_warn "$TBL — $CNT записей (ожидалось 0)"
    fi
done

# ===== 7. Миграции применены =====
echo ""
echo "--- 7. Миграции схемы ---"

PREV_COL=$(run_sql "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='vtiger_inventoryproductrel' AND COLUMN_NAME='prev_reading_id'")
CUR_COL=$(run_sql "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='vtiger_inventoryproductrel' AND COLUMN_NAME='cur_reading_id'")

if [ "$PREV_COL" = "1" ] && [ "$CUR_COL" = "1" ]; then
    check_pass "vtiger_inventoryproductrel: prev_reading_id, cur_reading_id — есть"
else
    check_fail "vtiger_inventoryproductrel: столбцы prev_reading_id/cur_reading_id не найдены"
fi

# ===== 8. Размер БД =====
echo ""
echo "--- 8. Размер БД ---"
SIZE=$(run_sql "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) FROM information_schema.tables WHERE table_schema = '$DB_NAME'")
echo "  Размер: ${SIZE} MB"

# ===== Итоги =====
echo ""
echo "============================================================"
echo "  Результаты: $PASS пройдено, $FAIL ошибок, $WARN предупреждений"
if [ "$FAIL" = "0" ]; then
    echo "  Всё в порядке!"
else
    echo "  Есть проблемы — проверь ошибки выше"
fi
echo "============================================================"
echo ""
