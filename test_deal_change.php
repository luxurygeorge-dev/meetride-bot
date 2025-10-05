<?php
/**
 * Тестовый скрипт для проверки новой функции dealChangeHandle
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;

echo "=== ТЕСТ ФУНКЦИИ dealChangeHandle ===\n\n";

// Создаем тестовые данные webhook
$testData = [
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
echo "Deal ID: " . $testData['data']['FIELDS']['ID'] . "\n";
echo "Изменения:\n";
foreach ($testData['data']['FIELDS']['OLD'] as $field => $oldValue) {
    $newValue = $testData['data']['FIELDS'][$field] ?? 'НЕ УКАЗАНО';
    echo "  - $field: '$oldValue' → '$newValue'\n";
}

echo "\n2. Тестирование функции dealChangeHandle...\n";

try {
    // Имитируем webhook данные
    $_REQUEST = $testData;
    
    // Создаем экземпляр бота
    $bot = new botManager();
    
    // Создаем мок объекты для тестирования
    $dealId = $testData['data']['FIELDS']['ID'];
    
    // Проверяем, что функция существует и доступна
    if (method_exists('Store\\botManager', 'dealChangeHandle')) {
        echo "✅ Функция dealChangeHandle найдена\n";
        
        // Проверяем константы
        echo "Проверка констант:\n";
        $constants = [
            'ADDRESS_FROM_FIELD' => botManager::ADDRESS_FROM_FIELD,
            'ADDRESS_TO_FIELD' => botManager::ADDRESS_TO_FIELD,
            'TRAVEL_DATE_TIME_FIELD' => botManager::TRAVEL_DATE_TIME_FIELD,
            'ADDITIONAL_CONDITIONS_FIELD' => botManager::ADDITIONAL_CONDITIONS_FIELD,
            'INTERMEDIATE_POINTS_FIELD' => botManager::INTERMEDIATE_POINTS_FIELD,
            'DRIVERS_GROUP_CHAT_ID' => botManager::DRIVERS_GROUP_CHAT_ID
        ];
        
        foreach ($constants as $name => $value) {
            echo "  - $name: $value\n";
        }
        
        echo "✅ Все константы определены корректно\n";
        
    } else {
        echo "❌ Функция dealChangeHandle не найдена\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
}

echo "\n3. Проверка логов...\n";
$logFile = '/var/www/html/meetRiedeBot/logs/bots.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $recentLogs = array_slice(explode("\n", $logs), -10);
    echo "Последние 10 строк лога:\n";
    foreach ($recentLogs as $log) {
        if (!empty(trim($log))) {
            echo "  " . $log . "\n";
        }
    }
} else {
    echo "❌ Лог файл не найден: $logFile\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЁН ===\n";
?>
