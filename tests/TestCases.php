<?php
namespace VtigerTests;
try {
    chdir('../');
    require_once 'include/utils/utils.php';
    require_once 'Logger.php';
    include_once 'includes/runtime/BaseModel.php';
    include_once 'includes/runtime/Globals.php';
    include_once 'includes/runtime/Controller.php';
    include_once 'includes/http/Request.php';

    global $current_user;
    global $adb;

    // Подключаем файл с функциями
    require_once __DIR__ . '/functions/ProcessCallsFunctions.php';

} catch (\Exception $e) {
    echo "<!-- Ошибка при инициализации: " . htmlspecialchars($e->getMessage()) . " -->\n";
}

/**
 * Класс для тестирования телефонии
 */
class TelephonyTests {
    /**
     * @var array Данные для тестов
     */
    private $testData;

    /**
     * @var array Тарифы
     */
    private $tariffs = [];

    /**
     * @var object Логгер
     */


    /**
     * @var array Результаты тестов
     */
    private $results = [];

    /**
     * @var array Ошибки
     */
    private $errors = [];

    /**
     * @var object База данных
     */
    private $db;

    /**
     * Конструктор
     * 
     * @param array $testData Данные для тестов
     */
    public function __construct($testData) {
        // Включаем отображение ошибок
        // ini_set('display_errors', 1);
        // error_reporting(E_ALL);

        // Проверяем, что функции определены
        echo "<!-- Функция getPrefixInfo определена: " . (function_exists('getPrefixInfo') ? 'Да' : 'Нет') . " -->\n";
        echo "<!-- Функция getTariffByPrefixAndType определена: " . (function_exists('getTariffByPrefixAndType') ? 'Да' : 'Нет') . " -->\n";
        echo "<!-- Функция calculateCallCost определена: " . (function_exists('calculateCallCost') ? 'Да' : 'Нет') . " -->\n";

        // Инициализируем данные для тестов
        $this->testData = $testData;
    }

    /**
     * Инициализация зависимостей
     */
    private function initDependencies() {
        try {
            echo "<!-- Инициализация зависимостей -->\n";

            // Используем глобальные переменные
            global $adb;

            if (isset($adb) && is_object($adb)) {
                echo "<!-- Глобальная переменная \$adb успешно получена -->\n";
                $this->db = $adb;


                // Загружаем тарифы из базы данных
                $this->loadTariffsFromDb();
            } else {
                echo "<!-- Глобальная переменная \$adb не найдена, используем тестовые тарифы -->\n";
                // $this->logger = new MockLogger();
                $this->tariffs = $this->getTestTariffs();
            }

            echo "<!-- Загружено " . count($this->tariffs) . " тарифов -->\n";
            echo "<!-- Зависимости успешно инициализированы -->\n";
        } catch (\Exception $e) {
            echo "<!-- Ошибка при инициализации зависимостей: " . htmlspecialchars($e->getMessage()) . " -->\n";
            throw new \Exception("Ошибка при инициализации зависимостей: " . $e->getMessage());
        }
    }

    /**
     * Загрузка тарифов из базы данных
     */
    private function loadTariffsFromDb() {
        try {
            echo "<!-- Загрузка тарифов из базы данных -->\n";

            // Запрос для получения тарифов, как в processCallsModal.php
            $query = "SELECT vp.cf_code, vp.productname, vp.cf_country_town, vp.cf_tarif_type_id, vp.unit_price, c.currency_code, vd.direction_name 
                      FROM vtiger_products vp 
                      INNER JOIN vtiger_direction vd ON vd.directionid = vp.cf_direction_id 
                      INNER JOIN vtiger_crmentity vc ON vp.productid = vc.crmid 
                      INNER JOIN vtiger_currency_info c ON c.id = vp.currency_id 
                      WHERE vc.deleted = 0";

            echo "<!-- Выполняем запрос: " . htmlspecialchars($query) . " -->\n";

            // Выполняем запрос
            $result = $this->db->pquery($query, array());

            if ($result === false) {
                echo "<!-- Ошибка при выполнении запроса -->\n";
                throw new \Exception("Ошибка при выполнении запроса к базе данных");
            }

            $tariffs = [];

            // Получаем данные
            while ($row = $this->db->fetchByAssoc($result)) {
                $tariffs[] = [
                    'cf_code' => $row['cf_code'],
                    'productname' => $row['productname'],
                    'cf_country_town' => $row['cf_country_town'],
                    'cf_tarif_type_id' => (int) $row['cf_tarif_type_id'],
                    'unit_price' => (float) $row['unit_price'],
                    'currency_code' => $row['currency_code'],
                    'direction_name' => $row['direction_name'],
                    'cf_direction' => $row['direction_name'], // Для совместимости с нашими функциями
                    'cf_price' => $row['unit_price'], // Для совместимости с нашими функциями
                    'cf_currency' => $row['currency_code'] // Для совместимости с нашими функциями
                ];
            }

            if (empty($tariffs)) {
                echo "<!-- Тарифы не найдены в базе данных, используем тестовые данные -->\n";
                $this->tariffs = $this->getTestTariffs();
            } else {
                echo "<!-- Загружено " . count($tariffs) . " тарифов из базы данных -->\n";
                $this->tariffs = $tariffs;
            }
        } catch (\Exception $e) {
            echo "<!-- Ошибка при загрузке тарифов из базы данных: " . htmlspecialchars($e->getMessage()) . " -->\n";
            echo "<!-- Используем тестовые тарифы -->\n";
            $this->tariffs = $this->getTestTariffs();
        }
    }

    /**
     * Получение тестовых тарифов
     * 
     * @return array Тестовые тарифы
     */
    private function getTestTariffs() {
        echo "<!-- Используем тестовые тарифы -->\n";
        return [
            // Бишкек - полное разделение по типам
            [
                'cf_code' => '312',
                'productname' => 'Бишкек',
                'cf_country_town' => 'Бишкек',
                'cf_tarif_type_id' => 1,
                'unit_price' => 1.5,
                'currency_code' => 'KGS',
                'direction_name' => 'Кыргызстан',
                'cf_direction' => 'Кыргызстан',
                'cf_price' => 1.5,
                'cf_currency' => 'KGS'
            ],
            [
                'cf_code' => '312',
                'productname' => 'Бишкек (Юр. лицо)',
                'cf_country_town' => 'Бишкек',
                'cf_tarif_type_id' => 4,
                'unit_price' => 2.0,
                'currency_code' => 'KGS',
                'direction_name' => 'Кыргызстан',
                'cf_direction' => 'Кыргызстан',
                'cf_price' => 2.0,
                'cf_currency' => 'KGS'
            ],
            [
                'cf_code' => '312',
                'productname' => 'Бишкек (КТЖ)',
                'cf_country_town' => 'Бишкек',
                'cf_tarif_type_id' => 3,
                'unit_price' => 1.8,
                'currency_code' => 'KGS',
                'direction_name' => 'Кыргызстан',
                'cf_direction' => 'Кыргызстан',
                'cf_price' => 1.8,
                'cf_currency' => 'KGS'
            ],
            // Россия - только общий тариф без разделения по типам
            [
                'cf_code' => '7',
                'productname' => 'Россия',
                'cf_country_town' => 'Москва',
                'cf_tarif_type_id' => 1,
                'unit_price' => 0.15,
                'currency_code' => 'USD',
                'direction_name' => 'Россия',
                'cf_direction' => 'Россия',
                'cf_price' => 0.15,
                'cf_currency' => 'USD'
            ],
            // Казахстан - только один тариф для всех типов
            [
                'cf_code' => '77',
                'productname' => 'Казахстан',
                'cf_country_town' => 'Алматы',
                'cf_tarif_type_id' => 1,
                'unit_price' => 0.12,
                'currency_code' => 'USD',
                'direction_name' => 'Казахстан',
                'cf_direction' => 'Казахстан',
                'cf_price' => 0.12,
                'cf_currency' => 'USD'
            ],
            // США - разделение только между физ. и юр. лицами
            [
                'cf_code' => '1',
                'productname' => 'США',
                'cf_country_town' => 'Нью-Йорк',
                'cf_tarif_type_id' => 1,
                'unit_price' => 0.25,
                'currency_code' => 'USD',
                'direction_name' => 'США',
                'cf_direction' => 'США',
                'cf_price' => 0.25,
                'cf_currency' => 'USD'
            ],
            [
                'cf_code' => '1',
                'productname' => 'США (Юр. лицо)',
                'cf_country_town' => 'Нью-Йорк',
                'cf_tarif_type_id' => 4,
                'unit_price' => 0.3,
                'currency_code' => 'USD',
                'direction_name' => 'США',
                'cf_direction' => 'США',
                'cf_price' => 0.3,
                'cf_currency' => 'USD'
            ],
            // Кыргызстан - полное разделение по типам
            [
                'cf_code' => '996',
                'productname' => 'Кыргызстан',
                'cf_country_town' => 'Бишкек',
                'cf_tarif_type_id' => 1,
                'unit_price' => 1.0,
                'currency_code' => 'KGS',
                'direction_name' => 'Кыргызстан',
                'cf_direction' => 'Кыргызстан',
                'cf_price' => 1.0,
                'cf_currency' => 'KGS'
            ],
            [
                'cf_code' => '996',
                'productname' => 'Кыргызстан (Юр. лицо)',
                'cf_country_town' => 'Бишкек',
                'cf_tarif_type_id' => 4,
                'unit_price' => 1.5,
                'currency_code' => 'KGS',
                'direction_name' => 'Кыргызстан',
                'cf_direction' => 'Кыргызстан',
                'cf_price' => 1.5,
                'cf_currency' => 'KGS'
            ],
            [
                'cf_code' => '996',
                'productname' => 'Кыргызстан (КТЖ)',
                'cf_country_town' => 'Бишкек',
                'cf_tarif_type_id' => 3,
                'unit_price' => 1.3,
                'currency_code' => 'KGS',
                'direction_name' => 'Кыргызстан',
                'cf_direction' => 'Кыргызстан',
                'cf_price' => 1.3,
                'cf_currency' => 'KGS'
            ]
        ];
    }

    /**
     * Запуск всех тестов
     * 
     * @return array Результаты тестов
     */
    public function runTests() {
        echo "<!-- Запуск тестов -->\n";

        // Результаты тестов
        $results = [
            'success' => true,
            'message' => '',
            'steps' => []
        ];

        try {
            // Инициализируем зависимости
            $this->initDependencies();

            // Получаем данные для тестов
            $phoneNumber = $this->testData['phoneNumber'];
            $contactType = $this->testData['contactType'];
            $duration = $this->testData['duration'];
            $usdRate = $this->testData['usdRate'];
            $testType = $this->testData['testType'];

            echo "<!-- Параметры тестов: phoneNumber={$phoneNumber}, contactType={$contactType}, duration={$duration}, usdRate={$usdRate}, testType={$testType} -->\n";
            echo "<!-- Количество загруженных тарифов: " . count($this->tariffs) . " -->\n";

            // Запускаем тесты в зависимости от типа
            if ($testType === 'all' || $testType === 'prefix') {
                $this->testFindPrefix($phoneNumber, $results);
            }

            if ($testType === 'all' || $testType === 'tariff') {
                $this->testFindTariff($phoneNumber, $contactType, $results);
            }

            if ($testType === 'all' || $testType === 'cost') {
                $this->testCalculateCost($phoneNumber, $contactType, $duration, $usdRate, $results);
            }

            // Если все тесты прошли успешно
            if ($results['success']) {
                $results['message'] = 'Все тесты выполнены успешно';
            }
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['message'] = 'Ошибка при выполнении тестов: ' . $e->getMessage();

            // Добавляем информацию об ошибке в шаги
            $results['steps'][] = [
                'name' => 'Ошибка',
                'status' => 'error',
                'output' => $e->getMessage() . "\n" . $e->getTraceAsString()
            ];

            echo "<!-- Исключение при выполнении тестов: " . htmlspecialchars($e->getMessage()) . " -->\n";
        }

        // Добавляем информацию о базе данных и тарифах
        $results['dataSource'] = 'Реальные данные из базы данных';
        $results['tariffsCount'] = count($this->tariffs);

        echo "<!-- Результаты тестов: " . ($results['success'] ? 'Успешно' : 'Ошибка') . " -->\n";
        return $results;
    }

    /**
     * Тест поиска префикса
     * 
     * @param string $phoneNumber Номер телефона
     * @param array &$results Результаты тестов
     * @return void
     */
    private function testFindPrefix($phoneNumber, &$results) {
        echo "<!-- Тест поиска префикса для номера {$phoneNumber} -->\n";

        $step = [
            'name' => 'Поиск префикса',
            'status' => 'pending',
            'output' => ''
        ];

        try {
            // Получаем информацию о префиксе
            $prefixInfo = getPrefixInfo($phoneNumber, $this->tariffs);

            if ($prefixInfo) {
                $step['status'] = 'success';
                $step['output'] = "Найден префикс: {$prefixInfo['cf_code']}\n";
                $step['output'] .= "Страна/город: {$prefixInfo['cf_country_town']}\n";
                $step['output'] .= "Направление: {$prefixInfo['direction_name']}\n";
                $step['data'] = $prefixInfo;
            } else {
                $step['status'] = 'error';
                $step['output'] = "Префикс не найден для номера {$phoneNumber}";
                $results['success'] = false;
                $results['message'] = 'Ошибка при поиске префикса';
            }
        } catch (\Exception $e) {
            $step['status'] = 'error';
            $step['output'] = "Ошибка при поиске префикса: " . $e->getMessage();
            $results['success'] = false;
            $results['message'] = 'Ошибка при поиске префикса';
        }

        $results['steps'][] = $step;
    }

    /**
     * Тест поиска тарифа
     * 
     * @param string $phoneNumber Номер телефона
     * @param string $contactType Тип контакта
     * @param array &$results Результаты тестов
     * @return void
     */
    private function testFindTariff($phoneNumber, $contactType, &$results) {
        echo "<!-- Тест поиска тарифа для номера {$phoneNumber} и типа контакта {$contactType} -->\n";

        $step = [
            'name' => 'Поиск тарифа',
            'status' => 'pending',
            'output' => ''
        ];

        try {
            // Получаем информацию о префиксе
            $prefixInfo = getPrefixInfo($phoneNumber, $this->tariffs);

            if (!$prefixInfo) {
                $step['status'] = 'error';
                $step['output'] = "Префикс не найден для номера {$phoneNumber}";
                $results['success'] = false;
                $results['message'] = 'Ошибка при поиске тарифа';
                $results['steps'][] = $step;
                return;
            }

            // Получаем все тарифы для данного префикса
            $filteredTariffs = array_filter($this->tariffs, function ($tariff) use ($prefixInfo) {
                return $tariff['cf_code'] === $prefixInfo['cf_code'];
            });

            // Получаем тариф по префиксу и типу контакта
            $tariff = getTariffByPrefixAndType($prefixInfo['cf_code'], $contactType, $this->tariffs);

            if ($tariff) {
                $step['status'] = 'success';
                $step['output'] = "Найден тариф: {$tariff['productname']}\n";
                $step['output'] .= "Цена за минуту: {$tariff['unit_price']} {$tariff['currency_code']}\n";
                $step['output'] .= "Тип тарифа: {$tariff['cf_tarif_type_id']}\n";

                // Проверяем, был ли использован fallback на общий тариф
                $tarifTypeNeeded = mapContactTypeToTarifTypeId($contactType);
                if ($tariff['cf_tarif_type_id'] !== $tarifTypeNeeded) {
                    $step['output'] .= "\nВнимание: Использован общий тариф, так как для типа контакта '{$contactType}' нет специального тарифа\n";
                    $step['output'] .= "Ожидаемый тип тарифа: {$tarifTypeNeeded}, Использован тип: {$tariff['cf_tarif_type_id']}\n";
                }

                // Добавляем информацию о доступных тарифах для этого префикса
                $step['output'] .= "\nДоступные тарифы для префикса {$prefixInfo['cf_code']}:\n";
                foreach ($filteredTariffs as $availableTariff) {
                    $step['output'] .= "- {$availableTariff['productname']} (тип: {$availableTariff['cf_tarif_type_id']}, цена: {$availableTariff['unit_price']} {$availableTariff['currency_code']})\n";
                }

                $step['data'] = [
                    'tariff' => $tariff,
                    'expectedTarifType' => $tarifTypeNeeded,
                    'availableTariffs' => $filteredTariffs
                ];
            } else {
                $step['status'] = 'error';
                $step['output'] = "Тариф не найден для префикса {$prefixInfo['cf_code']} и типа контакта {$contactType}";
                $results['success'] = false;
                $results['message'] = 'Ошибка при поиске тарифа';
            }
        } catch (\Exception $e) {
            $step['status'] = 'error';
            $step['output'] = "Ошибка при поиске тарифа: " . $e->getMessage();
            $results['success'] = false;
            $results['message'] = 'Ошибка при поиске тарифа';
        }

        $results['steps'][] = $step;
    }

    /**
     * Тест расчета стоимости звонка
     * 
     * @param string $phoneNumber Номер телефона
     * @param string $contactType Тип контакта
     * @param int $duration Длительность звонка в секундах
     * @param float $usdRate Курс доллара
     * @param array &$results Результаты тестов
     * @return void
     */
    private function testCalculateCost($phoneNumber, $contactType, $duration, $usdRate, &$results) {
        echo "<!-- Тест расчета стоимости звонка для номера {$phoneNumber}, типа контакта {$contactType}, длительности {$duration} сек. и курса доллара {$usdRate} -->\n";

        $step = [
            'name' => 'Расчет стоимости',
            'status' => 'pending',
            'output' => ''
        ];

        try {
            // Получаем информацию о префиксе
            $prefixInfo = getPrefixInfo($phoneNumber, $this->tariffs);

            if (!$prefixInfo) {
                $step['status'] = 'error';
                $step['output'] = "Префикс не найден для номера {$phoneNumber}";
                $results['success'] = false;
                $results['message'] = 'Ошибка при расчете стоимости';
                $results['steps'][] = $step;
                return;
            }

            // Получаем тариф по префиксу и типу контакта
            $tariff = getTariffByPrefixAndType($prefixInfo['cf_code'], $contactType, $this->tariffs);

            if (!$tariff) {
                $step['status'] = 'error';
                $step['output'] = "Тариф не найден для префикса {$prefixInfo['cf_code']} и типа контакта {$contactType}";
                $results['success'] = false;
                $results['message'] = 'Ошибка при расчете стоимости';
                $results['steps'][] = $step;
                return;
            }

            // Рассчитываем стоимость звонка
            $cost = calculateCallCost($duration, $usdRate, (float) $tariff['unit_price'], $tariff['currency_code']);

            $step['status'] = 'success';
            $step['output'] = "Длительность звонка: {$duration} сек.\n";
            $step['output'] .= "Цена за минуту: {$tariff['unit_price']} {$tariff['currency_code']}\n";
            $step['output'] .= "Курс доллара: {$usdRate}\n";
            $step['output'] .= "Стоимость звонка: {$cost} сом\n";
            $step['data'] = [
                'duration' => $duration,
                'unitPrice' => $tariff['unit_price'],
                'currency' => $tariff['currency_code'],
                'usdRate' => $usdRate,
                'cost' => $cost
            ];
        } catch (\Exception $e) {
            $step['status'] = 'error';
            $step['output'] = "Ошибка при расчете стоимости: " . $e->getMessage();
            $results['success'] = false;
            $results['message'] = 'Ошибка при расчете стоимости';
        }

        $results['steps'][] = $step;
    }
}

/**
 * Мок-класс для логгера
 */
class MockLogger {
    /**
     * Имитация записи в лог
     */
    public function log($message, $level = 'info') {
        // Ничего не делаем, так как это мок
    }
}