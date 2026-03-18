<?php
namespace VtigerTests;

use PHPUnit\Framework\TestCase;

// Подключаем класс для тестирования
require_once __DIR__ . '/TestCases.php';

/**
 * Адаптер для запуска тестов телефонии через PHPUnit
 */
class TelephonyTestsAdapter extends TestCase {
    /**
     * @var TelephonyTests Экземпляр класса для тестирования
     */
    private $telephonyTests;

    /**
     * Настройка перед каждым тестом
     */
    protected function setUp(): void {
        // Создаем тестовые данные
        $testData = [
            'phoneNumber' => '74992627510',
            'contactType' => 'Физ. лицо',
            'duration' => 60,
            'usdRate' => 89.5,
            'testType' => 'all'
        ];

        // Создаем экземпляр класса для тестирования
        $this->telephonyTests = new TelephonyTests($testData);
    }

    /**
     * Тест определения префикса
     */
    public function testPrefixDetection() {
        // Запускаем тест определения префикса
        $testData = [
            'phoneNumber' => '74992627510',
            'contactType' => 'Физ. лицо',
            'duration' => 60,
            'usdRate' => 89.5,
            'testType' => 'prefix'
        ];

        $telephonyTests = new TelephonyTests($testData);
        $results = $telephonyTests->runTests();

        // Проверяем, что тест прошел успешно
        $this->assertTrue($results['success'], 'Тест определения префикса должен пройти успешно');
    }

    /**
     * Тест поиска тарифа
     */
    public function testTariffCalculation() {
        // Запускаем тест поиска тарифа
        $testData = [
            'phoneNumber' => '74992627510',
            'contactType' => 'Физ. лицо',
            'duration' => 60,
            'usdRate' => 89.5,
            'testType' => 'tariff'
        ];

        $telephonyTests = new TelephonyTests($testData);
        $results = $telephonyTests->runTests();

        // Проверяем, что тест прошел успешно
        $this->assertTrue($results['success'], 'Тест поиска тарифа должен пройти успешно');
    }

    /**
     * Тест расчета стоимости звонка
     */
    public function testCallCostCalculation() {
        // Запускаем тест расчета стоимости звонка
        $testData = [
            'phoneNumber' => '74992627510',
            'contactType' => 'Физ. лицо',
            'duration' => 60,
            'usdRate' => 89.5,
            'testType' => 'cost'
        ];

        $telephonyTests = new TelephonyTests($testData);
        $results = $telephonyTests->runTests();

        // Проверяем, что тест прошел успешно
        $this->assertTrue($results['success'], 'Тест расчета стоимости звонка должен пройти успешно');
    }
}