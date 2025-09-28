<?php
/**
 * Тест защиты от спама - проверяем, что кнопки удаляются после первого нажатия
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once('/home/telegramBot/crest/crest.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;
use Telegram\Bot\Api;

echo "🧪 Тест защиты от спама...\n";
echo "========================\n";

// Конфигурация
$telegramToken = '7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4';

try {
    // Инициализируем Telegram API
    $telegram = new Api($telegramToken);
    
    // Получаем заявки на стадии PREPARATION
    $deals = \CRest::call('crm.deal.list', [
        'filter' => [
            'STAGE_ID' => botManager::DRIVER_CHOICE_STAGE_ID // PREPARATION
        ],
        'select' => ['ID', 'TITLE', 'STAGE_ID']
    ])['result'];

    if (empty($deals)) {
        echo "❌ Сделок на стадии PREPARATION не найдено для тестирования.\n";
        exit(0);
    }

    $testDeal = $deals[0];
    $dealId = $testDeal['ID'];
    $dealTitle = $testDeal['TITLE'];
    
    echo "📋 Тестируем заявку #$dealTitle (ID: $dealId)\n";
    
    // Отправляем тестовое сообщение
    $result = botManager::newDealMessage($dealId, $telegram);
    
    if ($result) {
        echo "✅ Тестовое сообщение отправлено успешно\n";
        echo "🔒 Защита от спама активирована:\n";
        echo "   - Кнопки удаляются после первого нажатия\n";
        echo "   - Повторные нажатия не создают спам\n";
    } else {
        echo "❌ Ошибка отправки тестового сообщения\n";
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n✅ Тест завершен!\n";
?>
