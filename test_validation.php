<?php
/**
 * Тестовый скрипт для проверки валидации SERVICE полей
 */

require_once(__DIR__ . '/src/crest/crest.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;

echo "=== ТЕСТ ВАЛИДАЦИИ SERVICE ПОЛЕЙ ===\n\n";

// ID тестовой заявки
$testDealId = 671;

echo "1. Получаем данные заявки #$testDealId...\n";

$deal = \CRest::call('crm.deal.get', [
    'id' => $testDealId,
    'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD]
])['result'];

if (empty($deal['ID'])) {
    echo "❌ Заявка не найдена!\n";
    exit;
}

echo "✅ Заявка найдена: {$deal['TITLE']}\n\n";

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

// Функция валидации (копия из botManager.php)
$isValidServiceValue = function($serviceValue, $mainValue) {
    // Если SERVICE поле пустое, а основное не пустое - это изменение
    if (empty($serviceValue) && !empty($mainValue)) {
        return false;
    }
    
    // Если SERVICE поле содержит дату (формат Y-m-d H:i:s или ISO), а основное поле не дата - неправильно
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $serviceValue) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $mainValue)) {
        return false;
    }
    
    // Если SERVICE поле содержит "Array" - неправильно
    if ($serviceValue === 'Array') {
        return false;
    }
    
    return true;
};

$realChanges = [];

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
    $isValid = $isValidServiceValue($serviceValue, $mainValue);
    $isRealChange = $isDifferent && $isValid;
    
    $status = $isRealChange ? "✅ РЕАЛЬНОЕ ИЗМЕНЕНИЕ" : ($isDifferent ? "❌ ЛОЖНОЕ ИЗМЕНЕНИЕ" : "✅ НЕТ ИЗМЕНЕНИЙ");
    
    echo "   $fieldName: $status\n";
    echo "      Основное: " . ($mainValue ?: 'Пусто') . "\n";
    echo "      SERVICE:  " . ($serviceValue ?: 'Пусто') . "\n";
    echo "      Валидно:  " . ($isValid ? 'Да' : 'Нет') . "\n";
    echo "\n";
    
    if ($isRealChange) {
        $realChanges[] = $fieldName;
    }
}

echo "3. Реальные изменения: " . (empty($realChanges) ? "НЕТ" : implode(", ", $realChanges)) . "\n\n";

echo "4. Тестируем формирование сообщения...\n";

if (!empty($realChanges)) {
    // Создаем тестовые изменения только для реально измененных полей
    $testChanges = [];
    if (in_array('Адрес назначения', $realChanges)) {
        $testChanges['addressTo'] = 'Новый адрес назначения';
    }
    if (in_array('Класс автомобиля', $realChanges)) {
        $testChanges['carClass'] = $deal[botManager::CAR_CLASS_FIELD];
    }
    
    $message = botManager::orderTextForDriverWithChangesSimple($deal, $testChanges);
    echo "Результат:\n";
    echo "---\n";
    echo $message;
    echo "\n---\n";
} else {
    echo "Нет реальных изменений для тестирования\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
?>
