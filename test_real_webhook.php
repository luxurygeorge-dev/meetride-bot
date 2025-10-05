<?php
/**
 * Тестовый скрипт для проверки реального webhook
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;
use Telegram\Bot\Api;

echo "=== ТЕСТ РЕАЛЬНОГО WEBHOOK ===\n\n";

// Создаем тестовые данные webhook (как отправляет Bitrix24)
$webhookData = [
    'event' => 'ONCRMDEALUPDATE',
    'event_handler_id' => '3',
    'data' => [
        'FIELDS' => [
            'ID' => 777,
            'UF_CRM_1751269147414' => 'Аэропорт Пулково', // Точка А (новое)
            'UF_CRM_1751269175432' => 'Московский вокзал', // Точка Б (новое)
            'UF_CRM_1751269222959' => '2025-10-05 15:30:00', // Время (новое)
            'UF_CRM_1751269256380' => '2 пассажира, багаж', // Пассажиры (новое)
            'UF_CRM_1754228146' => 'Невский проспект, Дворцовая площадь', // Промежуточные точки (новое)
            'OLD' => [
                'UF_CRM_1751269147414' => 'Центр города', // Точка А (старое)
                'UF_CRM_1751269175432' => 'Центр', // Точка Б (старое)
                'UF_CRM_1751269222959' => '2025-10-05 14:00:00', // Время (старое)
                'UF_CRM_1751269256380' => '1 пассажир', // Пассажиры (старое)
                'UF_CRM_1754228146' => 'Невский проспект', // Промежуточные точки (старое)
            ]
        ]
    ]
];

echo "1. Тестовые данные webhook:\n";
echo "Deal ID: " . $webhookData['data']['FIELDS']['ID'] . "\n";
echo "Изменения:\n";
foreach ($webhookData['data']['FIELDS']['OLD'] as $field => $oldValue) {
    $newValue = $webhookData['data']['FIELDS'][$field] ?? 'НЕ УКАЗАНО';
    echo "  - $field: '$oldValue' → '$newValue'\n";
}

echo "\n2. Тестирование обработки webhook...\n";

try {
    // Имитируем webhook данные
    $_REQUEST = $webhookData;
    
    // Создаем экземпляр бота
    $bot = new botManager();
    
    // Получаем данные сделки
    $dealId = $webhookData['data']['FIELDS']['ID'];
    $fields = $webhookData['data']['FIELDS'];
    
    echo "Deal ID: $dealId\n";
    echo "Поля для отслеживания:\n";
    
    $trackedFields = [
        botManager::ADDRESS_FROM_FIELD => 'Точка А',
        botManager::ADDRESS_TO_FIELD => 'Точка Б', 
        botManager::TRAVEL_DATE_TIME_FIELD => 'Время поездки',
        botManager::ADDITIONAL_CONDITIONS_FIELD => 'Пассажиры',
        botManager::INTERMEDIATE_POINTS_FIELD => 'Промежуточные точки'
    ];
    
    $changes = [];
    foreach ($trackedFields as $fieldId => $fieldName) {
        $oldValue = $fields['OLD'][$fieldId] ?? null;
        $newValue = $fields[$fieldId] ?? null;
        
        if ($oldValue !== null && $newValue !== null && $oldValue !== $newValue) {
            $changes[$fieldName] = [
                'old' => $oldValue,
                'new' => $newValue
            ];
            echo "  ✅ $fieldName: '$oldValue' → '$newValue'\n";
        } else {
            echo "  - $fieldName: без изменений\n";
        }
    }
    
    if (empty($changes)) {
        echo "❌ Нет изменений для отслеживания\n";
    } else {
        echo "\n✅ Найдено " . count($changes) . " изменений\n";
        
        // Формируем уведомление
        $message = "🚗 Заявка #$dealId изменена:\n\n";
        
        foreach ($changes as $fieldName => $change) {
            $emoji = match($fieldName) {
                'Точка А' => '🅰️',
                'Точка Б' => '🅱️',
                'Время поездки' => '⏰',
                'Пассажиры' => '👥',
                'Промежуточные точки' => '📍',
                default => '📝'
            };
            
            $message .= "$emoji $fieldName: <s>{$change['old']}</s> ➔ {$change['new']}\n";
        }
        
        echo "\n3. Сформированное уведомление:\n";
        echo "---\n";
        echo $message;
        echo "---\n";
        
        echo "\n4. Chat ID для отправки: " . botManager::DRIVERS_GROUP_CHAT_ID . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЁН ===\n";
?>
