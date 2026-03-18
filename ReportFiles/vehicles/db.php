<?php
chdir('../../');
require_once 'include/database/PearDatabase.php';
require 'vendor/autoload.php';
global $adb;

function getCars($startDate = null, $endDate = null, $sortColumn, $sortOrder, $limit, $offset, $paid = true, $unpaid = true) {
    global $adb;



    $query = "SELECT vi.subject AS car_number, vp.amount,
                      CASE 
                          WHEN vi.invoicestatus = 'Paid' THEN 'Оплачен' 
                          ELSE 'Не оплачен' 
                      END AS invoicestatus,
                      CONCAT(vi.cf_check_in_date, ' ', vi.cf_check_in_time) AS check_in,
                      CONCAT(vi.cf_check_out_date, ' ', vi.cf_check_out_time) AS check_out,
                      CASE 
                          WHEN vi.cf_shed IS NULL OR vi.cf_shed = 0 THEN 'Нет' 
                          ELSE 'Да' 
                      END AS shed_status,
                      CASE 
                          WHEN vi.cf_seller IS NULL OR vi.cf_seller = 0 THEN 'Нет' 
                          ELSE 'Да' 
                      END AS seller_status
                  FROM vtiger_invoice vi 
                  INNER JOIN vtiger_crmentity vc ON vi.invoiceid = vc.crmid 
                  LEFT JOIN vtiger_payments vp ON vp.cf_paid_object = vi.invoiceid 
                  WHERE vc.deleted = 0";

    // Условия для фильтрации
    $params = [];
    if (!empty($startDate)) {
        $query .= " AND (TIMESTAMP(vi.cf_check_in_date, vi.cf_check_in_time) >= ?)";
        $params[] = $startDate . ' 00:00:00';
    }
    if (!empty($endDate)) {
        $query .= " AND (TIMESTAMP(vi.cf_check_in_date, vi.cf_check_in_time) <= ?)";
        $params[] = $endDate . ' 23:59:59';
        ;
    }

    // Условия для статуса оплаты
    if (!$paid && !$unpaid) {
        // Если оба фильтра отключены, ничего не показываем
        $query .= " AND 1 = 0"; // Эффективно ничего не возвращает
    } elseif ($paid && $unpaid) {
        // Если оба фильтра активны, ничего не добавляем
    } elseif (!$paid) {
        $query .= " AND vi.invoicestatus != 'Paid'"; // Показать только оплаченные
    } elseif (!$unpaid) {
        $query .= " AND vi.invoicestatus = 'Paid'"; // Показать только не оплаченные
    }

    // Логика сортировки
    $validSortColumns = [
        'check_in' => 'check_in',
        'check_out' => 'check_out',
        'car_number' => 'car_number',
        'invoicestatus' => 'invoicestatus',
        'shed_status' => 'shed_status',
        'seller_status' => 'seller_status',
        'amount' => 'amount',
    ];

    if (array_key_exists($sortColumn, $validSortColumns)) {
        $query .= " ORDER BY " . $validSortColumns[$sortColumn] . " $sortOrder";
    } else {
        $query .= " ORDER BY check_in DESC";
    }
    if ($limit !== null && $offset !== null) {
        // Лимит и смещение
        $query .= " LIMIT $limit OFFSET $offset";
    }
    // Выполнение запроса с параметрами
    $result = $adb->pquery($query, $params);

    $cars = [];
    foreach ($result as $res) {
        $cars[] = $res;
    }

    return ['cars' => $cars];
}

function countCars($startDate = null, $endDate = null, $paid, $unpaid) {
    global $adb;

    $query = "SELECT COUNT(*) as count, SUM(vp.amount) as total FROM vtiger_invoice vi 
              INNER JOIN vtiger_crmentity vc ON vi.invoiceid = vc.crmid 
              LEFT JOIN vtiger_payments vp ON vp.cf_paid_object = vi.invoiceid 
              LEFT JOIN vtiger_crmentity vc2 ON vc2.crmid = vp.paymentsid AND vc2.deleted = 0
              WHERE vc.deleted = 0";

    // Условия для фильтрации
    $params = [];
    if (!empty($startDate)) {
        $query .= " AND (TIMESTAMP(vi.cf_check_in_date, vi.cf_check_in_time) >= ?)";
        $params[] = $startDate . ' 00:00:00';
    }
    if (!empty($endDate)) {
        $query .= " AND (TIMESTAMP(vi.cf_check_in_date, vi.cf_check_in_time) <= ?)";
        $params[] = $endDate . ' 23:59:59';
        ;
    }

    // Условия для статуса оплаты
    if (!$paid && !$unpaid) {
        // Если оба фильтра отключены, ничего не показываем
        $query .= " AND 1 = 0"; // Эффективно ничего не возвращает
    } elseif ($paid && $unpaid) {
        // Если оба фильтра активны, ничего не добавляем
    } elseif (!$paid) {
        $query .= " AND vi.invoicestatus != 'Paid'"; // Показать только оплаченные
    } elseif (!$unpaid) {
        $query .= " AND vi.invoicestatus = 'Paid'"; // Показать только не оплаченные
    }

    $totalAmount = 0;
    $count = 0;

    $result = $adb->pquery($query, $params);

    foreach ($result as $res) {
        $count += $res['count'];
        $totalAmount += $res['total'];
    }

    return ['count' => $count, 'totalAmount' => $totalAmount];
}
?>