<?php
/**
 * Тестовый скрипт для проверки системы отслеживания изменений
 */

require_once(__DIR__ . '/src/crest/crest.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;

echo "=== ТЕСТ СИСТЕМЫ ОТСЛЕЖИВАНИЯ ИЗМЕНЕНИЙ ===\n\n";

// ID тестовой заявки (замените на реальный ID)
$testDealId = 671; // Заявка с названием "777"

echo "1. Получаем данные заявки #$testDealId...\n";

$deal = \CRest::call('crm.deal.get', [
    'id' => $testDealId,
    'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD]
])['result'];

if (empty($deal['ID'])) {
    echo "❌ Заявка не найдена!\n";
    exit;
}

echo "✅ Заявка найдена: {$deal['TITLE']}\n";
echo "   Стадия: {$deal['STAGE_ID']}\n";
echo "   Водитель: " . ($deal[botManager::DRIVER_ID_FIELD] ?? 'Не назначен') . "\n\n";

echo "2. Проверяем SERVICE поля...\n";

$serviceFields = [
    'Сумма' => [
        'main' => $deal[botManager::DRIVER_SUM_FIELD],
        'service' => $deal[botManager::DRIVER_SUM_FIELD_SERVICE]
    ],
    'Адрес отправления' => [
        'main' => $deal[botManager::ADDRESS_FROM_FIELD],
        'service' => $deal[botManager::ADDRESS_FROM_FIELD_SERVICE]
    ],
    'Адрес назначения' => [
        'main' => $deal[botManager::ADDRESS_TO_FIELD],
        'service' => $deal[botManager::ADDRESS_TO_FIELD_SERVICE]
    ],
    'Дата и время' => [
        'main' => $deal[botManager::TRAVEL_DATE_TIME_FIELD],
        'service' => $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE]
    ],
    'Дополнительные условия' => [
        'main' => $deal[botManager::ADDITIONAL_CONDITIONS_FIELD],
        'service' => $deal[botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE]
    ],
    'Пассажиры' => [
        'main' => $deal['UF_CRM_1751271798896'],
        'service' => $deal[botManager::PASSENGERS_FIELD_SERVICE]
    ],
    'Номер рейса' => [
        'main' => $deal[botManager::FLIGHT_NUMBER_FIELD],
        'service' => $deal[botManager::FLIGHT_NUMBER_FIELD_SERVICE]
    ],
    'Класс автомобиля' => [
        'main' => $deal[botManager::CAR_CLASS_FIELD],
        'service' => $deal[botManager::CAR_CLASS_FIELD_SERVICE]
    ]
];

foreach ($serviceFields as $fieldName => $values) {
    $mainValue = $values['main'];
    $serviceValue = $values['service'];
    
    if (is_array($mainValue)) {
        $mainValue = implode(", ", $mainValue);
    }
    if (is_array($serviceValue)) {
        $serviceValue = implode(", ", $serviceValue);
    }
    
    $isDifferent = ($mainValue !== $serviceValue);
    $status = $isDifferent ? "❌ РАЗНЫЕ" : "✅ ОДИНАКОВЫЕ";
    
    echo "   $fieldName: $status\n";
    echo "      Основное: " . ($mainValue ?: 'Пусто') . "\n";
    echo "      SERVICE:  " . ($serviceValue ?: 'Пусто') . "\n";
    echo "\n";
}

echo "3. Тестируем функцию формирования сообщения...\n";

// Создаем тестовые изменения
$testChanges = [
    'newSum' => 1500,
    'newFromAddress' => 'Новый адрес отправления',
    'newToAddress' => 'Новый адрес назначения',
    'newDate' => '2025-09-29 15:30:00',
    'newAdditionalConditions' => 'Новые условия',
    'newPassengers' => 'Новый пассажир',
    'newFlightNumber' => '999',
    'newCarClass' => '119'
];

echo "   Тестовые изменения:\n";
foreach ($testChanges as $key => $value) {
    echo "      $key: $value\n";
}
echo "\n";

// Тестируем функцию
$testMessage = botManager::orderTextForDriverWithChangesNew(
    $deal,
    $testChanges['newSum'],
    $testChanges['newFromAddress'],
    $testChanges['newToAddress'],
    $testChanges['newDate'],
    $testChanges['newAdditionalConditions'],
    $testChanges['newPassengers'],
    $testChanges['newFlightNumber'],
    $testChanges['newCarClass']
);

echo "4. Результат формирования сообщения:\n";
echo "---\n";
echo $testMessage;
echo "\n---\n\n";

echo "5. Проверяем наличие зачеркнутых значений...\n";

if (strpos($testMessage, '<s>') !== false) {
    echo "✅ Зачеркнутые значения найдены!\n";
} else {
    echo "❌ Зачеркнутые значения НЕ найдены!\n";
}

if (strpos($testMessage, '➔') !== false) {
    echo "✅ Стрелки изменений найдены!\n";
} else {
    echo "❌ Стрелки изменений НЕ найдены!\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
?>
