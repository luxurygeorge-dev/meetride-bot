<?php
/**
 * Тестовый скрипт для отправки уведомления в Telegram
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;
use Telegram\Bot\Api;

echo "=== ТЕСТ ОТПРАВКИ УВЕДОМЛЕНИЯ ===\n\n";

try {
    // Создаем экземпляр бота
    $bot = new botManager();
    
    // Тестовые данные
    $dealId = 777;
    $driverId = 3; // ID водителя для тестов
    $changes = [
        'Точка А' => [
            'old' => 'Центр города',
            'new' => 'Аэропорт Пулково'
        ],
        'Точка Б' => [
            'old' => 'Центр',
            'new' => 'Московский вокзал'
        ],
        'Время поездки' => [
            'old' => '2025-10-05 14:00:00',
            'new' => '2025-10-05 15:30:00'
        ],
        'Пассажиры' => [
            'old' => '1 пассажир',
            'new' => '2 пассажира, багаж'
        ],
        'Промежуточные точки' => [
            'old' => 'Невский проспект',
            'new' => 'Невский проспект, Дворцовая площадь'
        ]
    ];
    
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
    
    echo "1. Сформированное уведомление:\n";
    echo "---\n";
    echo $message;
    echo "---\n\n";
    
    echo "2. Отправка в тестовый чат...\n";
    echo "Chat ID: " . botManager::DRIVERS_GROUP_CHAT_ID . "\n";
    
    // Отправляем уведомление
    $telegram = new Api('7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4');
    
    $response = $telegram->sendMessage([
        'chat_id' => botManager::DRIVERS_GROUP_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ]);
    
    if ($response->isOk()) {
        echo "✅ Уведомление отправлено успешно!\n";
        echo "Message ID: " . $response->getMessageId() . "\n";
    } else {
        echo "❌ Ошибка отправки: " . $response->getDescription() . "\n";
        echo "Response: " . print_r($response, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЁН ===\n";
?>
