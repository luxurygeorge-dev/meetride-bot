<?php
/**
 * Webhook для Bitrix24 - Ростов (CATEGORY 1)
 * Обработка исходящих вебхуков от стадий
 */

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем библиотеки
include(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../botManager.php');
require_once(__DIR__ . '/../CityConfigLoader.php');

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Store\botManager;
use Store\CityConfigLoader;

try {
    echo "OK - Webhook received (Rostov)\n";

    // Логируем все запросы
    $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] REQUEST: " . print_r($_REQUEST, true) . "\n";
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);

    // Получаем ID сделки из исходящего вебхука (document_id)
    $dealId = 0;

    // Формат исходящего вебхука: document_id = ['crm', 'CCrmDocumentDeal', 'DEAL_123']
    if (!empty($_REQUEST['document_id']) && is_array($_REQUEST['document_id'])) {
        $documentId = $_REQUEST['document_id'][2] ?? '';
        if (preg_match('/DEAL_(\d+)/', $documentId, $matches)) {
            $dealId = (int) $matches[1];
        }
    }

    if (empty($dealId)) {
        $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] No deal ID found\n";
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);
        http_response_code(400);
        exit('No deal ID');
    }

    $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Processing deal $dealId\n";
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);

    // Получаем данные о сделке
    require_once('/home/telegramBot/crest/crest.php');
    $deal = \CRest::call('crm.deal.get', [
        'id' => $dealId,
        'select' => ['*', botManager::GROUP_MESSAGE_SENT_FIELD]
    ])['result'];

    if (empty($deal['ID'])) {
        $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId not found\n";
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);
        http_response_code(404);
        exit('Deal not found');
    }

    // КРИТИЧНО: Проверяем что это сделка Ростова (CATEGORY 1)
    $categoryId = $deal['CATEGORY_ID'] ?? 0;
    if ($categoryId != 1) {
        $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId is not Rostov (CATEGORY $categoryId), skipping\n";
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);
        exit('Not a Rostov deal');
    }

    // Загружаем конфиг Ростова
    $cityConfig = CityConfigLoader::getByCategoryId(1);

    $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId stage: " . ($deal['STAGE_ID'] ?? 'UNKNOWN') . ", CATEGORY: $categoryId\n";
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);

    // Инициализируем Telegram с токеном Ростова
    $telegram = new Api($cityConfig['telegram']['notification_bot_token']);

    // Проверяем стадию C1:PREPARATION (Назначение водителя)
    if ($deal && $deal['STAGE_ID'] == 'C1:PREPARATION') {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
            date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId is C1:PREPARATION - sending to drivers\n", FILE_APPEND);

        // Проверяем, было ли уже отправлено сообщение в общий чат
        $groupMessageSent = $deal[botManager::GROUP_MESSAGE_SENT_FIELD] ?? '';

        // Timestamp-based deduplication: разрешаем повторную отправку если:
        // 1. Сообщение еще не отправлялось (пустое поле)
        // 2. Или предыдущая отправка была >30 сек назад (ручной возврат менеджером)
        // Это защищает от дублей при webhook-retry Bitrix24 (<30 сек)
        $shouldSend = true;
        if (!empty($groupMessageSent)) {
            $sentTime = strtotime($groupMessageSent);
            $shouldSend = ($sentTime === false || (time() - $sentTime) > 30);
        }

        if ($shouldSend) {
            echo "Deal $dealId is in C1:PREPARATION - sending to Rostov drivers\n";
            $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Sending deal $dealId to Rostov drivers\n";
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);

            // ИСПРАВЛЕНИЕ: Отправляем сообщение напрямую с правильным chat_id из конфига
            // вместо использования botManager::newDealMessage() который использует хардкоженный DRIVERS_GROUP_CHAT_ID

            // Получаем информацию о назначенном водителе
            $driver = null;
            if (!empty($deal[botManager::DRIVER_ID_FIELD])) {
                $driverResult = \CRest::call('crm.contact.get', [
                    'id' => $deal[botManager::DRIVER_ID_FIELD],
                    'select' => ['NAME', 'LAST_NAME']
                ]);
                $driver = $driverResult['result'] ?? null;
            }
            $driverName = '';
            if ($driver) {
                $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
            }

            // Создаем кнопки
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Принять', 'callback_data' => "accept_$dealId"],
                        ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]
                    ]
                ]
            ];

            // Формируем текст сообщения
            $messageText = botManager::orderTextForGroup($deal, $driverName);

            // Получаем chat_id из конфига Ростова
            $chatId = $cityConfig['telegram']['drivers_chat_id'];

            $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Sending to chat_id: $chatId\n";
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);

            // Отправляем сообщение
            try {
                $result = $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'reply_markup' => json_encode($keyboard),
                    'parse_mode' => 'HTML',
                ]);

                $success = $result && (method_exists($result, 'isOk') ? $result->isOk() : true);

                if ($success) {
                    echo "Deal $dealId sent to Rostov drivers chat successfully\n";
                    $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId sent successfully\n";

                    // Помечаем, что сообщение в общий чат отправлено
                    \CRest::call('crm.deal.update', [
                        'id' => $dealId,
                        'fields' => [
                            botManager::GROUP_MESSAGE_SENT_FIELD => date('Y-m-d H:i:s')
                        ]
                    ]);
                } else {
                    echo "Failed to send deal $dealId\n";
                    $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Failed to send deal $dealId\n";
                }

                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);
            } catch (Exception $e) {
                echo "ERROR sending message: " . $e->getMessage() . "\n";
                $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Error sending message: " . $e->getMessage() . "\n";
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);
            }
        } else {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
                date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId already sent to group ($groupMessageSent), skipping\n", FILE_APPEND);
            echo "Deal $dealId already sent to group\n";
        }
    } else {
        echo "Deal $dealId stage is: " . ($deal['STAGE_ID'] ?? 'UNKNOWN') . " (no action needed)\n";
        $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId stage " . ($deal['STAGE_ID'] ?? 'UNKNOWN') . " - no action\n";
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);
    }

    http_response_code(200);
    echo 'OK';

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    $log_message = date('Y-m-d H:i:s') . " - [ROSTOV] ERROR: " . $e->getMessage() . "\n";
    $log_message .= "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log', $log_message, FILE_APPEND);
    http_response_code(500);
}
?>
