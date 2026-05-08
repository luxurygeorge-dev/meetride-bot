<?php
/**
 * Webhook для обработки callback кнопок от Telegram бота - Ростов
 * Собственные обработчики кнопок (не использует botManager::buttonHanlde)
 * Причина: botManager проверяет DRIVER_CHOICE_STAGE_ID = 'PREPARATION' (без префикса),
 *           а у Ростова стадия C1:PREPARATION
 */

namespace Store;

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../botManager.php');
require_once(__DIR__ . '/../CityConfigLoader.php');

// Стадии Ростова (локальные константы с префиксом C1:)
const ROSTOV_STAGE_NEW       = 'C1:NEW';
const ROSTOV_STAGE_CHOICE    = 'C1:PREPARATION';

/**
 * Safe wrapper for answerCallbackQuery - never throws on 'query too old' errors
 */
function safeAnswerCallback($telegram, $result, string $text = '', bool $showAlert = false): void {
    try {
        $telegram->answerCallbackQuery([
            'callback_query_id' => $result->callbackQuery->id,
            'text' => $text,
            'show_alert' => $showAlert
        ]);
    } catch (\Exception $e) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_rostov.log',
            date('Y-m-d H:i:s') . ' - [ROSTOV] safeAnswerCallback failed (non-critical): ' . $e->getMessage() . "\n", FILE_APPEND);
    }
}
const ROSTOV_STAGE_ACCEPTED  = 'C1:PREPAYMENT_INVOICE';
const ROSTOV_STAGE_EXECUTING = 'C1:EXECUTING';
const ROSTOV_STAGE_FINISH    = 'C1:FINAL_INVOICE';

$LOG = '/var/www/html/meetRiedeBot/logs/webhook_rostov.log';

function rostovLog(string $msg): void {
    global $LOG;
    file_put_contents($LOG, date('Y-m-d H:i:s') . " - [ROSTOV] " . $msg . "\n", FILE_APPEND);
}

// Получаем входящие данные
$input = file_get_contents('php://input');
$update = json_decode($input, true);

rostovLog("Webhook получил: " . $input);

if (!$update) {
    http_response_code(400);
    exit('Invalid JSON');
}

try {
    $result = new Update($update);

    // Проверяем, есть ли callback query
    if (!$result->callbackQuery) {
        http_response_code(200);
        echo 'OK';
        exit;
    }

    $callbackData = $result->callbackQuery->data;
    rostovLog("Обработка callback: " . $callbackData);

    // Извлекаем dealId и action
    $buttonData = explode('_', $callbackData);
    $action = $buttonData[0];
    $dealId = (int) $buttonData[1];

    if (empty($dealId)) {
        rostovLog("Cannot extract deal ID from callback: $callbackData");
        http_response_code(400);
        exit('Invalid callback format');
    }

    // Загружаем CRest
    if (!class_exists("CRest")) {
        require_once('/home/telegramBot/crest/crest.php');
    }

    // Получаем сделку для определения города
    $deal = \CRest::call('crm.deal.get', [
        'id' => $dealId,
        'select' => ['ID', 'CATEGORY_ID', 'STAGE_ID']
    ])['result'];

    if (empty($deal['ID'])) {
        rostovLog("Deal $dealId not found");
        http_response_code(404);
        exit('Deal not found');
    }

    // Проверяем что это сделка Ростова (CATEGORY 1)
    $categoryId = $deal['CATEGORY_ID'] ?? 0;
    if ($categoryId != 1) {
        rostovLog("Deal $dealId is not Rostov (CATEGORY $categoryId), skipping");
        // Временный telegram для ответа
        $tempTelegram = new Api('8078436969:AAFfYA_t1f9bs8sM4ZttNYLho9woH6BUe9I');
        safeAnswerCallback($telegram, $result, 'Это не заявка Ростова', true);
        exit('Not a Rostov deal');
    }

    // Загружаем конфиг Ростова
    $cityConfig = CityConfigLoader::getByCategoryId(1);
    $groupChatId = $cityConfig['telegram']['drivers_chat_id'];

    rostovLog("Deal $dealId confirmed as Rostov (CATEGORY $categoryId)");

    // Инициализируем Telegram
    $telegram = new Api($cityConfig['telegram']['notification_bot_token']);

    // Получаем полную сделку (все поля)
    $deal = \CRest::call('crm.deal.get', [
        'id' => $dealId,
        'select' => ['*', botManager::PASSENGERS_FIELD, botManager::FLIGHT_NUMBER_FIELD,
                     botManager::INTERMEDIATE_POINTS_FIELD, botManager::CAR_CLASS_FIELD]
    ])['result'];

    $chatId = $result->callbackQuery->getMessage()->getChat()->getId();
    $message = $result->callbackQuery->getMessage();
    $telegramId = $result->callbackQuery->from->id;
    $isGroupChat = ($chatId == $groupChatId);

    // Заблокированные стадии
    $stage = $deal['STAGE_ID'] ?? '';
    if ($stage == ROSTOV_STAGE_FINISH || $stage == 'C1:LOSE' || $stage == 'C1:WON') {
        rostovLog("Deal $dealId is BLOCKED (stage: $stage)");
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Заявка недоступна',
        ]);
        safeAnswerCallback($telegram, $result);
        exit;
    }

    // Защита от повторного принятия в групповом чате
    if (($stage == ROSTOV_STAGE_ACCEPTED || $stage == ROSTOV_STAGE_EXECUTING) && $isGroupChat && $action == 'accept') {
        rostovLog("Deal $dealId already accepted (stage: $stage), blocking in group");
        $driverName = 'Водитель';
        if (!empty($deal[botManager::DRIVER_ID_FIELD])) {
            $driverContact = \CRest::call('crm.contact.get', [
                'id' => $deal[botManager::DRIVER_ID_FIELD],
                'select' => ['NAME', 'LAST_NAME']
            ])['result'];
            if ($driverContact) {
                $driverName = trim($driverContact['NAME'] . ' ' . $driverContact['LAST_NAME']);
            }
        }
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Заявку уже принял водитель: <b>$driverName</b>",
            'parse_mode' => 'HTML'
        ]);
        safeAnswerCallback($telegram, $result, 'Заявка уже была принята ранее.', true);
        exit;
    }

    rostovLog("Button action: $action for deal $dealId (stage: $stage)");

    // Диспетчер кнопок
    switch ($action) {
        case 'accept':
            rostovAcceptHandle($dealId, $deal, $chatId, $groupChatId, $message, $telegramId, $result, $telegram);
            break;
        case 'reject':
            rostovRejectHandle($dealId, $deal, $chatId, $groupChatId, $message, $telegramId, $result, $telegram);
            break;
        case 'start':
            rostovStartHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        case 'startYes':
            rostovStartYesHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        case 'startNo':
            rostovStartNoHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        case 'cancel':
            rostovCancelHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        case 'cancelYes':
            rostovCancelYesHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        case 'cancelNo':
            rostovCancelNoHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        case 'finish':
            rostovFinishHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        case 'finishYes':
            rostovFinishYesHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        case 'finishNo':
            rostovFinishNoHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        case 'confirm':
            rostovConfirmReminderHandle($dealId, $deal, $chatId, $message, $telegramId, $result, $telegram);
            break;
        default:
            rostovLog("Unknown action: $action");
    }

    rostovLog("Callback обработан успешно");
    http_response_code(200);
    echo 'OK';

} catch (Exception $e) {
    rostovLog("Ошибка webhook: " . $e->getMessage() . " Файл: " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}

// =====================================================================
// ОБРАБОТЧИКИ КНОПОК
// =====================================================================

/**
 * Принятие заявки из группового чата
 */
function rostovAcceptHandle(int $dealId, array $deal, $chatId, string $groupChatId, $message, int $telegramId, Update $result, Api $telegram): void {
    $stage = $deal['STAGE_ID'] ?? '';

    // Проверяем стадию
    if ($stage != ROSTOV_STAGE_CHOICE) {
        rostovLog("rostovAcceptHandle: Unexpected stage for deal $dealId: $stage");
        safeAnswerCallback($telegram, $result, 'Заявка находится в неожиданной стадии. Обратитесь к менеджеру.', true);
        return;
    }

    // Находим водителя по Telegram ID (проверку "только назначенный" не делаем - водители сами договариваются)
    $drivers = \CRest::call('crm.contact.list', [
        'filter' => [\Store\botManager::DRIVER_TELEGRAM_ID_FIELD => $telegramId],
        'select' => ['ID', 'NAME', 'LAST_NAME']
    ]);
    $driver = $drivers['result'][0] ?? null;

    // Подтверждаем callback
    try {
        safeAnswerCallback($telegram, $result, 'Заявка принята! Отправляем детали...');
    } catch (Exception $e) {
        rostovLog("rostovAcceptHandle: Error answering callback: " . $e->getMessage());
    }

    // Определяем водителя и имя
    if ($driver) {
        $driverId = $driver['ID'];
        $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
    } else {
        // Незарегистрированный водитель - назначаем контакт ID 9
        $driverId = 9;
        $firstName = $result->callbackQuery->from->first_name ?? '';
        $lastName = $result->callbackQuery->from->last_name ?? '';
        $driverName = trim("$firstName $lastName") ?: 'Водитель';
        rostovLog("rostovAcceptHandle: Unregistered driver $telegramId ($driverName), assigning contact ID 9");
    }

    // Обновляем сделку: назначаем водителя, меняем стадию, инициализируем SERVICE поля
    \CRest::call('crm.deal.update', [
        'id' => $dealId,
        'fields' => [
            \Store\botManager::DRIVER_ID_FIELD                     => $driverId,
            'STAGE_ID'                                             => ROSTOV_STAGE_ACCEPTED,
            \Store\botManager::DRIVER_SUM_FIELD_SERVICE            => $deal[\Store\botManager::DRIVER_SUM_FIELD],
            \Store\botManager::ADDRESS_FROM_FIELD_SERVICE          => $deal[\Store\botManager::ADDRESS_FROM_FIELD],
            \Store\botManager::ADDRESS_TO_FIELD_SERVICE            => $deal[\Store\botManager::ADDRESS_TO_FIELD],
            \Store\botManager::TRAVEL_DATE_TIME_FIELD_SERVICE      => $deal[\Store\botManager::TRAVEL_DATE_TIME_FIELD],
            \Store\botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE => $deal[\Store\botManager::ADDITIONAL_CONDITIONS_FIELD],
            \Store\botManager::PASSENGERS_FIELD_SERVICE            => $deal[\Store\botManager::PASSENGERS_FIELD],
            \Store\botManager::FLIGHT_NUMBER_FIELD_SERVICE         => $deal[\Store\botManager::FLIGHT_NUMBER_FIELD],
            \Store\botManager::CAR_CLASS_FIELD_SERVICE             => $deal[\Store\botManager::CAR_CLASS_FIELD],
            \Store\botManager::INTERMEDIATE_POINTS_FIELD_SERVICE   => $deal[\Store\botManager::INTERMEDIATE_POINTS_FIELD],
            \Store\botManager::ORDER_NUMBER_SERVICE_FIELD          => \Store\botManager::extractOrderNumber($deal['TITLE'] ?? '')
        ]
    ]);

    rostovLog("rostovAcceptHandle: Deal $dealId updated to ROSTOV_STAGE_ACCEPTED, driver ID: $driverId");

    // Перезагружаем сделку
    rostovLog("rostovAcceptHandle: Reloading deal $dealId");
    $deal = \CRest::call('crm.deal.get', [
        'id' => $dealId,
        'select' => ['*', \Store\botManager::PASSENGERS_FIELD, \Store\botManager::FLIGHT_NUMBER_FIELD, \Store\botManager::CAR_CLASS_FIELD]
    ])['result'];
    rostovLog("rostovAcceptHandle: Deal reloaded, title=" . ($deal['TITLE'] ?? 'NO_TITLE'));

    // Номер заявки
    $orderNumber = $deal['TITLE'] ?? $dealId;
    if (strpos($orderNumber, 'Заявка: ') === 0) {
        $orderNumber = substr($orderNumber, 8);
    }

    // Сообщение в групповой чат
    rostovLog("rostovAcceptHandle: Sending group notification to $chatId");
    $groupMessage = "Заявку #$orderNumber взял водитель: <b>$driverName</b>";
    try {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $groupMessage,
            'parse_mode' => 'HTML'
        ]);
    } catch (Exception $e) {
        rostovLog("rostovAcceptHandle: Error sending group message: " . $e->getMessage());
    }
    rostovLog("rostovAcceptHandle: Group notification sent");

    // Убираем кнопки с исходного сообщения
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ]);
    } catch (Exception $e) {
        rostovLog("rostovAcceptHandle: Error removing buttons: " . $e->getMessage());
    }

    if ($driver) {
        // Отправляем детали в личку зарегистрированному водителю
        $detailedMessage = \Store\botManager::orderTextForDriver($deal);
        $privateKeyboard = [
            'inline_keyboard' => [[
                ['text' => 'Начать выполнение', 'callback_data' => "start_$dealId"],
                ['text' => 'Отказаться', 'callback_data' => "reject_$dealId"]
            ]]
        ];
        try {
            $telegram->sendMessage([
                'chat_id' => $telegramId,
                'text' => $detailedMessage,
                'reply_markup' => json_encode($privateKeyboard),
                'parse_mode' => 'HTML'
            ]);
        } catch (Exception $e) {
            rostovLog("rostovAcceptHandle: Error sending private message: " . $e->getMessage());
        }
    } else {
        // Уведомляем ответственного о незарегистрированном водителе
        \CRest::call('im.notify.system.add', [
            'USER_ID' => $deal['ASSIGNED_BY_ID'],
            'MESSAGE' => "Заявку #$orderNumber взял незарегистрированный водитель: $driverName (Telegram ID: $telegramId). " .
                         "<a href='https://meetride.bitrix24.ru/crm/deal/details/$dealId/'>Открыть заявку</a>"
        ]);
    }

    rostovLog("rostovAcceptHandle: Completed for deal $dealId");
}

/**
 * Отказ водителя от заявки
 */
function rostovRejectHandle(int $dealId, array $deal, $chatId, string $groupChatId, $message, int $telegramId, Update $result, Api $telegram): void {
    $orderNumber = $deal['TITLE'] ?? $dealId;
    if (strpos($orderNumber, 'Заявка: ') === 0) {
        $orderNumber = substr($orderNumber, 8);
    }

    $firstName = $result->callbackQuery->from->first_name ?? '';
    $lastName = $result->callbackQuery->from->last_name ?? '';
    $driverName = trim("$firstName $lastName") ?: 'Водитель';

    safeAnswerCallback($telegram, $result, 'Вы отказались от заявки.');

    // Сообщение в групповой чат
    try {
        $telegram->sendMessage([
            'chat_id' => $groupChatId,
            'text' => "<b>$driverName</b> отказался от заявки #$orderNumber",
            'parse_mode' => 'HTML'
        ]);
    } catch (Exception $e) {
        rostovLog("rostovRejectHandle: Error sending group message: " . $e->getMessage());
    }

    // Обновляем сделку: очищаем водителя, возвращаем на C1:PREPARATION
    \CRest::call('crm.deal.update', [
        'id' => $dealId,
        'fields' => [
            \Store\botManager::DRIVER_ID_FIELD => 0,
            'STAGE_ID' => ROSTOV_STAGE_CHOICE,
            \Store\botManager::GROUP_MESSAGE_SENT_FIELD => '',
            \Store\botManager::DRIVER_SUM_FIELD_SERVICE => '',
            \Store\botManager::ADDRESS_FROM_FIELD_SERVICE => '',
            \Store\botManager::ADDRESS_TO_FIELD_SERVICE => '',
            \Store\botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => '',
            \Store\botManager::PASSENGERS_FIELD_SERVICE => '',
            \Store\botManager::INTERMEDIATE_POINTS_FIELD_SERVICE => '',
            \Store\botManager::CAR_CLASS_FIELD_SERVICE => '',
        ]
    ]);

    // Убираем кнопки из личного чата
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ]);
    } catch (Exception $e) {
        rostovLog("rostovRejectHandle: Error removing buttons: " . $e->getMessage());
    }

    rostovLog("rostovRejectHandle: Deal $dealId rejected by $driverName");
}

/**
 * "Начать выполнение" - показываем подтверждение
 */
function rostovStartHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    $stage = $deal['STAGE_ID'] ?? '';
    if ($stage != ROSTOV_STAGE_ACCEPTED) {
        rostovLog("rostovStartHandle: Wrong stage for deal $dealId: $stage");
        safeAnswerCallback($telegram, $result, 'Нельзя начать выполнение на этой стадии.', true);
        return;
    }

    safeAnswerCallback($telegram, $result);

    $keyboard = [
        'inline_keyboard' => [[
            ['text' => 'Да, начинаю', 'callback_data' => "startYes_$dealId"],
            ['text' => 'Нет', 'callback_data' => "startNo_$dealId"]
        ]]
    ];
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode($keyboard)
        ]);
    } catch (Exception $e) {
        rostovLog("rostovStartHandle: Error editing markup: " . $e->getMessage());
    }

    rostovLog("rostovStartHandle: Confirmation shown for deal $dealId");
}

/**
 * Подтверждение начала выполнения
 */
function rostovStartYesHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    safeAnswerCallback($telegram, $result, 'Выполнение начато!');

    \CRest::call('crm.deal.update', [
        'id' => $dealId,
        'fields' => ['STAGE_ID' => ROSTOV_STAGE_EXECUTING]
    ]);

    $keyboard = [
        'inline_keyboard' => [[
            ['text' => 'Завершить', 'callback_data' => "finish_$dealId"],
            ['text' => 'Отменить', 'callback_data' => "cancel_$dealId"]
        ]]
    ];
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode($keyboard)
        ]);
    } catch (Exception $e) {
        rostovLog("rostovStartYesHandle: Error editing markup: " . $e->getMessage());
    }

    rostovLog("rostovStartYesHandle: Deal $dealId moved to EXECUTING");
}

/**
 * Отмена начала (остаёмся на ACCEPTED)
 */
function rostovStartNoHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    safeAnswerCallback($telegram, $result);

    $keyboard = [
        'inline_keyboard' => [[
            ['text' => 'Начать выполнение', 'callback_data' => "start_$dealId"],
            ['text' => 'Отказаться', 'callback_data' => "reject_$dealId"]
        ]]
    ];
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode($keyboard)
        ]);
    } catch (Exception $e) {
        rostovLog("rostovStartNoHandle: Error editing markup: " . $e->getMessage());
    }

    rostovLog("rostovStartNoHandle: Deal $dealId start cancelled");
}

/**
 * "Отменить выполнение" - показываем подтверждение
 */
function rostovCancelHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    safeAnswerCallback($telegram, $result);

    $keyboard = [
        'inline_keyboard' => [[
            ['text' => 'Да, отменить', 'callback_data' => "cancelYes_$dealId"],
            ['text' => 'Нет', 'callback_data' => "cancelNo_$dealId"]
        ]]
    ];
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode($keyboard)
        ]);
    } catch (Exception $e) {
        rostovLog("rostovCancelHandle: Error editing markup: " . $e->getMessage());
    }

    rostovLog("rostovCancelHandle: Cancellation confirmation shown for deal $dealId");
}

/**
 * Подтверждение отмены выполнения
 */
function rostovCancelYesHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    safeAnswerCallback($telegram, $result, 'Выполнение отменено.');

    \CRest::call('crm.deal.update', [
        'id' => $dealId,
        'fields' => ['STAGE_ID' => ROSTOV_STAGE_ACCEPTED]
    ]);

    $keyboard = [
        'inline_keyboard' => [[
            ['text' => 'Начать выполнение', 'callback_data' => "start_$dealId"],
            ['text' => 'Отказаться', 'callback_data' => "reject_$dealId"]
        ]]
    ];
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode($keyboard)
        ]);
    } catch (Exception $e) {
        rostovLog("rostovCancelYesHandle: Error editing markup: " . $e->getMessage());
    }

    $orderNumber = $deal['TITLE'] ?? $dealId;
    if (strpos($orderNumber, 'Заявка: ') === 0) {
        $orderNumber = substr($orderNumber, 8);
    }
    \CRest::call('im.notify.system.add', [
        'USER_ID' => $deal['ASSIGNED_BY_ID'],
        'MESSAGE' => "Водитель отменил выполнение заявки #$orderNumber. " .
                     "<a href='https://meetride.bitrix24.ru/crm/deal/details/$dealId/'>Открыть заявку</a>"
    ]);

    rostovLog("rostovCancelYesHandle: Deal $dealId execution cancelled, returned to ACCEPTED");
}

/**
 * Отмена отмены (остаёмся на EXECUTING)
 */
function rostovCancelNoHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    safeAnswerCallback($telegram, $result);

    $keyboard = [
        'inline_keyboard' => [[
            ['text' => 'Завершить', 'callback_data' => "finish_$dealId"],
            ['text' => 'Отменить', 'callback_data' => "cancel_$dealId"]
        ]]
    ];
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode($keyboard)
        ]);
    } catch (Exception $e) {
        rostovLog("rostovCancelNoHandle: Error editing markup: " . $e->getMessage());
    }

    rostovLog("rostovCancelNoHandle: Deal $dealId cancel aborted, staying EXECUTING");
}

/**
 * "Завершить" - показываем подтверждение
 */
function rostovFinishHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    safeAnswerCallback($telegram, $result);

    $keyboard = [
        'inline_keyboard' => [[
            ['text' => 'Да, завершить', 'callback_data' => "finishYes_$dealId"],
            ['text' => 'Нет', 'callback_data' => "finishNo_$dealId"]
        ]]
    ];
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode($keyboard)
        ]);
    } catch (Exception $e) {
        rostovLog("rostovFinishHandle: Error editing markup: " . $e->getMessage());
    }

    rostovLog("rostovFinishHandle: Finish confirmation shown for deal $dealId");
}

/**
 * Подтверждение завершения
 */
function rostovFinishYesHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    safeAnswerCallback($telegram, $result, 'Заявка завершена!');

    \CRest::call('crm.deal.update', [
        'id' => $dealId,
        'fields' => ['STAGE_ID' => ROSTOV_STAGE_FINISH]
    ]);

    try {
        $oldText = $message->getText() ?? '';
        $newText = $oldText . "\n\nЗАЯВКА ВЫПОЛНЕНА";
        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'text' => $newText,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ]);
    } catch (Exception $e) {
        rostovLog("rostovFinishYesHandle: Error editing message: " . $e->getMessage());
        try {
            $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);
        } catch (Exception $e2) {
            rostovLog("rostovFinishYesHandle: Error removing buttons: " . $e2->getMessage());
        }
    }

    rostovLog("rostovFinishYesHandle: Deal $dealId moved to FINAL_INVOICE");
}

/**
 * Отмена завершения (остаёмся на EXECUTING)
 */
function rostovFinishNoHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    safeAnswerCallback($telegram, $result);

    $keyboard = [
        'inline_keyboard' => [[
            ['text' => 'Завершить', 'callback_data' => "finish_$dealId"],
            ['text' => 'Отменить', 'callback_data' => "cancel_$dealId"]
        ]]
    ];
    try {
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode($keyboard)
        ]);
    } catch (Exception $e) {
        rostovLog("rostovFinishNoHandle: Error editing markup: " . $e->getMessage());
    }

    rostovLog("rostovFinishNoHandle: Deal $dealId finish aborted, staying EXECUTING");
}

/**
 * Подтверждение напоминания
 */
function rostovConfirmReminderHandle(int $dealId, array $deal, $chatId, $message, int $telegramId, Update $result, Api $telegram): void {
    safeAnswerCallback($telegram, $result, 'Подтверждено!');

    \CRest::call('crm.deal.update', [
        'id' => $dealId,
        'fields' => [
            \Store\botManager::REMINDER_CONFIRMED_FIELD => date('Y-m-d H:i:s')
        ]
    ]);

    try {
        $oldText = $message->getText() ?? '';
        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'text' => $oldText . "\n\nПодтверждено",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ]);
    } catch (Exception $e) {
        rostovLog("rostovConfirmReminderHandle: Error editing message: " . $e->getMessage());
    }

    rostovLog("rostovConfirmReminderHandle: Deal $dealId reminder confirmed");
}
