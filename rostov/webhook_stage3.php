<?php
/**
 * Исходящий вебхук для третьей стадии (C1:PREPAYMENT_INVOICE) - Ростов
 * Отправляет сообщение водителю с кнопками "Начать выполнение"
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем библиотеки
include(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../botManager.php');
require_once(__DIR__ . '/../CityConfigLoader.php');

use Telegram\Bot\Api;
use Store\botManager;
use Store\CityConfigLoader;

try {
    // Логируем запрос
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
        date('Y-m-d H:i:s') . " - [ROSTOV] webhook_stage3.php called\n", FILE_APPEND);
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
        date('Y-m-d H:i:s') . " - [ROSTOV] REQUEST: " . print_r($_REQUEST, true) . "\n", FILE_APPEND);

    // Получаем ID сделки из разных форматов webhook
    $dealId = 0;

    // Формат 1: Исходящий вебхук от бизнес-процесса (document_id)
    if (!empty($_REQUEST['document_id']) && is_array($_REQUEST['document_id'])) {
        $documentId = $_REQUEST['document_id'][2] ?? '';
        if (preg_match('/DEAL_(\d+)/', $documentId, $matches)) {
            $dealId = (int) $matches[1];
        }
    }

    // Формат 2: Событие ONCRMDEALUPDATE
    if (empty($dealId) && !empty($_REQUEST['data']['FIELDS']['ID'])) {
        $dealId = (int) $_REQUEST['data']['FIELDS']['ID'];
    }

    if (empty($dealId)) {
        http_response_code(400);
        exit('No deal ID');
    }

    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
        date('Y-m-d H:i:s') . " - [ROSTOV] Processing deal $dealId from stage 3 webhook\n", FILE_APPEND);

    // Получаем данные о сделке
    require_once('/home/telegramBot/crest/crest.php');
    $deal = \CRest::call('crm.deal.get', [
        'id' => $dealId,
        'select' => ['*', botManager::DRIVER_ID_FIELD, botManager::DRIVER_TELEGRAM_ID_FIELD]
    ])['result'];

    if (empty($deal['ID'])) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
            date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId not found\n", FILE_APPEND);
        http_response_code(404);
        exit('Deal not found');
    }

    // КРИТИЧНО: Проверяем что это сделка Ростова (CATEGORY 1)
    $categoryId = $deal['CATEGORY_ID'] ?? 0;
    if ($categoryId != 1) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
            date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId is not Rostov (CATEGORY $categoryId), skipping\n", FILE_APPEND);
        exit('Not a Rostov deal');
    }

    // Загружаем конфиг Ростова
    $cityConfig = CityConfigLoader::getByCategoryId(1);

    // Проверяем стадию (должна быть C1:PREPAYMENT_INVOICE для Ростова)
    if ($deal['STAGE_ID'] !== 'C1:PREPAYMENT_INVOICE') {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
            date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId stage is {$deal['STAGE_ID']}, not C1:PREPAYMENT_INVOICE\n", FILE_APPEND);
        exit('OK - Wrong stage');
    }

    // Проверяем, есть ли назначенный водитель
    if (empty($deal[botManager::DRIVER_ID_FIELD]) || $deal[botManager::DRIVER_ID_FIELD] <= 0) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
            date('Y-m-d H:i:s') . " - [ROSTOV] No driver assigned for deal $dealId\n", FILE_APPEND);
        exit('OK - No driver');
    }

    // Проверяем, были ли уже инициализированы SERVICE поля (защита от дублирования)
    $serviceFieldsInitialized = !empty($deal[botManager::DRIVER_SUM_FIELD_SERVICE]) ||
                               !empty($deal[botManager::ADDRESS_FROM_FIELD_SERVICE]) ||
                               !empty($deal[botManager::ADDRESS_TO_FIELD_SERVICE]) ||
                               !empty($deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE]) ||
                               !empty($deal[botManager::PASSENGERS_FIELD_SERVICE]);

    if ($serviceFieldsInitialized) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
            date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId SERVICE fields already initialized, skipping message\n", FILE_APPEND);
        exit('OK - Already processed');
    }

    // Дополнительная проверка: если прошло менее 60 секунд с момента принятия заявки
    // (защита от автоматического принятия через кнопку)
    $currentTime = time();
    $updateTime = strtotime($_REQUEST['ts'] ?? 'now');
    $timeDiff = $currentTime - $updateTime;

    if ($timeDiff < 60) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
            date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId likely auto-accepted ($timeDiff seconds ago), checking if message already sent\n", FILE_APPEND);

        // Проверяем, было ли уже отправлено сообщение в личку водителю
        $messageSent = !empty($deal['UF_CRM_1759918565']); // GROUP_MESSAGE_SENT_FIELD

        if ($messageSent) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
                date('Y-m-d H:i:s') . " - [ROSTOV] Deal $dealId message already sent, skipping\n", FILE_APPEND);
            exit('OK - Message already sent');
        }
    }

    // Получаем Telegram ID водителя
    $driver = \CRest::call('crm.contact.get', [
        'id' => $deal[botManager::DRIVER_ID_FIELD],
        'select' => ['ID', botManager::DRIVER_TELEGRAM_ID_FIELD]
    ])['result'];

    if (empty($driver['ID']) || empty($driver[botManager::DRIVER_TELEGRAM_ID_FIELD])) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
            date('Y-m-d H:i:s') . " - [ROSTOV] Driver has no Telegram ID for deal $dealId\n", FILE_APPEND);
        exit('OK - No Telegram ID');
    }

    $driverTelegramId = (int) $driver[botManager::DRIVER_TELEGRAM_ID_FIELD];

    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
        date('Y-m-d H:i:s') . " - [ROSTOV] Sending private message to driver $driverTelegramId for deal $dealId\n", FILE_APPEND);

    // Инициализируем Telegram с токеном Ростова
    $telegram = new Api($cityConfig['telegram']['notification_bot_token']);

    // Отправляем сообщение в личку водителю с кнопками "Начать выполнение"
    botManager::sendPrivateMessageToDriver($dealId, $driverTelegramId, $telegram);

    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
        date('Y-m-d H:i:s') . " - [ROSTOV] webhook_stage3.php completed for deal $dealId\n", FILE_APPEND);

    http_response_code(200);
    echo "OK - Message sent to driver";

} catch (Exception $e) {
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
        date('Y-m-d H:i:s') . " - [ROSTOV] ERROR in webhook_stage3.php: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
}
?>
