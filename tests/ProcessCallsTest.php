<?php
namespace VtigerTests;

use PHPUnit\Framework\TestCase;
use VtigerTests\PearDatabase;
use VtigerTests\CustomLogger;
use function VtigerTests\calculateCallCost;
use function VtigerTests\getInternationalPrefix;
use function VtigerTests\getKyrgyzPrefix;
use function VtigerTests\updateCallRecord;
use function VtigerTests\searchProcessedCall;
use function VtigerTests\processFoundTariff;
use function VtigerTests\getUsdRateFromNBKR;
use function VtigerTests\mapContactTypeToTarifTypeId;

class ProcessCallsTest extends TestCase {
    private $adb;
    private $logger;

    protected function setUp(): void {
        $this->adb = $this->createMock(PearDatabase::class);

        $this->logger = $this->createMock(CustomLogger::class);

        global $adb;
        $adb = $this->adb;
    }

    public function testCalculateCallCost(): void {
        $result1 = calculateCallCost(120, 89.5, 0.1, 'USD');
        $this->assertEquals(17.90, $result1);

        $result2 = calculateCallCost(61, 89.5, 2.5, 'KGS');
        $this->assertEquals(5.00, $result2);
    }

    public function testGetInternationalPrefix(): void {
        $testTariffs = [
            [
                'cf_code' => '996',
                'direction_name' => 'Кыргызстан',
                'unit_price' => 1.5
            ],
            [
                'cf_code' => '7',
                'direction_name' => 'Россия',
                'unit_price' => 2.5
            ]
        ];

        $number = '79001234567';

        $result = getInternationalPrefix($number, $testTariffs);
        $this->assertEquals('7', $result['cf_code']);
        $this->assertEquals(2.5, $result['unit_price']);
    }

    public function testGetUsdRateFromNBKR(): void {
        $result = getUsdRateFromNBKR();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        if ($result['success']) {
            $this->assertArrayHasKey('usdRate', $result);
            $this->assertIsFloat($result['usdRate']);
        } else {
            $this->assertArrayHasKey('message', $result);
        }
    }

    public function testMapContactTypeToTarifTypeId(): void {
        $this->assertEquals(1, mapContactTypeToTarifTypeId('Физ. лицо'));
        $this->assertEquals(4, mapContactTypeToTarifTypeId('Юр. лицо'));
        $this->assertEquals(3, mapContactTypeToTarifTypeId('КТЖ'));
        $this->assertNull(mapContactTypeToTarifTypeId('Неизвестный тип'));
    }

    public function testUpdateCallRecord(): void {
        $logger = $this->createMock(CustomLogger::class);
        $logger->expects($this->once())
            ->method('log')
            ->willReturn(true);

        $this->adb->expects($this->once())
            ->method('pquery')
            ->willReturn(true);

        $result = updateCallRecord(1, 10.5, 'TEST', 'Test Category', 89.5, '12345', $this->adb, $logger);
        $this->assertTrue($result);
    }

    public function testCalculateCallCostEdgeCases(): void {
        // Нулевая длительность
        $result1 = calculateCallCost(0, 89.5, 0.1, 'USD');
        $this->assertEquals(8.95, $result1, 'Минимальная длительность должна быть 1 минута');

        // Очень длинный звонок
        $result2 = calculateCallCost(7200, 89.5, 0.1, 'USD'); // 2 часа
        $this->assertEquals(1074.00, $result2, '2 часа звонка');

        // Нестандартный курс
        $result3 = calculateCallCost(60, 0.0, 10, 'KGS');
        $this->assertEquals(10.00, $result3, 'KGS не должен зависеть от курса USD');
    }

    public function testGetInternationalPrefixInvalid(): void {
        $testTariffs = [
            [
                'cf_code' => '996',
                'direction_name' => 'Кыргызстан',
                'unit_price' => 1.5
            ]
        ];

        // Пустой номер
        $result1 = getInternationalPrefix('', $testTariffs);
        $this->assertNull($result1, 'Пустой номер должен вернуть null');

        // Некорректный номер
        $result2 = getInternationalPrefix('abc123', $testTariffs);
        $this->assertNull($result2, 'Некорректный номер должен вернуть null');

        // Слишком короткий номер
        $result3 = getInternationalPrefix('123', $testTariffs);
        $this->assertNull($result3, 'Короткий номер должен вернуть null');
    }

    public function testLogging(): void {
        $logger = $this->createMock(CustomLogger::class);
        $logger->expects($this->once())
            ->method('log')
            ->willReturn(true);

        $this->adb->expects($this->once())
            ->method('pquery')
            ->willReturn(true);

        $result = updateCallRecord(1, 10.5, 'TEST', 'Test Category', 89.5, '12345', $this->adb, $logger);
        $this->assertTrue($result);
    }

    public function testDatabaseError(): void {
        $logger = $this->createMock(CustomLogger::class);
        $logger->expects($this->never())
            ->method('log');

        $this->adb->expects($this->once())
            ->method('pquery')
            ->willReturn(false);

        $result = updateCallRecord(1, 10.5, 'TEST', 'Test Category', 89.5, '12345', $this->adb, $logger);
        $this->assertFalse($result);
    }

    public function testGetKyrgyzPrefix(): void {
        $testTariffs = [
            [
                'cf_code' => '312',
                'direction_name' => 'Кыргызстан, Бишкек',
                'unit_price' => 1.5,
                'currency_code' => 'KGS'
            ],
            [
                'cf_code' => '3122',
                'direction_name' => 'Кыргызстан, Чуй',
                'unit_price' => 2.0,
                'currency_code' => 'KGS'
            ],
            [
                'cf_code' => '7',
                'direction_name' => 'Россия',
                'unit_price' => 3.0,
                'currency_code' => 'USD'
            ]
        ];

        // Тест 1: Должен найти самый длинный подходящий префикс
        $result1 = getKyrgyzPrefix('312234567', $testTariffs);
        $this->assertEquals('3122', $result1['cf_code']);
        $this->assertEquals(2.0, $result1['unit_price']);

        // Тест 2: Должен найти короткий префикс
        $result2 = getKyrgyzPrefix('312345678', $testTariffs);
        $this->assertEquals('312', $result2['cf_code']);

        // Тест 3: Не должен находить международные префиксы
        $result3 = getKyrgyzPrefix('790012345', $testTariffs);
        $this->assertNull($result3);
    }

    public function testSearchProcessedCall(): void {
        // Тест 1: Проверяем поиск существующего номера
        $this->adb->expects($this->once())
            ->method('pquery')
            ->with(
                $this->stringContains('SELECT cl.cf_number_b'),
                ['996312123456']
            )
            ->willReturn(true);

        $this->adb->expects($this->once())
            ->method('fetchByAssoc')
            ->willReturn([
                'cf_number_b' => '996312123456',
                'cf_category' => 'Тестовый тариф',
                'cf_code' => '312'
            ]);

        $result1 = searchProcessedCall('996312123456');
        $this->assertIsArray($result1);
        $this->assertEquals('312', $result1['cf_code']);

        // Тест 2: Создаем новый мок для второго теста
        $adb2 = $this->createMock(PearDatabase::class);
        $adb2->expects($this->once())
            ->method('pquery')
            ->willReturn(true);
        $adb2->expects($this->once())
            ->method('fetchByAssoc')
            ->willReturn(false);

        // Временно подменяем глобальную переменную
        global $adb;
        $oldAdb = $adb;
        $adb = $adb2;

        $result2 = searchProcessedCall('999999999');
        $this->assertFalse($result2);

        // Возвращаем оригинальный объект
        $adb = $oldAdb;
    }

    public function testProcessFoundTariff(): void {
        // Создаем мок для логгера
        $mockLogger = $this->createMock(CustomLogger::class);
        $mockLogger->expects($this->once())
            ->method('log')
            ->willReturn(true);

        // Настраиваем мок для базы данных
        $this->adb->expects($this->once())
            ->method('pquery')
            ->willReturn(true);

        $found = [
            'cf_code' => '312',
            'cf_category' => 'Бишкек'
        ];

        $allTariffs = [
            [
                'cf_code' => '312',
                'productname' => 'Бишкек',
                'cf_country_town' => 'Центр',
                'cf_tarif_type_id' => 1,
                'unit_price' => 1.5,
                'currency_code' => 'KGS',
                'direction_name' => 'Кыргызстан'
            ]
        ];

        $call = [
            'callsid' => 1,
            'cf_duration' => 120,
            'call_id_number' => '123456'
        ];

        // Подменяем глобальные переменные
        global $logger;
        $oldLogger = $logger;
        $logger = $mockLogger;

        // Тест 1: Проверяем успешное обновление
        $result = processFoundTariff($found, $allTariffs, $call, 89.5, 1);
        $this->assertTrue($result);

        // Возвращаем оригинальный логгер
        $logger = $oldLogger;

        // Тест 2: Создаем новый мок для второго теста
        $mockLogger2 = $this->createMock(CustomLogger::class);
        $mockLogger2->expects($this->never())
            ->method('log');
        $logger = $mockLogger2;

        // Проверяем случай, когда тариф не найден
        $found['cf_code'] = '999';
        $result2 = processFoundTariff($found, $allTariffs, $call, 89.5, 1);
        $this->assertFalse($result2);

        $logger = $oldLogger;
    }

    public function testGetInternationalPrefixWithCountries(): void {
        $testTariffs = [
            [
                'cf_code' => '7',
                'productname' => 'Россия',
                'cf_country_town' => 'Москва',
                'unit_price' => 3.0,
                'currency_code' => 'USD',
                'direction_name' => 'Россия'
            ],
            [
                'cf_code' => '77',
                'productname' => 'Казахстан',
                'cf_country_town' => 'Алматы',
                'unit_price' => 2.5,
                'currency_code' => 'USD',
                'direction_name' => 'Казахстан'
            ]
        ];

        // Тест 1: Россия
        $number = '79001234567';
        var_dump("=== Тест 1: Проверка номера $number ===");
        $result1 = getInternationalPrefix($number, $testTariffs);
        var_dump("Результат:", $result1);

        $this->assertNotNull($result1);
        $this->assertEquals('7', $result1['cf_code']);

        // Тест 2: Казахстан
        $number = '77012345678';
        var_dump("=== Тест 2: Проверка номера $number ===");
        $result2 = getInternationalPrefix($number, $testTariffs);
        var_dump("Результат:", $result2);

        $this->assertNotNull($result2);
        $this->assertEquals('77', $result2['cf_code']);
    }

    public function testGetInternationalPrefixWithDaData(): void {
        $testTariffs = [
            [
                'cf_code' => '7',
                'productname' => 'Россия',
                'unit_price' => 3.0,
                'currency_code' => 'USD',
                'direction_name' => 'Россия'
            ]
        ];

        // Тест 1: Номер, которого нет в тарифах
        $number = '819012345678'; // Японский номер
        echo "\n=== Тест API DaData для номера $number ===\n";

        $result = getInternationalPrefix($number, $testTariffs);
        echo "Результат определения страны:\n";
        print_r($result);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('productname', $result);
        $this->assertEquals('Япония', $result['productname']);
        $this->assertEquals('81', $result['cf_code']);

        // Тест 2: Некорректный номер
        $number = '000000000';
        echo "\n=== Тест API DaData для некорректного номера $number ===\n";

        $result2 = getInternationalPrefix($number, $testTariffs);
        echo "Результат для некорректного номера:\n";
        print_r($result2);

        $this->assertNull($result2);
    }

    public function testNumberProcessingByLength(): void {
        $testTariffs = [
            [
                'cf_code' => '312',
                'productname' => 'Бишкек',
                'cf_country_town' => 'Город',
                'cf_tarif_type_id' => 1,
                'unit_price' => 1.5,
                'currency_code' => 'KGS',
                'direction_name' => 'Кыргызстан'
            ],
            [
                'cf_code' => '7',
                'productname' => 'Россия',
                'cf_country_town' => 'Москва',
                'cf_tarif_type_id' => 1,
                'unit_price' => 3.0,
                'currency_code' => 'USD',
                'direction_name' => 'Россия'
            ]
        ];

        // Тест 1: Короткий номер (< 9 цифр)
        $shortNumber = '12345678';
        $result1 = getInternationalPrefix($shortNumber, $testTariffs);
        $this->assertNull($result1, 'Короткий номер должен возвращать null');

        // Тест 2: 9-значный номер (местный)
        $localNumber = '312234567';
        $result2 = getKyrgyzPrefix($localNumber, $testTariffs);
        $this->assertNotNull($result2, '9-значный номер должен найти местный тариф');
        $this->assertEquals('312', $result2['cf_code']);
        $this->assertEquals('Кыргызстан', $result2['direction_name']);

        // Тест 3: Международный номер (> 9 цифр)
        $internationalNumber = '79001234567';
        $result3 = getInternationalPrefix($internationalNumber, $testTariffs);
        $this->assertNotNull($result3, 'Международный номер должен найти тариф');
        $this->assertEquals('7', $result3['cf_code']);
        $this->assertEquals('Россия', $result3['direction_name']);

        // Тест 4: Номер с ведущими нулями
        $numberWithZeros = '00312234567';
        $strippedNumber = ltrim($numberWithZeros, '0');
        $result4 = getKyrgyzPrefix($strippedNumber, $testTariffs);
        $this->assertNotNull($result4, 'Номер с ведущими нулями должен найти тариф после очистки');
        $this->assertEquals('312', $result4['cf_code']);
    }

    public function testSearchProcessedCallIntegration(): void {
        // Настраиваем мок для базы данных
        $this->adb->expects($this->exactly(2))
            ->method('pquery')
            ->willReturn(true);

        // Первый вызов возвращает существующий звонок
        $this->adb->expects($this->exactly(2))
            ->method('fetchByAssoc')
            ->willReturnOnConsecutiveCalls(
                [
                    'cf_number_b' => '312234567',
                    'cf_category' => 'Бишкек, Город',
                    'cf_code' => '312'
                ],
                false
            );

        // Тест 1: Поиск существующего номера
        $result1 = searchProcessedCall('312234567');
        $this->assertIsArray($result1);
        $this->assertEquals('312', $result1['cf_code']);

        // Тест 2: Поиск несуществующего номера
        $result2 = searchProcessedCall('999999999');
        $this->assertFalse($result2);
    }

    public function testFullCallProcessing(): void {
        $testTariffs = [
            [
                'cf_code' => '312',
                'productname' => 'Бишкек',
                'cf_country_town' => 'Город',
                'cf_tarif_type_id' => 1,
                'unit_price' => 1.5,
                'currency_code' => 'KGS',
                'direction_name' => 'Кыргызстан'
            ]
        ];

        $call = [
            'callsid' => 1,
            'cf_number_b' => '312234567',
            'cf_duration' => 120,
            'call_id_number' => '123456',
            'cf_contact_type' => 'Физ. лицо'
        ];

        // Настраиваем моки
        $mockLogger = $this->createMock(CustomLogger::class);
        $mockLogger->expects($this->atLeastOnce())
            ->method('log')
            ->with($this->isType('string'))
            ->willReturn(true);

        // Настраиваем мок для базы данных
        $this->adb->expects($this->once())
            ->method('pquery')
            ->willReturn(true);

        $this->adb->expects($this->once())
            ->method('fetchByAssoc')
            ->willReturn(false);

        // Подменяем глобальные переменные
        global $logger, $adb;
        $oldLogger = $logger;
        $oldAdb = $adb;
        $logger = $mockLogger;
        $adb = $this->adb;

        try {
            // Проверяем полный процесс обработки
            $number_b = ltrim($call['cf_number_b'], '0');
            $length = strlen($number_b);

            $this->assertEquals(9, $length, 'Длина номера должна быть 9 цифр');

            // Логируем начало обработки
            $logger->log("Обработка номера: {$number_b}, длина: {$length}");

            // Проверяем поиск в истории
            $found = searchProcessedCall($number_b);
            $this->assertFalse($found, 'Номер не должен быть найден в истории');

            // Проверяем определение тарифа
            $prefixInfo = getKyrgyzPrefix($number_b, $testTariffs);
            $this->assertNotNull($prefixInfo, 'Должен найти тариф для местного номера');

            // Проверяем расчет стоимости
            $callCost = calculateCallCost(
                (int) $call['cf_duration'],
                89.5,
                $prefixInfo['unit_price'],
                $prefixInfo['currency_code']
            );

            $this->assertGreaterThan(0, $callCost, 'Стоимость звонка должна быть больше 0');

            // Логируем успешное завершение
            $logger->log("Успешно обработан звонок, стоимость: {$callCost}");
        } finally {
            // Возвращаем оригинальные объекты
            $logger = $oldLogger;
            $adb = $oldAdb;
        }
    }
}