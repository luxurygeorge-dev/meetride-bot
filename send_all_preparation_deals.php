<?php
/**
 * Автоматическая отправка всех заявок в стадии PREPARATION
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Telegram\Bot\Api;
use Store\botManager;

echo "=== ОТПРАВКА ВСЕХ ЗАЯВОК В СТАДИИ PREPARATION ===\n\n";

// Подключаем CRest
require_once('/home/telegramBot/crest/crest.php');

// Получаем все заявки в стадии PREPARATION
$deals = \CRest::call('crm.deal.list', [
    'filter' => [
        'STAGE_ID' => 'PREPARATION'
    ],
    'select' => ['ID', 'TITLE', 'STAGE_ID']
])['result'];

if (empty($deals)) {
    echo "❌ Нет заявок в стадии PREPARATION\n";
    exit;
}

echo "✅ Найдено заявок: " . count($deals) . "\n\n";

// Инициализируем Telegram
$telegram = new Api('7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4');

$successCount = 0;
$failCount = 0;

foreach ($deals as $deal) {
    $dealId = $deal['ID'];
    $dealTitle = $deal['TITLE'] ?? "Заявка $dealId";
    
    echo "Обработка заявки $dealId ($dealTitle)...\n";
    
    // Отправляем заявку в общий чат с кнопками
    $success = botManager::newDealMessage($dealId, $telegram);
    
    if ($success) {
        echo "  ✅ Заявка $dealId отправлена\n";
        $successCount++;
    } else {
        echo "  ❌ Ошибка при отправке заявки $dealId\n";
        $failCount++;
    }
    
    // Пауза между отправками, чтобы не перегрузить Telegram API
    sleep(1);
}

echo "\n=== ОТПРАВКА ЗАВЕРШЕНА ===\n";
echo "Успешно отправлено: $successCount\n";
echo "Ошибок: $failCount\n";
?>






