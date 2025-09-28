<?php
/**
 * Webhook для обработки callback кнопок от Telegram бота
 */

namespace Store;

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

require_once('vendor/autoload.php');
require_once('botManager.php');

// Токен бота для уведомлений и управления (НЕ для создания сделок!)
$bot_token = '7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4';

// Получаем входящие данные
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Логирование для отладки
$log_message = date('Y-m-d H:i:s') . " - Webhook получил: " . $input . "\n";
file_put_contents('/root/meetride/logs/webhook.log', $log_message, FILE_APPEND);

if (!$update) {
    http_response_code(400);
    exit('Invalid JSON');
}

try {
    $telegram = new Api($bot_token);
    $result = new Update($update);
    
    // Проверяем, есть ли callback query
    if ($result->callbackQuery) {
        $log_message = date('Y-m-d H:i:s') . " - Обработка callback: " . $result->callbackQuery->data . "\n";
        file_put_contents('/root/meetride/logs/webhook.log', $log_message, FILE_APPEND);
        
        // Используем существующий обработчик из botManager
        botManager::buttonHanlde($telegram, $result);
    }
    
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    $log_message = date('Y-m-d H:i:s') . " - Ошибка webhook: " . $e->getMessage() . "\n";
    file_put_contents('/root/meetride/logs/webhook.log', $log_message, FILE_APPEND);
    
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
?>
