<?php
/**
 * Ручная отправка заявки в общий чат
 */

echo "=== РУЧНАЯ ОТПРАВКА ЗАЯВКИ ===\n\n";

if (empty($argv[1])) {
    echo "📋 Использование: php send_deal_manually.php [DEAL_ID]\n";
    echo "📋 Пример: php send_deal_manually.php 609\n\n";
    exit;
}

$dealId = (int) $argv[1];

try {
    // Подключаем библиотеки
    include('vendor/autoload.php');
    require_once('/home/telegramBot/crest/crest.php');
    require_once('/root/meetride/botManager.php');
    
    echo "✅ Библиотеки подключены\n";
    
    // Инициализируем Telegram
    $telegram = new Longman\TelegramBot\Telegram('7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4');
    echo "✅ Telegram инициализирован\n";
    
    // Проверяем заявку
    $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
    
    if (empty($deal['ID'])) {
        echo "❌ Заявка $dealId не найдена\n";
        exit;
    }
    
    echo "📋 Заявка $dealId:\n";
    echo "   - ID: {$deal['ID']}\n";
    echo "   - Название: {$deal['TITLE']}\n";
    echo "   - Стадия: {$deal['STAGE_ID']}\n";
    echo "   - Назначенный водитель: " . ($deal[Store\botManager::DRIVER_ID_FIELD] ?: 'НЕ НАЗНАЧЕН') . "\n\n";
    
    // Отправляем сообщение
    echo "📤 Отправляем заявку в общий чат...\n";
    $success = Store\botManager::newDealMessage($dealId, $telegram);
    
    if ($success) {
        echo "✅ Заявка отправлена успешно!\n";
        echo "💡 Проверьте общий чат водителей\n";
    } else {
        echo "❌ Ошибка отправки заявки\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n=== Отправка завершена ===\n";
?>

