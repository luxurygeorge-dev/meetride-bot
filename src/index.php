<?php
/**
 * Webhook для Bitrix24 - с полной функциональностью
 */

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем библиотеки
include(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../botManager.php');

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Store\botManager;

try {
    echo "OK - Webhook received\n";
    
    // Логируем все запросы
    $log_message = date('Y-m-d H:i:s') . " - REQUEST: " . print_r($_REQUEST, true) . "\n";
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
    
    // Дополнительная отладка
    $log_message = date('Y-m-d H:i:s') . " - Event check: " . (isset($_REQUEST['event']) ? $_REQUEST['event'] : 'NO_EVENT') . "\n";
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
    
    // ТОЛЬКО Bitrix24 webhooks (event есть ТОЛЬКО у Bitrix24)
    if (!empty($_REQUEST['event']) && $_REQUEST['event'] == 'ONCRMDEALUPDATE' && !empty($_REQUEST['data']['FIELDS']['ID'])) {
        $log_message = date('Y-m-d H:i:s') . " - ONCRMDEALUPDATE event detected (handler: " . ($_REQUEST['event_handler_id'] ?? 'unknown') . ")\n";
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
        
        // Проверка токена безопасности (опционально)
        $expectedToken = 'ancn7qxhagfsifwxkcoo7s04tfc8q2ez';
        $receivedToken = $_REQUEST['auth']['application_token'] ?? '';
        if (!empty($expectedToken) && $receivedToken !== $expectedToken) {
            $log_message = date('Y-m-d H:i:s') . " - Invalid token received: $receivedToken\n";
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
            http_response_code(403);
            exit('Invalid token');
        }
        
        // Обрабатываем только события от handler_id = 3 (избегаем дублирования)
        if ($_REQUEST['event_handler_id'] != '3') {
            $log_message = date('Y-m-d H:i:s') . " - Skipping handler " . $_REQUEST['event_handler_id'] . "\n";
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
            exit;
        }
        
        $dealId = (int) $_REQUEST['data']['FIELDS']['ID'];
        echo "Processing deal update: $dealId\n";
        
        // Получаем данные о сделке
        require_once('/home/telegramBot/crest/crest.php');
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        
        $log_message = date('Y-m-d H:i:s') . " - Deal $dealId stage: " . ($deal['STAGE_ID'] ?? 'UNKNOWN') . "\n";
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
        
        // Инициализируем Telegram для всех операций
        $telegram = new Api('7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4');
        $update = new Update($_REQUEST);
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Starting stage check for deal $dealId\n", FILE_APPEND);
        
        if ($deal && $deal['STAGE_ID'] == 'PREPARATION') {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal $dealId is PREPARATION\n", FILE_APPEND);
            echo "Deal $dealId is in PREPARATION stage - sending to drivers\n";
            $log_message = date('Y-m-d H:i:s') . " - Sending deal $dealId to drivers\n";
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
            
            // Отправляем заявку в общий чат с кнопками
            $success = botManager::newDealMessage($dealId, $telegram);
            
            if ($success) {
                echo "Deal $dealId sent to drivers chat successfully\n";
                $log_message = date('Y-m-d H:i:s') . " - Deal $dealId sent successfully\n";
            } else {
                echo "Failed to send deal $dealId\n";
                $log_message = date('Y-m-d H:i:s') . " - Failed to send deal $dealId\n";
            }
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
            
        } elseif ($deal && $deal['STAGE_ID'] == 'PREPAYMENT_INVOICE') {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal $dealId is PREPAYMENT_INVOICE\n", FILE_APPEND);
            
            // ЭТОТ БЛОК КОДА ОТКЛЮЧЕН - уведомление водителю отправляется из botManager::driverAcceptHandle()
            // Здесь не нужно дублировать отправку, это вызывает спам
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Skipping notification (handled by driverAcceptHandle)\n", FILE_APPEND);
            
            // Также проверяем изменения в полях (как было раньше)
            echo "Deal $dealId stage is: " . $deal['STAGE_ID'] . " - checking for field changes\n";
            $log_message = date('Y-m-d H:i:s') . " - Checking for field changes in deal $dealId (stage: " . $deal['STAGE_ID'] . ")\n";
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
            
            // Получаем OLD значения из webhook
            $oldValues = $_REQUEST['data']['FIELDS']['OLD'] ?? null;

            // Вызываем обработку изменений
            botManager::dealChangeHandle($dealId, $telegram, $update, $oldValues);

            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - dealChangeHandle completed for deal $dealId\n", FILE_APPEND);

        } elseif ($deal && $deal['STAGE_ID'] == 'EXECUTING') {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal $dealId is EXECUTING\n", FILE_APPEND);
            // Проверяем изменения в полях для стадии "Заявка выполняется"
            echo "Deal $dealId stage is: " . $deal['STAGE_ID'] . " - checking for field changes\n";
            $log_message = date('Y-m-d H:i:s') . " - Checking for field changes in deal $dealId (stage: " . $deal['STAGE_ID'] . ")\n";
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);

            // Получаем OLD значения из webhook
            $oldValues = $_REQUEST['data']['FIELDS']['OLD'] ?? null;

            // Вызываем обработку изменений
            botManager::dealChangeHandle($dealId, $telegram, $update, $oldValues);
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - dealChangeHandle completed for deal $dealId\n", FILE_APPEND);
            
        } else {
            echo "Deal $dealId stage is: " . ($deal['STAGE_ID'] ?? 'UNKNOWN') . " (no action needed)\n";
        }
        exit;
    }
    
    // Старая логика для прямых вызовов с dealId и stage
    if (!empty($_REQUEST['dealId']) && !empty($_REQUEST['stage'])) {
        
        $dealId = (int) $_REQUEST['dealId'];
        $stage = $_REQUEST['stage'];
        echo "Processing deal: $dealId, stage: $stage\n";
        
        $log_message = date('Y-m-d H:i:s') . " - Webhook call: dealId=$dealId, stage=$stage\n";
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
        
        // Инициализируем Telegram
        $telegram = new Api('7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4');
        
        // Отправляем заявку в общий чат с кнопками
        $success = botManager::newDealMessage($dealId, $telegram);
        
        if ($success) {
            echo "Deal $dealId sent to drivers chat successfully\n";
            $log_message = date('Y-m-d H:i:s') . " - Deal $dealId sent successfully\n";
        } else {
            echo "Failed to send deal $dealId\n";
            $log_message = date('Y-m-d H:i:s') . " - Failed to send deal $dealId\n";
        }
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
        
        http_response_code(200);
        exit('OK - Manual trigger processed');
    }
    
    // Если сюда попал - неизвестный запрос
    http_response_code(400);
    echo "Bad Request - Use webhook.php for Telegram callbacks";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    $log_message = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
}
?>