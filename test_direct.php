<?php
/**
 * Прямой тест функции dealChangeHandle
 */

require_once(__DIR__ . '/src/crest/crest.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;

echo "=== ПРЯМОЙ ТЕСТ dealChangeHandle ===\n\n";

// ID тестовой заявки
$testDealId = 671;

echo "1. Тестируем функцию dealChangeHandle для заявки #$testDealId...\n";

// Создаем мок объекты для тестирования
$telegram = new \Telegram\Bot\Api('7529690360:AAHjKqZqZqZqZqZqZqZqZqZqZqZqZqZqZqZ');
$result = new \Longman\TelegramBot\Entities\Update(['update_id' => 1]);

try {
    botManager::dealChangeHandle($testDealId, $telegram, $result);
    echo "✅ Функция выполнена успешно\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
?>
