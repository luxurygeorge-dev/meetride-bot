<?php

namespace Store;

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Collection;


require_once(__DIR__ . '/vendor/autoload.php');

class botManager {
//callback pattern =   action_dealId
    public const DRIVER_ID_FIELD                = 'UF_CRM_1751272181';
    public const DRIVER_TELEGRAM_ID_FIELD       = 'UF_CRM_1751185017761';
    public const ADDRESS_FROM_FIELD             = 'UF_CRM_1751269147414';
    public const ADDRESS_FROM_FIELD_SERVICE     = 'UF_CRM_1751638512'; // Исправлено на правильное поле
    public const ADDRESS_TO_FIELD               = 'UF_CRM_1751269175432';
    public const ADDRESS_TO_FIELD_SERVICE       = 'UF_CRM_1751638529';
    public const ADDITIONAL_CONDITIONS_FIELD    = 'UF_CRM_1751269256380';
    public const FLIGHT_NUMBER_FIELD            = 'UF_CRM_1751271774391'; // Номер рейса
    public const CAR_CLASS_FIELD                = 'UF_CRM_1751271728682'; // Класс автомобиля
    public const DRIVER_SUM_FIELD               = 'UF_CRM_1751271862251';
    public const DRIVER_SUM_FIELD_SERVICE       = 'UF_CRM_1751638441407';
    public const TRAVEL_DATE_TIME_FIELD         = 'UF_CRM_1751269222959';
    public const TRAVEL_DATE_TIME_FIELD_SERVICE = 'UF_CRM_1751638617';
    public const ADDITIONAL_CONDITIONS_FIELD_SERVICE = 'UF_CRM_1758709126'; // REMINDER_SENT_FIELD (используем как SERVICE)
    public const PASSENGERS_FIELD_SERVICE = 'UF_CRM_1758709139'; // REMINDER_CONFIRMED_FIELD (используем как SERVICE)
    public const FLIGHT_NUMBER_FIELD_SERVICE = 'UF_CRM_1758710216'; // REMINDER_NOTIFICATION_SENT_FIELD (используем как SERVICE)
    public const CAR_CLASS_FIELD_SERVICE = 'UF_CRM_1751271841129'; // HIDDEN_FIELD (используем как SERVICE)
    public const DRIVER_ACCEPTED_STAGE_ID       = 'PREPAYMENT_INVOICE'; // Водитель взял заявку
    public const NEW_DEAL_STAGE_ID              = 'NEW';
    public const DRIVER_CHOICE_STAGE_ID         = 'PREPARATION';
    public const TRAVEL_STARTED_STAGE_ID         = 'EXECUTING'; // Заявка выполняется
    public const FINISH_STAGE_ID         = 'FINAL_INVOICE';
    public const DRIVER_CONTACT_TYPE            = 'UC_C7O5J7';
    public const DRIVERS_GROUP_CHAT_ID = '-1002544521661'; // БОЕВОЙ режим'; // ТЕСТОВЫЙ режим'; // БОЕВОЙ режим'; // ТЕСТОВЫЙ режим; // ТЕСТОВАЯ группа водителей (НЕ МЕНЯТЬ НА БОЕВУЮ!)
    
    // Поля для системы напоминаний (исправленные ID)
    public const REMINDER_SENT_FIELD            = 'UF_CRM_1758709126';
    public const REMINDER_CONFIRMED_FIELD       = 'UF_CRM_1758709139';
    public const REMINDER_NOTIFICATION_SENT_FIELD = 'UF_CRM_1758710216';

    public static function newDealMessage(int $dealid, $telegram): bool {
        require_once('/home/telegramBot/crest/crest.php');
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealid,
            'select' => ['*', botManager::CAR_CLASS_FIELD] // Получаем все поля включая TITLE и класс авто
        ])['result'];
        if(empty($deal['ID'])) {
            return false;
        }
        
        // Получаем информацию о назначенном водителе для отображения в сообщении
        $driver = null;
        if (!empty($deal[botManager::DRIVER_ID_FIELD])) {
            $driverResult = \CRest::call('crm.contact.get', ['id' => $deal[botManager::DRIVER_ID_FIELD], 'select' => ['NAME', 'LAST_NAME']]);
            $driver = $driverResult['result'] ?? null;
        }
        $driverName = '';
        if($driver) {
            $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
        }
        
        // Кнопки доступны всем водителям
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Принять', 'callback_data' => "accept_$dealid"],
                    ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealid"]
                ]
            ]
        ];

        // Отправляем в общий чат водителей (БЕЗ пассажиров!)
        try {
            $result = $telegram->sendMessage([
                'chat_id'      => botManager::DRIVERS_GROUP_CHAT_ID,
                'text'         => botManager::orderTextForGroup($deal, $driverName),
                'reply_markup' => json_encode($keyboard),
                'parse_mode'   => 'HTML',
            ]);
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - newDealMessage result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND);
            return $result && (method_exists($result, 'isOk') ? $result->isOk() : true);
        } catch (Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - newDealMessage error: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    public static function buttonHanlde($telegram, $result) {
        require_once(__DIR__ . '/crest/crest.php');

        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();

        $data = $result->callbackQuery->data;
        if ($data) {
            $buttonData = explode('_', $data);
            $dealId = (int) $buttonData[1];
            
            // Логируем начало обработки
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Getting deal $dealId from Bitrix24\n", FILE_APPEND);
            
            $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal received: " . ($deal ? 'YES' : 'NO') . ", Stage: " . ($deal['STAGE_ID'] ?? 'UNKNOWN') . "\n", FILE_APPEND);
            if(empty($deal['ID'])) {
                $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => '', // можно добавить всплывающее уведомление
                        'show_alert' => false
                ]);
                exit;
            }
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Checking if deal is blocked. Stage: " . $deal['STAGE_ID'] . ", FINISH_STAGE_ID: " . botManager::FINISH_STAGE_ID . "\n", FILE_APPEND);
            
            if(
                    $deal['STAGE_ID'] == botManager::FINISH_STAGE_ID
                    || $deal['STAGE_ID'] =='LOSE'
                    || $deal['STAGE_ID'] == 'WON'
                    // Убираем NEW из заблокированных стадий - заявки в стадии NEW должны быть доступны для принятия
            ) {
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal $dealId is BLOCKED (unavailable)\n", FILE_APPEND);
                $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text'    => "Заявка недоступна",
                ]);
                $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => '', // можно добавить всплывающее уведомление
                        'show_alert' => false
                ]);
                exit;
            }
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Button action: " . $buttonData[0] . "\n", FILE_APPEND);
            
            match ($buttonData[0]) {
                'accept' => botManager::driverAcceptHandle($telegram, $result, $dealId),
                'reject' => botManager::driverRejectHandle($telegram, $result, $dealId),
                "groupAccept" => botManager::groupAcceptHandle($dealId, $chatId, $telegram, $result, $buttonData[2]),
                "start" => botManager::travelStartHandle($dealId, $telegram, $result),
                "startYes" => botManager::travelStartYesHandle($dealId, $telegram, $result),
                "startNo" => botManager::travelStartNoHandle($telegram, $result, $dealId),
                "cancel" => botManager::cancelHandle($dealId, $telegram, $result),
                "cancelYes" => self::cancelYesHandle($telegram, $result, $dealId),
                "cancelNo" => self::cancelNoHandle($dealId, $telegram, $result),
                "finish" => self::finishHandle($dealId, $telegram, $result),
                "finishYes" => self::finishYesHandle($dealId, $result, $telegram),
                "finishNo" => self::finishNoHandle($dealId, $telegram, $result),
                "confirm" => botManager::confirmReminderHandle($dealId, $telegram, $result),
                default => botManager::writeToLog("/logs/xxx.php", $buttonData[0],'$buttonData[0]', 'a'),
            };

            exit;
        }
    }

    public static function driverAcceptHandle ($telegram, $result, int $dealId): void {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - driverAcceptHandle started for deal $dealId\n", FILE_APPEND);
        
        require_once(__DIR__ . '/crest/crest.php');
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealId,
            'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD] // Добавляем поля "Пассажиры" и "Номер рейса"
        ])['result'];
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal loaded: " . ($deal['ID'] ?? 'NOT_FOUND') . "\n", FILE_APPEND);
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }

        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Getting message and chat info\n", FILE_APPEND);
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - ChatId: $chatId\n", FILE_APPEND);
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Current driver ID: " . ($deal[botManager::DRIVER_ID_FIELD] ?? 'EMPTY') . "\n", FILE_APPEND);
        
        // НОВАЯ ЛОГИКА: Проверяем водителя
        $currentDriverId = $deal[botManager::DRIVER_ID_FIELD];
        $telegramId = $result->callbackQuery->from->id;
        
        // НОВАЯ ЛОГИКА: Любой может взять заявку
        if(!$currentDriverId) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - No driver assigned, processing new request\n", FILE_APPEND);
            
            // Получаем данные из Telegram
            $telegramId = $result->callbackQuery->from->id;
            $telegramName = $result->callbackQuery->from->first_name;
            if ($result->callbackQuery->from->last_name) {
                $telegramName .= ' ' . $result->callbackQuery->from->last_name;
            }
            
            // Ищем водителя по Telegram ID
            $drivers = \CRest::call('crm.contact.list', [
                'filter' => ['UF_CRM_1751185017761' => $telegramId],
                'select' => ['ID', 'NAME', 'LAST_NAME']
            ]);
            
            if (isset($drivers['result']) && !empty($drivers['result'])) {
                // ЗАРЕГИСТРИРОВАННЫЙ ВОДИТЕЛЬ
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Registered driver found\n", FILE_APPEND);
                
                $driver = $drivers['result'][0];
                $driverId = $driver['ID'];
                $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
                
                // Назначаем водителя, меняем стадию и инициализируем SERVICE поля
                \CRest::call('crm.deal.update', [
                    'id' => $dealId, 
                    'fields' => [
                        botManager::DRIVER_ID_FIELD => $driverId,
                        'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID,
                        // Инициализируем SERVICE поля сразу, чтобы избежать ложных уведомлений
                        botManager::DRIVER_SUM_FIELD_SERVICE => $deal[botManager::DRIVER_SUM_FIELD],
                        botManager::ADDRESS_FROM_FIELD_SERVICE => $deal[botManager::ADDRESS_FROM_FIELD],
                        botManager::ADDRESS_TO_FIELD_SERVICE => $deal[botManager::ADDRESS_TO_FIELD],
                        botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => $deal[botManager::TRAVEL_DATE_TIME_FIELD],
                        botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE => $deal[botManager::ADDITIONAL_CONDITIONS_FIELD],
                        botManager::PASSENGERS_FIELD_SERVICE => $deal['UF_CRM_1751271798896'],
                        botManager::FLIGHT_NUMBER_FIELD_SERVICE => $deal[botManager::FLIGHT_NUMBER_FIELD],
                        botManager::CAR_CLASS_FIELD_SERVICE => $deal[botManager::CAR_CLASS_FIELD]
                    ]
                ]);
                
                // Получаем обновленную заявку с полями "Пассажиры" и "Номер рейса"
                $deal = \CRest::call('crm.deal.get', [
                    'id' => $dealId,
                    'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD]
                ])['result'];
                
                // Отправляем уведомление в общий чат (имя из CRM)  
                $orderNumber = $deal['TITLE'] ?? $dealId;
                // Убираем префикс "Заявка: " если есть
                if (strpos($orderNumber, 'Заявка: ') === 0) {
                    $orderNumber = substr($orderNumber, 8);
                }
                $groupMessage = "✅ Заявку #$orderNumber взял водитель: <b>$driverName</b>";
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $groupMessage,
                    'parse_mode' => 'HTML'
                ]);
                
                // Убираем кнопки с исходного сообщения
                $telegram->editMessageReplyMarkup([
                    'chat_id' => $chatId,
                    'message_id' => $message->getMessageId(),
                    'reply_markup' => json_encode(['inline_keyboard' => []])
                ]);
                
                // Отправляем детальную информацию в личку
                $detailedMessage = botManager::orderTextForDriver($deal);
                $privateKeyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"],
                            ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]
                        ]
                    ]
                ];
                
                $telegram->sendMessage([
                    'chat_id' => $telegramId,
                    'text' => $detailedMessage,
                    'reply_markup' => json_encode($privateKeyboard),
                    'parse_mode' => 'HTML'
                ]);
                
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => 'Заявка принята! Детали отправлены в личные сообщения.',
                    'show_alert' => true
                ]);
                
            } else {
                // НЕЗАРЕГИСТРИРОВАННЫЙ ВОДИТЕЛЬ
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Unregistered driver, assigning contact ID 9\n", FILE_APPEND);
                
                // Назначаем контакт ID 9, меняем стадию и инициализируем SERVICE поля
                \CRest::call('crm.deal.update', [
                    'id' => $dealId, 
                    'fields' => [
                        botManager::DRIVER_ID_FIELD => 9,
                        'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID,
                        // Инициализируем SERVICE поля сразу, чтобы избежать ложных уведомлений
                        botManager::DRIVER_SUM_FIELD_SERVICE => $deal[botManager::DRIVER_SUM_FIELD],
                        botManager::ADDRESS_FROM_FIELD_SERVICE => $deal[botManager::ADDRESS_FROM_FIELD],
                        botManager::ADDRESS_TO_FIELD_SERVICE => $deal[botManager::ADDRESS_TO_FIELD],
                        botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => $deal[botManager::TRAVEL_DATE_TIME_FIELD],
                        botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE => $deal[botManager::ADDITIONAL_CONDITIONS_FIELD],
                        botManager::PASSENGERS_FIELD_SERVICE => $deal['UF_CRM_1751271798896'],
                        botManager::FLIGHT_NUMBER_FIELD_SERVICE => $deal[botManager::FLIGHT_NUMBER_FIELD],
                        botManager::CAR_CLASS_FIELD_SERVICE => $deal[botManager::CAR_CLASS_FIELD]
                    ]
                ]);
                
                // Отправляем уведомление в общий чат (имя из Telegram)
                $orderNumber = $deal['TITLE'] ?? $dealId;
                // Убираем префикс "Заявка: " если есть
                if (strpos($orderNumber, 'Заявка: ') === 0) {
                    $orderNumber = substr($orderNumber, 8);
                }
                $groupMessage = "✅ Заявку #$orderNumber взял: <b>$telegramName</b>";
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $groupMessage,
                    'parse_mode' => 'HTML'
                ]);
                
                // Убираем кнопки с исходного сообщения
                $telegram->editMessageReplyMarkup([
                    'chat_id' => $chatId,
                    'message_id' => $message->getMessageId(),
                    'reply_markup' => json_encode(['inline_keyboard' => []])
                ]);
                
                // Уведомляем ответственного о незарегистрированном водителе
                \CRest::call('im.notify.system.add', [
                    'USER_ID' => $deal['ASSIGNED_BY_ID'],
                    'MESSAGE' => "⚠️ Заявку #{$orderNumber} взял незарегистрированный водитель: $telegramName (Telegram ID: $telegramId). " .
                                "<a href='https://meetride.bitrix24.ru/crm/deal/details/$dealId/'>Открыть заявку</a>"
                ]);
                
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => 'Заявка принята! Ответственный уведомлен о необходимости создания контакта водителя.',
                    'show_alert' => true
                ]);
            }
            
            return; // Завершаем выполнение функции
        } else {
            // Водитель уже назначен - проверяем, тот ли это водитель
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Driver already assigned (ID: $currentDriverId), checking if it's the same driver\n", FILE_APPEND);
            
            // НОВАЯ ЛОГИКА: Если назначен контакт ID 9, любой зарегистрированный водитель может взять заявку
            // ЭТА ПРОВЕРКА ДОЛЖНА БЫТЬ ПЕРВОЙ!
            if ($currentDriverId == 9) {
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Driver ID 9 assigned, allowing any registered driver to take the deal\n", FILE_APPEND);
                
                // Ищем водителя по Telegram ID
                $drivers = \CRest::call('crm.contact.list', [
                    'filter' => ['UF_CRM_1751185017761' => $telegramId],
                    'select' => ['ID', 'NAME', 'LAST_NAME']
                ]);
                
                if (isset($drivers['result']) && !empty($drivers['result'])) {
                    // ЗАРЕГИСТРИРОВАННЫЙ ВОДИТЕЛЬ - разрешаем взять заявку
                    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Registered driver found, allowing to take deal from ID 9\n", FILE_APPEND);
                    
                    $driver = $drivers['result'][0];
                    $driverId = $driver['ID'];
                    $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
                    
                    // Назначаем нового водителя, меняем стадию и инициализируем SERVICE поля
                    \CRest::call('crm.deal.update', [
                        'id' => $dealId, 
                        'fields' => [
                            botManager::DRIVER_ID_FIELD => $driverId,
                            'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID,
                            // Инициализируем SERVICE поля сразу, чтобы избежать ложных уведомлений
                            botManager::DRIVER_SUM_FIELD_SERVICE => $deal[botManager::DRIVER_SUM_FIELD],
                            botManager::ADDRESS_FROM_FIELD_SERVICE => $deal[botManager::ADDRESS_FROM_FIELD],
                            botManager::ADDRESS_TO_FIELD_SERVICE => $deal[botManager::ADDRESS_TO_FIELD],
                            botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => $deal[botManager::TRAVEL_DATE_TIME_FIELD]
                        ]
                    ]);
                    
                    // Получаем обновленную заявку с полями "Пассажиры" и "Номер рейса"
                    $deal = \CRest::call('crm.deal.get', [
                        'id' => $dealId,
                        'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD]
                    ])['result'];
                    
                    // Отправляем уведомление в общий чат (имя из CRM)  
                    $orderNumber = $deal['TITLE'] ?? $dealId;
                    // Убираем префикс "Заявка: " если есть
                    if (strpos($orderNumber, 'Заявка: ') === 0) {
                        $orderNumber = substr($orderNumber, 8);
                    }
                    $groupMessage = "✅ Заявку #$orderNumber взял водитель: <b>$driverName</b>";
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $groupMessage,
                        'parse_mode' => 'HTML'
                    ]);
                    
                    // Убираем кнопки с исходного сообщения
                    $telegram->editMessageReplyMarkup([
                        'chat_id' => $chatId,
                        'message_id' => $message->getMessageId(),
                        'reply_markup' => json_encode(['inline_keyboard' => []])
                    ]);
                    
                    // Отправляем детальную информацию в личку
                    $detailedMessage = botManager::orderTextForDriver($deal);
                    $privateKeyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"],
                                ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]
                            ]
                        ]
                    ];
                    
                    $telegram->sendMessage([
                        'chat_id' => $telegramId,
                        'text' => $detailedMessage,
                        'reply_markup' => json_encode($privateKeyboard),
                        'parse_mode' => 'HTML'
                    ]);
                    
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => 'Заявка принята! Детали отправлены в личные сообщения.',
                        'show_alert' => true
                    ]);
                    
                } else {
                    // НЕЗАРЕГИСТРИРОВАННЫЙ ВОДИТЕЛЬ - отказываем
                    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Unregistered driver trying to take deal from ID 9, rejecting\n", FILE_APPEND);
                    
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => 'Только зарегистрированные водители могут взять эту заявку.',
                        'show_alert' => true
                    ]);
                }
                
                return; // Завершаем выполнение функции
            }
            
            // Обычная логика для других водителей (не ID 9)
            $assignedDriver = \CRest::call('crm.contact.get', [
                'id' => $currentDriverId,
                'select' => ['ID', 'NAME', 'LAST_NAME', botManager::DRIVER_TELEGRAM_ID_FIELD]
            ])['result'];
            
            if ($assignedDriver && $assignedDriver[botManager::DRIVER_TELEGRAM_ID_FIELD] == $telegramId) {
                // Это тот же водитель - отправляем ему детали в личку + обновляем группу
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Same driver clicking again, sending details and updating group\n", FILE_APPEND);
                
                $driverName = trim($assignedDriver['NAME'] . ' ' . $assignedDriver['LAST_NAME']);
                
                // Убираем кнопки с исходного сообщения СРАЗУ (защита от спама)
                $telegram->editMessageReplyMarkup([
                    'chat_id' => $chatId,
                    'message_id' => $message->getMessageId(),
                    'reply_markup' => json_encode(['inline_keyboard' => []])
                ]);
                
                // Проверяем, отправлялось ли уже уведомление о взятии заявки
                $orderNumber = $deal['TITLE'] ?? $dealId;
                if (strpos($orderNumber, 'Заявка: ') === 0) {
                    $orderNumber = substr($orderNumber, 8);
                }
                
                // Отправляем уведомление в общий чат ТОЛЬКО если кнопки еще были активны
                // (если кнопки уже удалены, значит уведомление уже отправлялось)
                try {
                    $groupMessage = "✅ Заявку #$orderNumber взял водитель: <b>$driverName</b>";
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $groupMessage,
                        'parse_mode' => 'HTML'
                    ]);
                } catch (Exception $e) {
                    // Игнорируем ошибки отправки (возможно, сообщение уже было отправлено)
                    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Group message already sent, ignoring\n", FILE_APPEND);
                }
                
                // Отправляем детальную информацию водителю в личные сообщения
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Generating detailed message\n", FILE_APPEND);
                
                $detailedMessage = botManager::orderTextForDriver($deal);
                
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Message generated, sending to Telegram ID: $telegramId\n", FILE_APPEND);
                
                $privateKeyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"],
                            ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]
                        ]
                    ]
                ];
                
                try {
                    $result = $telegram->sendMessage([
                        'chat_id' => $telegramId,
                        'text' => $detailedMessage,
                        'reply_markup' => json_encode($privateKeyboard),
                        'parse_mode' => 'HTML'
                    ]);
                    
                    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Private message sent successfully\n", FILE_APPEND);
                } catch (Exception $e) {
                    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Error sending private message: " . $e->getMessage() . "\n", FILE_APPEND);
                }
                
                // Обновляем стадию заявки в Битрикс24
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Updating deal stage to DRIVER_ACCEPTED\n", FILE_APPEND);
                
                $dealUpdate = \CRest::call('crm.deal.update', ['id' => $dealId, 'fields'=>[
                    'STAGE_ID'=>botManager::DRIVER_ACCEPTED_STAGE_ID, // Водитель взял заявку
                    botManager::DRIVER_SUM_FIELD_SERVICE=>$deal[botManager::DRIVER_SUM_FIELD],
                    botManager::ADDRESS_FROM_FIELD_SERVICE=>$deal[botManager::ADDRESS_FROM_FIELD],
                    botManager::ADDRESS_TO_FIELD_SERVICE=>$deal[botManager::ADDRESS_TO_FIELD],
                    botManager::TRAVEL_DATE_TIME_FIELD_SERVICE=>$deal[botManager::TRAVEL_DATE_TIME_FIELD]
                ]]);
                
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal stage updated\n", FILE_APPEND);
                
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => 'Детали заявки отправлены в личные сообщения.',
                    'show_alert' => true
                ]);
            } else {
                // Другой водитель - отказываем
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => 'Заявка уже принята другим водителем.',
                    'show_alert' => true
                ]);
            }
        }
    }

    public static function cancelHandle(int $dealId, Api $telegram, Update $result) {
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $keyboard = new Keyboard();

        // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
        $keyboard->inline();

        // 3. Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => 'Да', 'callback_data' => "cancelYes_$dealId"]),
                Keyboard::inlineButton(['text' => 'Нет', 'callback_data' => "cancelNo_$dealId"]),
        ]);
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
        ]);
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
    }

    public static function finishHandle(int $dealId, Api $telegram, Update $result) {
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $keyboard = new Keyboard();

        // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
        $keyboard->inline();

        // 3. Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => 'Да', 'callback_data' => "finishYes_$dealId"]),
                Keyboard::inlineButton(['text' => 'Нет', 'callback_data' => "finishNo_$dealId"]),
        ]);
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
        ]);
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
    }

    public static function finishYesHandle($dealId, Update $result, Api $telegram) {
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $dealUpdate = \CRest::call('crm.deal.update', ['id' => $dealId, 'fields'=>[
                'STAGE_ID'=>botManager::FINISH_STAGE_ID,
        ]
        ]);
        
        // Обновляем сообщение с отметкой о выполнении и убираем кнопки
        $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'text' => $message->getText() . "\n\n✅ ЗАЯВКА ВЫПОЛНЕНА",
                'reply_markup' => null  // Убираем все кнопки
        ]);
        
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => 'Заявка отмечена как выполненная!', 
                'show_alert' => false
        ]);
    }

    public static function finishNoHandle(int $dealId, Api $telegram, Update $result) {
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $dealUpdate = \CRest::call('crm.deal.update', [
                        'id'     => $dealId,
                        'fields' => ['STAGE_ID' => botManager::TRAVEL_STARTED_STAGE_ID],
                ]
        );
        $keyboard = new Keyboard();

        // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
        $keyboard->inline();

        // 3. Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => '🏁 Заявка выполнена', 'callback_data' => "finish_$dealId"]),
                Keyboard::inlineButton(['text' => '❌ Отменить выполнение', 'callback_data' => "cancel_$dealId"]),
        ]);
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
        ]);
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
    }

    public static function cancelYesHandle(Api $telegram, Update $result, int $dealId) {
        require_once(__DIR__ . '/crest/crest.php');
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();


        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"],
                    ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]
                ]
            ]
        ];
        
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => json_encode($keyboard)
        ]);
        $dealUpdate = \CRest::call('crm.deal.update', ['id' => $dealId, 'fields'=>[
                'STAGE_ID'=>botManager::DRIVER_ACCEPTED_STAGE_ID,
        ]
        ]);
        $notify = \CRest::call('im.notify.system.add', [
                'USER_ID' => $deal['ASSIGNED_BY_ID'],
                'MESSAGE'=>"Водитель отменил выполнение заявки". " <a href = 'https://b24-cprnr5.bitrix24.ru/crm/deal/details/$dealId/'>{$deal['TITLE']}</a>",

                ]
        );
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);

    }

    public static function cancelNoHandle(int $dealId, Api $telegram, Update $result) {
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $dealUpdate = \CRest::call('crm.deal.update', [
                        'id'     => $dealId,
                        'fields' => ['STAGE_ID' => botManager::TRAVEL_STARTED_STAGE_ID],
                ]
        );
        $keyboard = new Keyboard();

        // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
        $keyboard->inline();

        // 3. Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => '🏁 Заявка выполнена', 'callback_data' => "finish_$dealId"]),
                Keyboard::inlineButton(['text' => '❌ Отменить выполнение', 'callback_data' => "cancel_$dealId"]),
        ]);
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
        ]);
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
    }

    public static function travelStartYesHandle(int $dealId, Api $telegram, Update $result) {
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $dealUpdate = \CRest::call('crm.deal.update', [
                        'id'     => $dealId,
                        'fields' => ['STAGE_ID' => botManager::TRAVEL_STARTED_STAGE_ID],
                ]
        );
        $keyboard = new Keyboard();

        // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
        $keyboard->inline();

        // 3. Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => '🏁 Заявка выполнена', 'callback_data' => "finish_$dealId"]),
                Keyboard::inlineButton(['text' => '❌ Отменить выполнение', 'callback_data' => "cancel_$dealId"]),
        ]);
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
        ]);
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
    }

    public static function travelStartNoHandle(Api $telegram, Update $result, int $dealId) {
        require_once(__DIR__ . '/crest/crest.php');
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();


        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"],
                    ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]
                ]
            ]
        ];
        
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => json_encode($keyboard)
        ]);

        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
    }

    public static function travelStartHandle(int $dealId, Api $telegram, Update $result) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - travelStartHandle started for deal $dealId\n", FILE_APPEND);
        
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Да', 'callback_data' => "startYes_$dealId"],
                    ['text' => 'Нет', 'callback_data' => "startNo_$dealId"]
                ]
            ]
        ];
        
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => json_encode($keyboard)
        ]);
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
    }

    public static function driverRejectHandle ($telegram, $result, int $dealId):void {
        require_once(__DIR__ . '/crest/crest.php');
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "вы отказались!",
        ]);
        $telegram->deleteMessage([
                'chat_id'    => $chatId,
                'message_id' => $message->getMessageId(),
        ]);
        $dealUpdate = \CRest::call('crm.deal.update', [
                'id' => $dealId,
                'fields'=>[
                    botManager::DRIVER_ID_FIELD => 0,
                    'STAGE_ID' => botManager::NEW_DEAL_STAGE_ID  // Возвращаем на стадию "Новая заявка"
                ]
        ]);
        if($deal[botManager::DRIVER_ID_FIELD] > 0) {
        $notify = \CRest::call('im.notify.system.add', [
                        'USER_ID' => $deal['ASSIGNED_BY_ID'],
                        'MESSAGE'=>"Водитель отказался от заявки". " <a href = 'https://b24-cprnr5.bitrix24.ru/crm/deal/details/$dealId/'>{$deal['TITLE']}</a>",

                ]
        );
        }
        // УБРАЛИ рассылку всем водителям в личку - по новой логике заявка остается в общем чате
        // Водители могут взять заявку из общего чата, нажав кнопку "Принять"
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
    }

    public static function dealChangeHandle(int $dealId, Api $telegram, Update $result): void {
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealId,
            'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD] // Добавляем поля "Пассажиры" и "Номер рейса"
        ])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        
        // ЗАЩИТА ОТ СПАМА: Проверяем временную метку последнего уведомления
        $lastNotificationTime = $deal['UF_CRM_1751638512'] ?? null; // Используем поле для временной метки
        $currentTime = time();
        
        // Если последнее уведомление было отправлено менее 30 секунд назад - игнорируем
        if ($lastNotificationTime && ($currentTime - strtotime($lastNotificationTime)) < 30) {
            return; // Слишком часто - выходим
        }
        $driver = \CRest::call('crm.contact.get', ['id' => $deal[botManager::DRIVER_ID_FIELD]])['result'];
        if(empty($driver['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        $driverTelegramId = (int) $driver[botManager::DRIVER_TELEGRAM_ID_FIELD];
        // Проверяем реальные изменения полей с валидацией SERVICE полей
        $changes = [];
        
        // Функция для проверки валидности SERVICE поля
        $isValidServiceValue = function($serviceValue, $mainValue) {
            // Если SERVICE поле пустое, а основное не пустое - это изменение
            if (empty($serviceValue) && !empty($mainValue)) {
                return false;
            }
            
            // Если SERVICE поле содержит дату (формат Y-m-d H:i:s или ISO), а основное поле не дата - неправильно
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $serviceValue) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $mainValue)) {
                return false;
            }
            
            // Если SERVICE поле содержит "Array" - неправильно
            if ($serviceValue === 'Array') {
                return false;
            }
            
            return true;
        };
        
        // Сумма
        $mainSum = $deal[botManager::DRIVER_SUM_FIELD];
        $serviceSum = $deal[botManager::DRIVER_SUM_FIELD_SERVICE];
        if ($mainSum !== $serviceSum && $isValidServiceValue($serviceSum, $mainSum)) {
            $changes['sum'] = (int) $mainSum;
        }
        
        // Адрес отправления
        $mainAddressFrom = $deal[botManager::ADDRESS_FROM_FIELD];
        $serviceAddressFrom = $deal[botManager::ADDRESS_FROM_FIELD_SERVICE];
        if ($mainAddressFrom !== $serviceAddressFrom && $isValidServiceValue($serviceAddressFrom, $mainAddressFrom)) {
            $changes['addressFrom'] = (string) $mainAddressFrom;
        }
        
        // Адрес назначения
        $mainAddressTo = $deal[botManager::ADDRESS_TO_FIELD];
        $serviceAddressTo = $deal[botManager::ADDRESS_TO_FIELD_SERVICE];
        if ($mainAddressTo !== $serviceAddressTo && $isValidServiceValue($serviceAddressTo, $mainAddressTo)) {
            $changes['addressTo'] = (string) $mainAddressTo;
        }
        
        // Дата и время
        $mainDate = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        $serviceDate = $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE];
        if ($mainDate !== $serviceDate && $isValidServiceValue($serviceDate, $mainDate)) {
            $changes['date'] = (string) $mainDate;
        }
        
        // Дополнительные условия
        $mainAdditionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD];
        $serviceAdditionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE];
        if ($mainAdditionalConditions !== $serviceAdditionalConditions && $isValidServiceValue($serviceAdditionalConditions, $mainAdditionalConditions)) {
            $changes['additionalConditions'] = (string) $mainAdditionalConditions;
        }
        
        // Пассажиры
        $mainPassengers = $deal['UF_CRM_1751271798896'];
        $servicePassengers = $deal[botManager::PASSENGERS_FIELD_SERVICE];
        if ($mainPassengers !== $servicePassengers && $isValidServiceValue($servicePassengers, $mainPassengers)) {
            if (is_array($mainPassengers)) {
                $changes['passengers'] = implode(", ", $mainPassengers);
            } else {
                $changes['passengers'] = (string) $mainPassengers;
            }
        }
        
        // Номер рейса
        $mainFlightNumber = $deal[botManager::FLIGHT_NUMBER_FIELD];
        $serviceFlightNumber = $deal[botManager::FLIGHT_NUMBER_FIELD_SERVICE];
        if ($mainFlightNumber !== $serviceFlightNumber && $isValidServiceValue($serviceFlightNumber, $mainFlightNumber)) {
            $changes['flightNumber'] = (string) $mainFlightNumber;
        }
        
        // Класс автомобиля
        $mainCarClass = $deal[botManager::CAR_CLASS_FIELD];
        $serviceCarClass = $deal[botManager::CAR_CLASS_FIELD_SERVICE];
        if ($mainCarClass !== $serviceCarClass && $isValidServiceValue($serviceCarClass, $mainCarClass)) {
            $changes['carClass'] = (string) $mainCarClass;
        }
        
        // Если нет изменений - выходим
        if (empty($changes)) {
            return;
        }

        $telegram->sendMessage(
                [
                        'chat_id'      => $driverTelegramId,
                        'text'         => botManager::orderTextForDriverWithChangesSimple($deal, $changes),
                        'parse_mode' => 'HTML',
                ]
        );
        $dealUpdate = \CRest::call('crm.deal.update', ['id' => $dealId, 'fields'=>[
                // Обновляем все SERVICE поля
                botManager::DRIVER_SUM_FIELD_SERVICE=>$deal[botManager::DRIVER_SUM_FIELD],
                botManager::ADDRESS_FROM_FIELD_SERVICE=>$deal[botManager::ADDRESS_FROM_FIELD],
                botManager::ADDRESS_TO_FIELD_SERVICE=>$deal[botManager::ADDRESS_TO_FIELD],
                botManager::TRAVEL_DATE_TIME_FIELD_SERVICE=>$deal[botManager::TRAVEL_DATE_TIME_FIELD],
                botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE=>$deal[botManager::ADDITIONAL_CONDITIONS_FIELD],
                botManager::PASSENGERS_FIELD_SERVICE=>$deal['UF_CRM_1751271798896'],
                botManager::FLIGHT_NUMBER_FIELD_SERVICE=>$deal[botManager::FLIGHT_NUMBER_FIELD],
                botManager::CAR_CLASS_FIELD_SERVICE=>$deal[botManager::CAR_CLASS_FIELD],
                'UF_CRM_1751638512' => date('Y-m-d H:i:s') // Обновляем временную метку
        ]
        ]);
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
    }

    public static function commonMailing(int $dealId, Api $telegram, Update $result): void {
        require_once(__DIR__ . '/crest/crest.php');
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        $dealUpdate = \CRest::call('crm.deal.update', [
                'id' => $dealId,
                'fields'=>[botManager::DRIVER_ID_FIELD => 0]
        ]);
        // УБРАЛИ commonMailing - по новой логике заявки отправляются только в общий чат
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
    }

    public static function groupAcceptHandle(int $dealId, string $chatId, Api $telegram, Update $result, $driverId): void {
        $message = $result->getMessage();
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        if(!$deal[botManager::DRIVER_ID_FIELD] && $deal['STAGE_ID'] === botManager::DRIVER_CHOICE_STAGE_ID) {
            \CRest::call('crm.deal.update', ['id' => $dealId, 'fields'=>[botManager::DRIVER_ID_FIELD => $driverId, 'STAGE_ID'=>botManager::DRIVER_ACCEPTED_STAGE_ID]])['result'];
        }
        sleep(3);
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealId,
            'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD] // Добавляем поля "Пассажиры" и "Номер рейса"
        ])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        if($deal[botManager::DRIVER_ID_FIELD] === $driverId) {
        // Создаем новую клавиатуру
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"],
                    ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]
                ]
            ]
        ];
        
        // Обновляем кнопки в сообщении
        $telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'reply_markup' => json_encode($keyboard)
        ]);
        
        // Отправляем заявку в личку водителю
        $driverTelegramId = $result->callbackQuery->from->id;
        $driverName = $result->callbackQuery->from->first_name;
        if($result->callbackQuery->from->last_name) {
            $driverName .= ' ' . $result->callbackQuery->from->last_name;
        }
        
        $telegram->sendMessage([
            'chat_id' => $driverTelegramId,
            'text' => botManager::orderTextForDriver($deal),
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);

        } else {
            $telegram->sendMessage(
                    [
                            'chat_id' => $chatId,
                            'text'    => "Заявку взял другой водитель",
                    ]
            );

            $telegram->deleteMessage([
                    'chat_id'    => $chatId,
                    'message_id' => $message->getMessageId(),
            ]);
        }
    }

    public static function orderText(
            array $deal,
            ?int $newSum = null,
            ?string $newFromAddress = null,
            ?string $newToAddress = null,
            ?string $newDate = null
    ): string {
        $additionalConditions = '';
        if (!empty($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
            if (is_array($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
                $additionalConditions = implode(" | ", $deal[botManager::ADDITIONAL_CONDITIONS_FIELD]);
            } else {
                $additionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD];
            }
        }
        // Форматируем дату в человеческий вид
        $dateText = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        if ($dateText) {
            $date = new \DateTime($dateText);
            $dateText = $date->format('d.m.Y H:i');
        }
        
        if ($newDate !== null) {
            // Форматируем старую дату
            $oldDate = $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE];
            if ($oldDate) {
                $oldDateFormatted = (new \DateTime($oldDate))->format('d.m.Y H:i');
            } else {
                $oldDateFormatted = $oldDate;
            }
            
            // Форматируем новую дату
            $newDateFormatted = $newDate;
            if ($newDate) {
                $newDateFormatted = (new \DateTime($newDate))->format('d.m.Y H:i');
            }
            
            $dateText = "<s>{$oldDateFormatted}</s> ➔ {$newDateFormatted}";
        }



        // Форматируем адрес отправления
        $fromAddress = $deal[botManager::ADDRESS_FROM_FIELD];
        if ($newFromAddress !== null) {
            $fromAddress = "<s>{$deal[botManager::ADDRESS_FROM_FIELD_SERVICE]}</s> ➔ {$newFromAddress}";
        }



        // Форматируем адрес назначения
        $toAddress = $deal[botManager::ADDRESS_TO_FIELD];
        if ($newToAddress !== null) {
            $toAddress = "<s>{$deal[botManager::ADDRESS_TO_FIELD_SERVICE]}</s> ➔ {$newToAddress}";
        }



        // Форматируем сумму
        $sumText = $deal[botManager::DRIVER_SUM_FIELD];
        if ($newSum !== null) {
            $oldSum = $deal[botManager::DRIVER_SUM_FIELD_SERVICE];
            $sumText = "<s>{$oldSum}</s> ➔ {$newSum} руб.";
        }

        // Используем номер заявки из TITLE для заголовка
        $orderNumber = $deal['TITLE'] ?? $deal['ID'];
        // Очищаем номер от лишнего текста
        if (strpos($orderNumber, ':') !== false) {
            $orderNumber = trim(explode(':', $orderNumber)[1]);
        }
        
        $header = $orderNumber;
        if($newSum || $newToAddress || $newFromAddress || $newDate) {
            $header = "Заявка $orderNumber изменена:";
        }


        $text = <<<HTML
#️⃣ $header

📆 {$dateText}

🅰️ {$fromAddress}

🅱️ {$toAddress}

ℹ️ {$additionalConditions}

💰 {$sumText}
HTML;

        return $text;
    }

    /**
     * Формирует текст заявки с указанием назначенного водителя
     */
    public static function orderTextWithDriver(array $deal, string $driverName): string {
        $additionalConditions = '';
        if (!empty($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
            if (is_array($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
                $additionalConditions = implode(" | ", $deal[botManager::ADDITIONAL_CONDITIONS_FIELD]);
            } else {
                $additionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD];
            }
        }
        
        // Форматируем дату в удобочитаемый вид
        $dateText = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        if ($dateText) {
            $date = new \DateTime($dateText);
            $dateText = $date->format('d.m.Y H:i');
        }
        
        $fromAddress = $deal[botManager::ADDRESS_FROM_FIELD];
        $toAddress = $deal[botManager::ADDRESS_TO_FIELD];
        $sumText = $deal[botManager::DRIVER_SUM_FIELD];

        $header = "Заявка {$deal['ID']}";
        
        // Добавляем ФИО водителя в заголовок
        if($driverName) {
            $header .= " - <b>Назначена водителю: {$driverName}</b>";
        }

        $text = <<<HTML
#️⃣ $header

📆 {$dateText}

🅰️ {$fromAddress}

🅱️ {$toAddress}

ℹ️ {$additionalConditions}

💰 {$sumText}
HTML;

        return $text;
    }

    /**
     * Формирует текст заявки для ОБЩЕГО ЧАТА (БЕЗ пассажиров)
     * Использует номер заявки из TITLE вместо ID сделки
     */
    public static function orderTextForGroup(array $deal, string $driverName = ''): string {
        $additionalConditions = '';
        if (!empty($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
            if (is_array($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
                $additionalConditions = implode(" | ", $deal[botManager::ADDITIONAL_CONDITIONS_FIELD]);
            } else {
                $additionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD];
            }
        }
        
        // Форматируем дату в удобочитаемый вид
        $dateText = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        if ($dateText) {
            $date = new \DateTime($dateText);
            $dateText = $date->format('d.m.Y H:i');
        }
        
        $fromAddress = $deal[botManager::ADDRESS_FROM_FIELD];
        $toAddress = $deal[botManager::ADDRESS_TO_FIELD];
        
        // Получаем класс автомобиля
        $carClassName = 'Не указано';
        if (!empty($deal[botManager::CAR_CLASS_FIELD])) {
            $carClassName = botManager::getCarClassName((int)$deal[botManager::CAR_CLASS_FIELD]);
        }
        
        // Убираем |RUB из суммы
        $sumText = $deal[botManager::DRIVER_SUM_FIELD];
        if ($sumText) {
            $sumText = str_replace('|RUB', '', $sumText);
        }
        
        // Используем TITLE как номер заявки (999999), а не ID сделки
        $orderNumber = $deal['TITLE'] ?? $deal['ID'];
        // Очищаем номер от лишнего текста (может быть "Заявка: 999999")
        if (strpos($orderNumber, ':') !== false) {
            $orderNumber = trim(explode(':', $orderNumber)[1]);
        }
        
        $header = "#️⃣ $orderNumber";
        
        // Добавляем ФИО водителя в заголовок, если назначен
        if($driverName) {
            $header .= " - <b>Назначена водителю: {$driverName}</b>";
        }

        $text = <<<HTML
$header

📆 {$dateText}

🚗 {$carClassName}

🅰️ {$fromAddress}

🅱️ {$toAddress}

ℹ️ {$additionalConditions}

💰 {$sumText}
HTML;

        return $text;
    }

    /**
     * Получает название класса автомобиля по ID
     */
    public static function getCarClassName(int $carClassId): string {
        $carClassMapping = [
            119 => 'Стандарт',
            93 => 'Комфорт',
            95 => 'Комфорт+',
            97 => 'Микроавтобус',
            99 => 'Минивэн',
            101 => 'Минивэн VIP',
            103 => 'Автобус',
            105 => 'Бизнес',
            107 => 'Представительский',
            109 => 'Кроссовер',
            111 => 'Джип',
            113 => 'Внедорожник',
            115 => 'Трезвый водитель',
            117 => 'Доставка'
        ];
        
        return $carClassMapping[$carClassId] ?? 'Не указано';
    }

    public static function writeToLog($LogFileName, $info, $prefix = '', $wa = 'a') {
        $log = '';
        if (is_array($info) || is_object($info)) {
            if (is_array($info))
                $log = print_r($info, 1);
            else
                $log = print_r((array)$info, 1);
        } else {
            $log = $info;
        }
        
        if (strlen($prefix) > 0) {
            $log = $prefix . "\n" . $log;
        }

        if ($wa == 'w') {
            $log = "<?php /*\n";
        }

        $log .= "\n------------------------\n";
        $log .= date("Y.m.d G:i:s") . "\n";
        $log .= "DEBUG\n";
        $log .= $log;
        $log .= "\n------------------------\n";

        if ($wa == 'w') {
            file_put_contents(getcwd() . $LogFileName, $log);
        } else {
            file_put_contents(getcwd() . $LogFileName, $log, FILE_APPEND);
        }

        return true;
    }

    /**
     * Отправляет напоминание водителю за 1 час до поездки
     */
    public static function sendTravelReminder(int $dealId, $telegram): bool {
        require_once('/home/telegramBot/crest/crest.php');
        
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if (empty($deal['ID'])) {
            return false;
        }
        
        // Проверяем, что сделка в статусе "Водитель принял"
        if ($deal['STAGE_ID'] !== botManager::DRIVER_ACCEPTED_STAGE_ID) {
            return false;
        }
        
        // Проверяем, что напоминание еще не отправлялось
        if (!empty($deal[botManager::REMINDER_SENT_FIELD])) {
            return false;
        }
        
        $driver = \CRest::call('crm.contact.get', [
            'id' => $deal[botManager::DRIVER_ID_FIELD],
            'select' => [botManager::DRIVER_TELEGRAM_ID_FIELD]
        ])['result'];
        
        if (empty($driver) || empty($driver[botManager::DRIVER_TELEGRAM_ID_FIELD])) {
            return false;
        }
        
        $driverTelegramId = (int) $driver[botManager::DRIVER_TELEGRAM_ID_FIELD];
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Подтверждаю', 'callback_data' => "confirm_$dealId"]
                ]
            ]
        ];
        
        $message = $telegram->sendMessage([
            'chat_id' => $driverTelegramId,
            'text' => "⚠️ НАПОМИНАНИЕ!\n\nЧерез 1 час начинается поездка по заявке #{$dealId}\n\nПожалуйста, подтвердите готовность к выполнению заказа.",
            'reply_markup' => json_encode($keyboard),
        ]);
        
        if ($message) {
            // Отмечаем, что напоминание отправлено
            \CRest::call('crm.deal.update', [
                'id' => $dealId,
                'fields' => [botManager::REMINDER_SENT_FIELD => date('Y-m-d H:i:s')]
            ]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Обрабатывает подтверждение водителя о готовности к поездке
     */
    public static function confirmReminderHandle(int $dealId, $telegram, Update $result): void {
        require_once('/home/telegramBot/crest/crest.php');
        
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if (empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => 'Заявка не найдена',
                'show_alert' => true
            ]);
            return;
        }
        
        $message = $result->getCallbackQuery()->getMessage();
        $chatId = $message->getChat()->getId();
        
        // Отмечаем, что водитель подтвердил готовность
        \CRest::call('crm.deal.update', [
            'id' => $dealId,
            'fields' => [botManager::REMINDER_CONFIRMED_FIELD => date('Y-m-d H:i:s')]
        ]);
        
        // Обновляем сообщение
        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $message->getMessageId(),
            'text' => "✅ Подтверждено!\n\nЗаявка #{$dealId} - водитель готов к выполнению заказа.",
        ]);
        
        $telegram->answerCallbackQuery([
            'callback_query_id' => $result->callbackQuery->id,
            'text' => 'Готовность подтверждена!',
            'show_alert' => false
        ]);
    }
    
    /**
     * Отправляет уведомление ответственному лицу о том, что водитель не подтвердил заказ
     */
    public static function sendResponsibleNotification(int $dealId, $telegram): bool {
        require_once('/home/telegramBot/crest/crest.php');
        
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if (empty($deal['ID'])) {
            return false;
        }
        
        // Проверяем, что уведомление еще не отправлялось
        if (!empty($deal[botManager::REMINDER_NOTIFICATION_SENT_FIELD])) {
            return false;
        }
        
        // Проверяем, что напоминание было отправлено, но водитель не подтвердил
        if (empty($deal[botManager::REMINDER_SENT_FIELD]) || !empty($deal[botManager::REMINDER_CONFIRMED_FIELD])) {
            return false;
        }
        
        // Проверяем, прошло ли 15 минут с момента отправки напоминания
        $reminderTime = strtotime($deal[botManager::REMINDER_SENT_FIELD]);
        $currentTime = time();
        
        if (($currentTime - $reminderTime) < 900) { // 900 секунд = 15 минут
            return false;
        }
        
        // Отправляем уведомление ответственному лицу
        $notify = \CRest::call('im.notify.system.add', [
            'USER_ID' => $deal['ASSIGNED_BY_ID'],
            'MESSAGE' => "⚠️ ВНИМАНИЕ! Водитель 15 минут не подтверждает заказ #{$dealId}. " .
                        "<a href='https://b24-cprnr5.bitrix24.ru/crm/deal/details/{$dealId}/'>{$deal['TITLE']}</a>"
        ]);
        
        if ($notify) {
            // Отмечаем, что уведомление отправлено
            \CRest::call('crm.deal.update', [
                'id' => $dealId,
                'fields' => [botManager::REMINDER_NOTIFICATION_SENT_FIELD => date('Y-m-d H:i:s')]
            ]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Проверяет все активные заявки и отправляет напоминания
     */
    public static function checkAndSendReminders($telegram): array {
        require_once('/home/telegramBot/crest/crest.php');
        
        $result = [
            'reminders_sent' => 0,
            'notifications_sent' => 0,
            'errors' => []
        ];
        
        // Получаем все заявки в статусе "Водитель принял"
        $deals = \CRest::call('crm.deal.list', [
            'filter' => [
                'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID
            ],
            'select' => ['ID', botManager::TRAVEL_DATE_TIME_FIELD, botManager::REMINDER_SENT_FIELD, botManager::REMINDER_CONFIRMED_FIELD]
        ])['result'];
        
        foreach ($deals as $deal) {
            try {
                $travelTime = strtotime($deal[botManager::TRAVEL_DATE_TIME_FIELD]);
                $currentTime = time();
                $timeUntilTravel = $travelTime - $currentTime;
                
                // Проверяем, было ли уже отправлено напоминание
                $reminderSent = !empty($deal[botManager::REMINDER_SENT_FIELD]);
                $reminderConfirmed = !empty($deal[botManager::REMINDER_CONFIRMED_FIELD]);
                
                // Если до поездки остался 1 час (3600 секунд) или меньше, и напоминание не отправлялось
                if ($timeUntilTravel <= 3600 && $timeUntilTravel > 0 && !$reminderSent && !$reminderConfirmed) {
                    if (botManager::sendTravelReminder($deal['ID'], $telegram)) {
                        $result['reminders_sent']++;
                    } else {
                        $result['errors'][] = "Ошибка отправки напоминания для заявки #{$deal['ID']}";
                    }
                }
            } catch (Exception $e) {
                $result['errors'][] = "Ошибка обработки заявки #{$deal['ID']}: " . $e->getMessage();
            }
        }
        
        // Проверяем заявки для отправки уведомлений ответственному
        $dealsForNotification = \CRest::call('crm.deal.list', [
            'filter' => [
                'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID
            ],
            'select' => ['ID', botManager::REMINDER_SENT_FIELD, botManager::REMINDER_CONFIRMED_FIELD, botManager::REMINDER_NOTIFICATION_SENT_FIELD, 'ASSIGNED_BY_ID', 'TITLE']
        ])['result'];
        
        foreach ($dealsForNotification as $deal) {
            try {
                // Проверяем состояние заявки
                $reminderSent = !empty($deal[botManager::REMINDER_SENT_FIELD]);
                $reminderConfirmed = !empty($deal[botManager::REMINDER_CONFIRMED_FIELD]);
                $notificationSent = !empty($deal[botManager::REMINDER_NOTIFICATION_SENT_FIELD]);
                
                // Пропускаем если напоминание не отправлялось, уже подтверждено, или уведомление уже отправлено
                if (!$reminderSent || $reminderConfirmed || $notificationSent) {
                    continue;
                }
                
                // Проверяем, прошло ли 15 минут с момента отправки напоминания
                $reminderTime = strtotime($deal[botManager::REMINDER_SENT_FIELD]);
                $currentTime = time();
                
                if (($currentTime - $reminderTime) >= 900) { // 900 секунд = 15 минут
                    if (botManager::sendResponsibleNotification($deal['ID'], $telegram)) {
                        $result['notifications_sent']++;
                    } else {
                        $result['errors'][] = "Ошибка отправки уведомления ответственному для заявки #{$deal['ID']}";
                    }
                }
            } catch (Exception $e) {
                $result['errors'][] = "Ошибка обработки уведомления для заявки #{$deal['ID']}: " . $e->getMessage();
            }
        }
        
        return $result;
    }

    /**
     * Формирует детальный текст заявки для личных сообщений водителю 
     * Включает поле "Пассажиры" (UF_CRM_1751271798896)
     * НЕ включает скрытое поле UF_CRM_1751271841129
     */
    public static function orderTextForDriver(array $deal): string {
        $additionalConditions = '';
        if (!empty($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
            if (is_array($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
                $additionalConditions = implode(" | ", $deal[botManager::ADDITIONAL_CONDITIONS_FIELD]);
            } else {
                $additionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD];
            }
        }
        
        // Форматируем дату в удобочитаемый вид
        $dateText = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        if ($dateText) {
            $date = new \DateTime($dateText);
            $dateText = $date->format('d.m.Y H:i');
        }
        
        $fromAddress = $deal[botManager::ADDRESS_FROM_FIELD];
        $toAddress = $deal[botManager::ADDRESS_TO_FIELD];
        
        // Убираем |RUB из суммы
        $sumText = $deal[botManager::DRIVER_SUM_FIELD];
        if ($sumText) {
            $sumText = str_replace('|RUB', '', $sumText);
        }
        
        // Получаем информацию о пассажирах (показываем)
        $passengers = 'Не указано';
        if (!empty($deal['UF_CRM_1751271798896'])) {
            // Если поле - массив, преобразуем в строку
            if (is_array($deal['UF_CRM_1751271798896'])) {
                $passengers = implode(", ", $deal['UF_CRM_1751271798896']);
            } else {
                $passengers = $deal['UF_CRM_1751271798896'];
            }
        }
        
        // Получаем информацию о номере рейса (показываем)
        $flightNumber = 'Не указано';
        if (!empty($deal[botManager::FLIGHT_NUMBER_FIELD])) {
            $flightNumber = $deal[botManager::FLIGHT_NUMBER_FIELD];
        }
        
        // Поле UF_CRM_1751271841129 НЕ ПОКАЗЫВАЕМ никогда!
        
        // Используем TITLE как номер заявки, а не ID сделки
        $orderNumber = $deal['TITLE'] ?? $deal['ID'];
        // Очищаем номер от лишнего текста (может быть "Заявка: 999999")
        if (strpos($orderNumber, ':') !== false) {
            $orderNumber = trim(explode(':', $orderNumber)[1]);
        }
        $header = "🚗 Ваша заявка #$orderNumber";

        $text = <<<HTML
$header

📆 <b>Дата и время:</b> {$dateText}

🅰️ <b>Откуда:</b> {$fromAddress}

🅱️ <b>Куда:</b> {$toAddress}

👥 <b>Пассажиры:</b> {$passengers}

✈️ <b>Номер рейса:</b> {$flightNumber}

ℹ️ <b>Дополнительные условия:</b> {$additionalConditions}

💰 <b>Сумма:</b> {$sumText}

<i>Нажмите "Начать выполнение" когда будете готовы ехать</i>
HTML;

        return $text;
    }

    /**
     * Формирует детальный текст заявки для личных сообщений водителю с поддержкой изменений
     * Включает поле "Пассажиры" (UF_CRM_1751271798896) и номер рейса
     */
    public static function orderTextForDriverWithChanges(
            array $deal,
            ?int $newSum = null,
            ?string $newFromAddress = null,
            ?string $newToAddress = null,
            ?string $newDate = null,
            ?string $newAdditionalConditions = null,
            ?string $newPassengers = null,
            ?string $newFlightNumber = null,
            ?string $newCarClass = null
    ): string {
        $additionalConditions = '';
        if (!empty($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
            if (is_array($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
                $additionalConditions = implode(" | ", $deal[botManager::ADDITIONAL_CONDITIONS_FIELD]);
            } else {
                $additionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD];
            }
        }
        
        // Форматируем дату в удобочитаемый вид
        $dateText = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        if ($dateText) {
            $date = new \DateTime($dateText);
            $dateText = $date->format('d.m.Y H:i');
        }
        
        if ($newDate !== null) {
            // Форматируем старую дату
            $oldDate = $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE];
            if ($oldDate) {
                $oldDateFormatted = (new \DateTime($oldDate))->format('d.m.Y H:i');
            } else {
                $oldDateFormatted = $oldDate;
            }
            
            // Форматируем новую дату
            $newDateFormatted = $newDate;
            if ($newDate) {
                $newDateFormatted = (new \DateTime($newDate))->format('d.m.Y H:i');
            }
            
            $dateText = "<s>{$oldDateFormatted}</s> ➔ {$newDateFormatted}";
        }

        // Форматируем адрес отправления
        $fromAddress = $deal[botManager::ADDRESS_FROM_FIELD];
        if ($newFromAddress !== null) {
            $fromAddress = "<s>{$deal[botManager::ADDRESS_FROM_FIELD_SERVICE]}</s> ➔ {$newFromAddress}";
        }

        // Форматируем адрес назначения
        $toAddress = $deal[botManager::ADDRESS_TO_FIELD];
        if ($newToAddress !== null) {
            $toAddress = "<s>{$deal[botManager::ADDRESS_TO_FIELD_SERVICE]}</s> ➔ {$newToAddress}";
        }

        // Форматируем сумму
        $sumText = $deal[botManager::DRIVER_SUM_FIELD];
        if ($newSum !== null) {
            $oldSum = $deal[botManager::DRIVER_SUM_FIELD_SERVICE];
            $sumText = "<s>{$oldSum}</s> ➔ {$newSum} руб.";
        } else {
            // Убираем |RUB из суммы
            if ($sumText) {
                $sumText = str_replace('|RUB', '', $sumText);
            }
        }
        
        // Получаем информацию о пассажирах (показываем)
        $passengers = 'Не указано';
        if (!empty($deal['UF_CRM_1751271798896'])) {
            // Если поле - массив, преобразуем в строку
            if (is_array($deal['UF_CRM_1751271798896'])) {
                $passengers = implode(", ", $deal['UF_CRM_1751271798896']);
            } else {
                $passengers = $deal['UF_CRM_1751271798896'];
            }
        }
        
        // Получаем информацию о номере рейса (показываем)
        $flightNumber = 'Не указано';
        if (!empty($deal[botManager::FLIGHT_NUMBER_FIELD])) {
            $flightNumber = $deal[botManager::FLIGHT_NUMBER_FIELD];
        }
        
        // Используем TITLE как номер заявки, а не ID сделки
        $orderNumber = $deal['TITLE'] ?? $deal['ID'];
        // Очищаем номер от лишнего текста (может быть "Заявка: 999999")
        if (strpos($orderNumber, ':') !== false) {
            $orderNumber = trim(explode(':', $orderNumber)[1]);
        }
        
        $header = "🚗 Ваша заявка #$orderNumber";
        if($newSum || $newToAddress || $newFromAddress || $newDate) {
            $header = "🚗 Заявка $orderNumber изменена:";
        }

        $text = <<<HTML
$header

📆 <b>Дата и время:</b> {$dateText}

🅰️ <b>Откуда:</b> {$fromAddress}

🅱️ <b>Куда:</b> {$toAddress}

👥 <b>Пассажиры:</b> {$passengers}

✈️ <b>Номер рейса:</b> {$flightNumber}

ℹ️ <b>Дополнительные условия:</b> {$additionalConditions}

💰 <b>Сумма:</b> {$sumText}
HTML;

        return $text;
    }

    /**
     * Формирует детальный текст заявки для личных сообщений водителю с поддержкой изменений
     * Включает все поля: пассажиры, номер рейса, класс авто, дополнительные условия
     */
    public static function orderTextForDriverWithChangesNew(
            array $deal,
            ?int $newSum = null,
            ?string $newFromAddress = null,
            ?string $newToAddress = null,
            ?string $newDate = null,
            ?string $newAdditionalConditions = null,
            ?string $newPassengers = null,
            ?string $newFlightNumber = null,
            ?string $newCarClass = null
    ): string {
        $additionalConditions = '';
        if (!empty($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
            if (is_array($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
                $additionalConditions = implode(" | ", $deal[botManager::ADDITIONAL_CONDITIONS_FIELD]);
            } else {
                $additionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD];
            }
        }
        
        // Обрабатываем изменения дополнительных условий
        if ($newAdditionalConditions !== null) {
            $oldAdditionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE];
            if (is_array($oldAdditionalConditions)) {
                $oldAdditionalConditions = implode(" | ", $oldAdditionalConditions);
            }
            $additionalConditions = "<s>{$oldAdditionalConditions}</s> ➔ {$newAdditionalConditions}";
        }
        
        // Форматируем дату в удобочитаемый вид
        $dateText = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        if ($dateText) {
            $date = new \DateTime($dateText);
            $dateText = $date->format('d.m.Y H:i');
        }
        
        if ($newDate !== null) {
            // Форматируем старую дату
            $oldDate = $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE];
            if ($oldDate) {
                $oldDateFormatted = (new \DateTime($oldDate))->format('d.m.Y H:i');
            } else {
                $oldDateFormatted = $oldDate;
            }
            
            // Форматируем новую дату
            $newDateFormatted = $newDate;
            if ($newDate) {
                $newDateFormatted = (new \DateTime($newDate))->format('d.m.Y H:i');
            }
            
            $dateText = "<s>{$oldDateFormatted}</s> ➔ {$newDateFormatted}";
        }

        // Форматируем адрес отправления
        $fromAddress = $deal[botManager::ADDRESS_FROM_FIELD];
        if ($newFromAddress !== null) {
            $fromAddress = "<s>{$deal[botManager::ADDRESS_FROM_FIELD_SERVICE]}</s> ➔ {$newFromAddress}";
        }

        // Форматируем адрес назначения
        $toAddress = $deal[botManager::ADDRESS_TO_FIELD];
        if ($newToAddress !== null) {
            $toAddress = "<s>{$deal[botManager::ADDRESS_TO_FIELD_SERVICE]}</s> ➔ {$newToAddress}";
        }

        // Форматируем сумму
        $sumText = $deal[botManager::DRIVER_SUM_FIELD];
        if ($newSum !== null) {
            $oldSum = $deal[botManager::DRIVER_SUM_FIELD_SERVICE];
            $sumText = "<s>{$oldSum}</s> ➔ {$newSum} руб.";
        } else {
            // Убираем |RUB из суммы
            if ($sumText) {
                $sumText = str_replace('|RUB', '', $sumText);
            }
        }
        
        // Получаем информацию о пассажирах (показываем)
        $passengers = 'Не указано';
        if (!empty($deal['UF_CRM_1751271798896'])) {
            // Если поле - массив, преобразуем в строку
            if (is_array($deal['UF_CRM_1751271798896'])) {
                $passengers = implode(", ", $deal['UF_CRM_1751271798896']);
            } else {
                $passengers = $deal['UF_CRM_1751271798896'];
            }
        }
        
        // Обрабатываем изменения пассажиров
        if ($newPassengers !== null) {
            $oldPassengers = $deal[botManager::PASSENGERS_FIELD_SERVICE];
            if (is_array($oldPassengers)) {
                $oldPassengers = implode(", ", $oldPassengers);
            }
            $passengers = "<s>{$oldPassengers}</s> ➔ {$newPassengers}";
        }
        
        // Получаем информацию о номере рейса (показываем)
        $flightNumber = 'Не указано';
        if (!empty($deal[botManager::FLIGHT_NUMBER_FIELD])) {
            $flightNumber = $deal[botManager::FLIGHT_NUMBER_FIELD];
        }
        
        // Обрабатываем изменения номера рейса
        if ($newFlightNumber !== null) {
            $oldFlightNumber = $deal[botManager::FLIGHT_NUMBER_FIELD_SERVICE];
            $flightNumber = "<s>{$oldFlightNumber}</s> ➔ {$newFlightNumber}";
        }
        
        // Получаем информацию о классе автомобиля (показываем)
        $carClassName = 'Не указано';
        if (!empty($deal[botManager::CAR_CLASS_FIELD])) {
            $carClassName = botManager::getCarClassName((int)$deal[botManager::CAR_CLASS_FIELD]);
        }
        
        // Обрабатываем изменения класса автомобиля
        if ($newCarClass !== null) {
            $oldCarClassId = $deal[botManager::CAR_CLASS_FIELD_SERVICE];
            $oldCarClassName = $oldCarClassId ? botManager::getCarClassName((int)$oldCarClassId) : 'Не указано';
            $newCarClassName = botManager::getCarClassName((int)$newCarClass);
            $carClassName = "<s>{$oldCarClassName}</s> ➔ {$newCarClassName}";
        }
        
        // Используем TITLE как номер заявки, а не ID сделки
        $orderNumber = $deal['TITLE'] ?? $deal['ID'];
        // Очищаем номер от лишнего текста (может быть "Заявка: 999999")
        if (strpos($orderNumber, ':') !== false) {
            $orderNumber = trim(explode(':', $orderNumber)[1]);
        }
        
        $header = "🚗 Ваша заявка #$orderNumber";
        if($newSum || $newToAddress || $newFromAddress || $newDate || $newAdditionalConditions || $newPassengers || $newFlightNumber || $newCarClass) {
            $header = "🚗 Заявка $orderNumber изменена:";
        }

        $text = <<<HTML
$header

📆 <b>Дата и время:</b> {$dateText}

🚗 <b>Класс автомобиля:</b> {$carClassName}

🅰️ <b>Откуда:</b> {$fromAddress}

🅱️ <b>Куда:</b> {$toAddress}

👥 <b>Пассажиры:</b> {$passengers}

✈️ <b>Номер рейса:</b> {$flightNumber}

ℹ️ <b>Дополнительные условия:</b> {$additionalConditions}

💰 <b>Сумма:</b> {$sumText}
HTML;

        return $text;
    }

    /**
     * Формирует упрощенное сообщение об изменениях - показывает только измененные поля
     */
    public static function orderTextForDriverWithChangesSimple(array $deal, array $changes): string {
        // Получаем номер заявки
        $orderNumber = $deal['TITLE'] ?? $deal['ID'];
        if (strpos($orderNumber, ':') !== false) {
            $orderNumber = trim(explode(':', $orderNumber)[1]);
        }
        
        $header = "🚗 Заявка $orderNumber изменена:";
        $text = $header . "\n\n";
        
        // Показываем только измененные поля
        foreach ($changes as $fieldType => $newValue) {
            switch ($fieldType) {
                case 'sum':
                    $oldValue = $deal[botManager::DRIVER_SUM_FIELD_SERVICE];
                    $text .= "💰 <b>Сумма:</b> <s>{$oldValue}</s> ➔ {$newValue} руб.\n\n";
                    break;
                    
                case 'addressFrom':
                    $oldValue = $deal[botManager::ADDRESS_FROM_FIELD_SERVICE];
                    $text .= "🅰️ <b>Откуда:</b> <s>{$oldValue}</s> ➔ {$newValue}\n\n";
                    break;
                    
                case 'addressTo':
                    $oldValue = $deal[botManager::ADDRESS_TO_FIELD_SERVICE];
                    $text .= "🅱️ <b>Куда:</b> <s>{$oldValue}</s> ➔ {$newValue}\n\n";
                    break;
                    
                case 'date':
                    $oldValue = $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE];
                    if ($oldValue) {
                        $oldDate = (new \DateTime($oldValue))->format('d.m.Y H:i');
                    } else {
                        $oldDate = $oldValue;
                    }
                    if ($newValue) {
                        $newDate = (new \DateTime($newValue))->format('d.m.Y H:i');
                    } else {
                        $newDate = $newValue;
                    }
                    $text .= "📆 <b>Дата и время:</b> <s>{$oldDate}</s> ➔ {$newDate}\n\n";
                    break;
                    
                case 'additionalConditions':
                    $oldValue = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE];
                    if (is_array($oldValue)) {
                        $oldValue = implode(" | ", $oldValue);
                    }
                    $text .= "ℹ️ <b>Дополнительные условия:</b> <s>{$oldValue}</s> ➔ {$newValue}\n\n";
                    break;
                    
                case 'passengers':
                    $oldValue = $deal[botManager::PASSENGERS_FIELD_SERVICE];
                    if (is_array($oldValue)) {
                        $oldValue = implode(", ", $oldValue);
                    }
                    $text .= "👥 <b>Пассажиры:</b> <s>{$oldValue}</s> ➔ {$newValue}\n\n";
                    break;
                    
                case 'flightNumber':
                    $oldValue = $deal[botManager::FLIGHT_NUMBER_FIELD_SERVICE];
                    $text .= "✈️ <b>Номер рейса:</b> <s>{$oldValue}</s> ➔ {$newValue}\n\n";
                    break;
                    
                case 'carClass':
                    $oldValue = $deal[botManager::CAR_CLASS_FIELD_SERVICE];
                    $oldCarClassName = $oldValue ? botManager::getCarClassName((int)$oldValue) : 'Не указано';
                    $newCarClassName = botManager::getCarClassName((int)$newValue);
                    $text .= "🚗 <b>Класс автомобиля:</b> <s>{$oldCarClassName}</s> ➔ {$newCarClassName}\n\n";
                    break;
            }
        }
        
        return trim($text);
    }
}
