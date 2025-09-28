<?php
/**
 * Скрипт для очистки SERVICE полей от неправильных значений
 */

require_once(__DIR__ . '/src/crest/crest.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;

echo "=== ОЧИСТКА SERVICE ПОЛЕЙ ===\n\n";

// Получаем все заявки в стадиях PREPAYMENT_INVOICE и EXECUTING
$deals = \CRest::call('crm.deal.list', [
    'filter' => [
        'STAGE_ID' => ['PREPAYMENT_INVOICE', 'EXECUTING']
    ],
    'select' => ['ID', 'TITLE', 'STAGE_ID', 'UF_CRM_1751272181'],
    'order' => ['ID' => 'DESC'],
    'start' => 0
])['result'];

if (empty($deals)) {
    echo "❌ Заявки не найдены!\n";
    exit;
}

echo "Найдено заявок: " . count($deals) . "\n\n";

$updatedCount = 0;

foreach ($deals as $deal) {
    $dealId = $deal['ID'];
    $dealTitle = $deal['TITLE'];
    
    echo "Обрабатываем заявку #$dealId ($dealTitle)...\n";
    
    // Получаем полные данные заявки
    $dealFull = \CRest::call('crm.deal.get', [
        'id' => $dealId,
        'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD]
    ])['result'];
    
    if (empty($dealFull['ID'])) {
        echo "  ❌ Не удалось получить данные заявки\n";
        continue;
    }
    
    // Проверяем, нужно ли обновлять SERVICE поля
    $needsUpdate = false;
    $updateFields = [];
    
    // Проверяем каждое SERVICE поле
    $serviceFields = [
        botManager::DRIVER_SUM_FIELD_SERVICE => $dealFull[botManager::DRIVER_SUM_FIELD],
        botManager::ADDRESS_FROM_FIELD_SERVICE => $dealFull[botManager::ADDRESS_FROM_FIELD],
        botManager::ADDRESS_TO_FIELD_SERVICE => $dealFull[botManager::ADDRESS_TO_FIELD],
        botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => $dealFull[botManager::TRAVEL_DATE_TIME_FIELD],
        botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE => $dealFull[botManager::ADDITIONAL_CONDITIONS_FIELD],
        botManager::PASSENGERS_FIELD_SERVICE => $dealFull['UF_CRM_1751271798896'],
        botManager::FLIGHT_NUMBER_FIELD_SERVICE => $dealFull[botManager::FLIGHT_NUMBER_FIELD],
        botManager::CAR_CLASS_FIELD_SERVICE => $dealFull[botManager::CAR_CLASS_FIELD]
    ];
    
    foreach ($serviceFields as $serviceField => $mainValue) {
        $currentServiceValue = $dealFull[$serviceField] ?? null;
        
        // Если SERVICE поле содержит дату или неправильное значение - обновляем
        if (isInvalidServiceValue($currentServiceValue, $mainValue)) {
            $updateFields[$serviceField] = $mainValue;
            $needsUpdate = true;
            echo "  🔧 Исправляем $serviceField: '$currentServiceValue' → '$mainValue'\n";
        }
    }
    
    if ($needsUpdate) {
        // Обновляем заявку
        $updateResult = \CRest::call('crm.deal.update', [
            'id' => $dealId,
            'fields' => $updateFields
        ]);
        
        if (isset($updateResult['result']) && $updateResult['result'] === true) {
            echo "  ✅ Заявка #$dealId обновлена\n";
            $updatedCount++;
        } else {
            echo "  ❌ Ошибка обновления заявки #$dealId: " . json_encode($updateResult) . "\n";
        }
    } else {
        echo "  ✅ SERVICE поля уже корректны\n";
    }
    
    echo "\n";
}

echo "=== ОЧИСТКА ЗАВЕРШЕНА ===\n";
echo "Обновлено заявок: $updatedCount\n";

/**
 * Проверяет, является ли значение SERVICE поля неправильным
 */
function isInvalidServiceValue($serviceValue, $mainValue) {
    // Преобразуем массивы в строки для сравнения
    if (is_array($serviceValue)) {
        $serviceValue = implode(", ", $serviceValue);
    }
    if (is_array($mainValue)) {
        $mainValue = implode(", ", $mainValue);
    }
    
    // Если SERVICE поле пустое, а основное не пустое - нужно обновить
    if (empty($serviceValue) && !empty($mainValue)) {
        return true;
    }
    
    // Если SERVICE поле содержит дату (формат Y-m-d H:i:s или ISO), а основное поле не дата - неправильно
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $serviceValue) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $mainValue)) {
        return true;
    }
    
    // Если SERVICE поле содержит "Array" - неправильно
    if ($serviceValue === 'Array') {
        return true;
    }
    
    // Если SERVICE поле содержит |RUB, а основное не содержит - неправильно
    if (strpos($serviceValue, '|RUB') !== false && strpos($mainValue, '|RUB') === false) {
        return true;
    }
    
    return false;
}
?>
