<?php
/**
 * Ручная отправка заявки водителям
 * Использование: php send_deal.php <ID заявки>
 */

if ($argc < 2) {
    echo "Использование: php send_deal.php <ID заявки>\n";
    echo "Пример: php send_deal.php 787\n";
    exit(1);
}

$dealId = (int) $argv[1];

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Telegram\Bot\Api;
use Store\botManager;

echo "=== РУЧНАЯ ОТПРАВКА ЗАЯВКИ $dealId ===\n\n";

// Подключаем CRest
require_once('/home/telegramBot/crest/crest.php');

// Получаем заявку
$deal = \CRest::call('crm.deal.get', [
    'id' => $dealId,
    'select' => ['*']
])['result'];

if (empty($deal)) {
    echo "❌ Заявка $dealId не найдена!\n";
    exit(1);
}

echo "✅ Заявка найдена!\n";
echo "ID: " . $deal['ID'] . "\n";
echo "Название: " . ($deal['TITLE'] ?? 'Не указано') . "\n";
echo "Стадия: " . $deal['STAGE_ID'] . "\n\n";

if ($deal['STAGE_ID'] !== 'PREPARATION') {
    echo "⚠️  Внимание! Заявка не в стадии PREPARATION\n";
    echo "Текущая стадия: {$deal['STAGE_ID']}\n";
    echo "Рекомендуется изменить стадию на PREPARATION в Bitrix24\n\n";
    
    echo "Продолжить отправку? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim($line) != 'y') {
        echo "Отправка отменена\n";
        exit(0);
    }
    fclose($handle);
}

echo "Отправляем заявку водителям...\n";

// Инициализируем Telegram
$telegram = new Api('7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4');

// Отправляем заявку в общий чат с кнопками
$success = botManager::newDealMessage($dealId, $telegram);

if ($success) {
    echo "✅ Заявка $dealId успешно отправлена водителям!\n";
    exit(0);
} else {
    echo "❌ Ошибка при отправке заявки $dealId\n";
    exit(1);
}
?>


