<?php

function getPrefixInfo($phoneNumber, $allTariffs) {
    // Проверяем, что номер телефона содержит только цифры
    if (!preg_match('/^\d+$/', $phoneNumber)) {
        return null;
    }

    // Определяем тип номера по длине
    $length = strlen($phoneNumber);

    if ($length === 9) {
        // Местный номер (Кыргызстан)
        return getKyrgyzPrefix($phoneNumber, $allTariffs);
    } else {
        // Международный номер
        return getInternationalPrefix($phoneNumber, $allTariffs);
    }
}

function getTariffByPrefixAndType($prefix, $contactType, $allTariffs) {
    // Преобразуем тип контакта в ID типа тарифа
    $tarifTypeNeeded = mapContactTypeToTarifTypeId($contactType);

    // Фильтруем тарифы по префиксу
    $filteredTariffs = array_filter($allTariffs, function ($tariff) use ($prefix) {
        return $tariff['cf_code'] === $prefix;
    });

    // Если есть тарифы с указанным префиксом
    if (!empty($filteredTariffs)) {
        // Сначала ищем тариф, где cf_code и cf_tarif_type_id совпадают
        foreach ($filteredTariffs as $tariff) {
            if ($tariff['cf_tarif_type_id'] === $tarifTypeNeeded) {
                return $tariff;
            }
        }

        // Если не нашли с нужным типом тарифа, возвращаем первый найденный
        return reset($filteredTariffs);
    }

    return null;
}

function calculateCallCost(int $durationSec, float $usdRate, float $unitPrice, string $currencyCode): float {
    // Защита от отрицательной длительности
    $durationSec = max(0, $durationSec);

    // Всегда минимум 1 минута
    $minutes = max(1, ceil($durationSec / 60.0));

    if (strtoupper($currencyCode) === 'USD') {
        // Защита от отрицательного курса
        $usdRate = max(0, $usdRate);
        $priceSomPerMin = $unitPrice * $usdRate;
    } else {
        $priceSomPerMin = $unitPrice;
    }

    $callCost = $priceSomPerMin * $minutes;
    return round($callCost, 2);
}

function getInternationalPrefix(string $number_b, array $allTariffs): ?array {
    // 1. Сначала пробуем найти по существующим тарифам
    $filteredTariffs = array_filter($allTariffs, function ($tariff) {
        return mb_stripos($tariff['direction_name'], 'Кыргызстан') === false;
    });

    usort($filteredTariffs, function ($a, $b) {
        return strlen($b['cf_code']) <=> strlen($a['cf_code']);
    });

    foreach ($filteredTariffs as $tariff) {
        $prefix = strtolower(trim($tariff['cf_code']));
        if ($prefix !== '' && strpos($number_b, $prefix) === 0) {
            return $tariff;
        }
    }

    // 2. Если не нашли, пробуем через DaData API
    return checkNumberViaDaData($number_b, $allTariffs);
}

function checkNumberViaDaData(string $phoneNumber, array $allTariffs): ?array {
    $url = 'https://cleaner.dadata.ru/api/v1/clean/phone';
    $data = [$phoneNumber];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Token 29eaf1428e2d87142055cc3e936b2f5b8604c921',
            'X-Secret: 82fe3e7a9fa42496b9a516b7f50a1abafa7f9941'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return null;
    }

    $result = json_decode($response, true);
    if (!$result || !isset($result[0]['country'])) {
        return null;
    }

    // Преобразуем ответ API в формат тарифа
    return [
        'cf_code' => $result[0]['country_code'],
        'productname' => $result[0]['country'],
        'direction_name' => $result[0]['country'],
        'unit_price' => 0, // Тут нужно будет определить цену по стране
        'currency_code' => 'USD'
    ];
}

function mapContactTypeToTarifTypeId($contactType) {
    $mapping = [
        'Физ. лицо' => 1,
        'Юр. лицо' => 4,
        'КТЖ' => 3
    ];
    return $mapping[$contactType] ?? null;
}

function getUsdRateFromNBKR() {
    $url = "https://www.nbkr.kg/";
    $html = file_get_contents($url);
    if (!$html) {
        return ['success' => false, 'message' => 'Ошибка загрузки страницы NBKR'];
    }
    if (preg_match('/<td class="excurr">.*?1USD.*?<\/td>\s*<td class="exrate">([\d,]+)\s*<\/td>/', $html, $matches)) {
        $usdRate = str_replace(',', '.', trim($matches[1]));
        return ['success' => true, 'usdRate' => (float) $usdRate];
    }
    return ['success' => false, 'message' => 'Курс USD не найден'];
}

function updateCallRecord(
    int $callsid,
    float $callCost,
    string $cf_code,
    string $cf_category,
    float $usdRate,
    $call_id_number,
    $adb = null,
    $logger = null
): bool {
    try {
        if (!$adb) {
            return false;
        }

        $updateQuery = "UPDATE vtiger_calls 
                        SET cf_call_cost = ?, 
                            cf_code = ?, 
                            cf_category = ?, 
                            cf_usd_rate = ? 
                        WHERE callsid = ?";

        $params = [
            $callCost,
            $cf_code,
            $cf_category,
            $usdRate,
            $callsid
        ];

        $result = $adb->pquery($updateQuery, $params);

        // Логируем только если запрос успешен и есть логгер
        if ($result && $logger) {
            $logger->log(json_encode([
                'call_id' => $callsid,
                'cost' => $callCost,
                'code' => $cf_code
            ]));
        }

        return $result === true;
    } catch (Exception $e) {
        return false;
    }
}

function getKyrgyzPrefix(string $number_b, array $allTariffs): ?array {
    // Фильтруем тарифы, оставляем только для Кыргызстана
    $filteredTariffs = array_filter($allTariffs, function ($tariff) {
        return mb_stripos($tariff['direction_name'], 'Кыргызстан') !== false;
    });

    // Сортируем от самых длинных префиксов к коротким
    usort($filteredTariffs, function ($a, $b) {
        return strlen($b['cf_code']) <=> strlen($a['cf_code']);
    });

    foreach ($filteredTariffs as $tariff) {
        $prefix = strtolower(trim($tariff['cf_code']));
        if ($prefix !== '' && strpos($number_b, $prefix) === 0) {
            return $tariff;
        }
    }
    return null;
}

function searchProcessedCall(string $number_b) {
    global $adb;
    $query = "SELECT cl.cf_number_b, cl.cf_category, cf_code 
             FROM vtiger_calls cl 
             INNER JOIN vtiger_crmentity vc ON vc.crmid = cl.callsid 
             WHERE vc.deleted = 0
               AND cf_calltype NOT IN ('Входящий', 'Внутренний')
               AND cl.cf_number_b = ?
               AND cl.cf_code IS NOT NULL 
               AND cl.cf_code != ''
             LIMIT 1";
    $result = $adb->pquery($query, [$number_b]);
    return $adb->fetchByAssoc($result) ?: false;
}

function processFoundTariff(array $found, array $allTariffs, array $call, float $usdRate, int $tarifTypeNeeded): bool {
    global $adb, $logger;

    // Проверяем входные данные
    if (!isset($found['cf_code'])) {
        return false;
    }

    $foundCode = $found['cf_code'];
    $matchedTariff = null;

    // Ищем тариф с совпадающим кодом и типом
    foreach ($allTariffs as $tariff) {
        if ($tariff['cf_code'] === $foundCode && $tariff['cf_tarif_type_id'] === $tarifTypeNeeded) {
            $matchedTariff = $tariff;
            break;
        }
    }

    // Если не нашли по типу, ищем только по коду
    if (!$matchedTariff) {
        foreach ($allTariffs as $tariff) {
            if ($tariff['cf_code'] === $foundCode) {
                $matchedTariff = $tariff;
                break;
            }
        }
    }

    if (!$matchedTariff) {
        return false;
    }

    $combinedName = $matchedTariff['productname'];
    if (!empty($matchedTariff['cf_country_town'])) {
        $combinedName .= ', ' . $matchedTariff['cf_country_town'];
    }

    $durationSec = (int) $call['cf_duration'];
    $callCost = calculateCallCost(
        $durationSec,
        $usdRate,
        $matchedTariff['unit_price'],
        $matchedTariff['currency_code']
    );

    // Логируем перед обновлением
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'call_id_number' => $call['call_id_number'],
        'tariff_code' => $matchedTariff['cf_code'],
        'tariff_info' => $combinedName,
        'cost' => $callCost,
        'usd' => $usdRate
    ];
    $logger->log(json_encode($logData, JSON_UNESCAPED_UNICODE));

    // Обновляем запись
    $updateQuery = "UPDATE vtiger_calls 
                    SET cf_call_cost = ?, 
                        cf_code = ?, 
                        cf_category = ?, 
                        cf_usd_rate = ? 
                    WHERE callsid = ?";

    $params = [
        $callCost,
        $matchedTariff['cf_code'],
        $combinedName,
        $usdRate,
        $call['callsid']
    ];

    $result = $adb->pquery($updateQuery, $params);
    return $result === true;
}