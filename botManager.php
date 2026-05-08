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
    public const INTERMEDIATE_POINTS_FIELD      = 'UF_CRM_1751822573510'; // Промежуточные точки
    public const FLIGHT_NUMBER_FIELD            = 'UF_CRM_1751271774391'; // Номер рейса
    public const CAR_CLASS_FIELD                = 'UF_CRM_1751271728682'; // Класс автомобиля
    public const DRIVER_SUM_FIELD               = 'UF_CRM_1751271862251';
    public const DRIVER_SUM_FIELD_SERVICE       = 'UF_CRM_1751638441407';
    public const TRAVEL_DATE_TIME_FIELD         = 'UF_CRM_1751269222959';
    public const TRAVEL_DATE_TIME_FIELD_SERVICE = 'UF_CRM_1751638617';
    public const ADDITIONAL_CONDITIONS_FIELD_SERVICE = 'UF_CRM_1758709126'; // REMINDER_SENT_FIELD (используем как SERVICE)
    public const PASSENGERS_FIELD = 'UF_CRM_1751271798896'; // Пассажиры
    public const PASSENGERS_FIELD_SERVICE = 'UF_CRM_1759653762'; // SERVICE: Пассажиры
    public const INTERMEDIATE_POINTS_FIELD_SERVICE = 'UF_CRM_1754228146'; // SERVICE: Промежуточные точки
    public const FLIGHT_NUMBER_FIELD_SERVICE = 'UF_CRM_1758710216'; // REMINDER_NOTIFICATION_SENT_FIELD (используем как SERVICE)
    public const CAR_CLASS_FIELD_SERVICE = 'UF_CRM_1759653779'; // SERVICE: Класс автомобиля
    public const ORDER_NUMBER_SERVICE_FIELD = 'UF_CRM_1759847340'; // SERVICE: Номер заявки
    public const DRIVER_ACCEPTED_STAGE_ID       = 'PREPAYMENT_INVOICE'; // Водитель взял заявку
    
    /**
     * Извлекает очищенный номер заявки из TITLE
     */
    public static function extractOrderNumber(string $title): string {
        if (empty($title)) return '';
        
        $cleanNumber = $title;
        if (strpos($cleanNumber, 'Заявка: ') === 0) {
            $cleanNumber = mb_substr($cleanNumber, 8);
        } elseif (strpos($cleanNumber, 'Сделка #') === 0) {
            $cleanNumber = mb_substr($cleanNumber, 8);
        }
        
        return $cleanNumber;
    }
    
    /**
     * Форматирует дату из ISO формата в человекочитаемый вид
     * @param string|null $dateString Дата в формате ISO (2025-01-10T10:00:00+03:00)
     * @return string Дата в формате "10 января 2025, 10:00" или "Не указано"
     */
    public static function formatDateTime(?string $dateString): string {
        if (empty($dateString)) {
            return 'Не указано';
        }
        
        try {
            $date = new \DateTime($dateString);
            
            $months = [
                1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
                5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
                9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
            ];
            
            $day = $date->format('j');
            $month = $months[(int)$date->format('n')];
            $year = $date->format('Y');
            $time = $date->format('H:i');
            
            return "$day $month $year, $time";
        } catch (\Exception $e) {
            return $dateString; // Возвращаем исходную строку, если не удалось распарсить
        }
    }
    public const NEW_DEAL_STAGE_ID              = 'NEW';
    public const DRIVER_CHOICE_STAGE_ID         = 'PREPARATION';
    public const TRAVEL_STARTED_STAGE_ID         = 'EXECUTING'; // Заявка выполняется
    public const FINISH_STAGE_ID         = 'FINAL_INVOICE';
    public const DRIVER_CONTACT_TYPE            = 'UC_C7O5J7';
    public const DRIVERS_GROUP_CHAT_ID = '-1002544521661'; // БОЕВОЙ режим - группа водителей
    
    // Поля для системы напоминаний (исправленные ID)
    public const REMINDER_SENT_FIELD            = 'UF_CRM_1758709126';
    public const REMINDER_CONFIRMED_FIELD       = 'UF_CRM_1758709139';
    public const REMINDER_NOTIFICATION_SENT_FIELD = 'UF_CRM_1758710216';
    public const GROUP_MESSAGE_SENT_FIELD       = 'UF_CRM_1759918565'; // Сообщение в общий чат отправлено

    public static function newDealMessage(int $dealid, $telegram): bool {
        if (!class_exists("CRest")) { require_once("/home/telegramBot/crest/crest.php"); }
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
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Preparing message for group\n", FILE_APPEND);
        
        $messageText = botManager::orderTextForGroup($deal, $driverName);
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Sending message to group\n", FILE_APPEND);
        
        try {
            $result = $telegram->sendMessage([
                'chat_id'      => botManager::DRIVERS_GROUP_CHAT_ID,
                'text'         => $messageText,
                'reply_markup' => json_encode($keyboard),
                'parse_mode'   => 'HTML',
            ]);
            
            $success = $result && (method_exists($result, 'isOk') ? $result->isOk() : true);
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - newDealMessage result: " . ($success ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND);
            
            // Помечаем, что сообщение в общий чат отправлено (защита от дублирования)
            if ($success) {
                \CRest::call('crm.deal.update', [
                    'id' => $dealid,
                    'fields' => [
                        botManager::GROUP_MESSAGE_SENT_FIELD => date('Y-m-d H:i:s')
                    ]
                ]);
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Marked deal $dealid as sent to group\n", FILE_APPEND);
            }
            
            return $success;
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - newDealMessage error: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    public static function buttonHanlde($telegram, $result) {
        if (!class_exists("CRest")) { require_once('/home/telegramBot/crest/crest.php'); }

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
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - GHOST deal $dealId, clearing keyboard\n", FILE_APPEND);
                try {
                    $telegram->editMessageReplyMarkup([
                            'chat_id'      => $chatId,
                            'message_id'   => $message->getMessageId(),
                            'reply_markup' => json_encode(['inline_keyboard' => []]),
                    ]);
                } catch (\Throwable $e) { /* message too old to edit — ignore */ }
                try {
                    $telegram->answerCallbackQuery([
                            'callback_query_id' => $result->get('callback_query')['id'],
                            'text'              => '⚠️ Заявка устарела или удалена',
                            'show_alert'        => true,
                    ]);
                } catch (\Throwable $e) { /* callback expired — ignore */ }
                return;
            }
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Checking if deal is blocked. Stage: " . $deal['STAGE_ID'] . ", FINISH_STAGE_ID: " . botManager::FINISH_STAGE_ID . "\n", FILE_APPEND);
            
            if(
                    $deal['STAGE_ID'] == botManager::FINISH_STAGE_ID
                    || $deal['STAGE_ID'] =='LOSE'
                    || $deal['STAGE_ID'] == 'WON'
                    // Убираем NEW из заблокированных стадий - заявки в стадии NEW должны быть доступны для принятия
            ) {
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal $dealId is BLOCKED (unavailable), clearing keyboard\n", FILE_APPEND);
                try {
                    $telegram->editMessageReplyMarkup([
                            'chat_id'      => $chatId,
                            'message_id'   => $message->getMessageId(),
                            'reply_markup' => json_encode(['inline_keyboard' => []]),
                    ]);
                } catch (\Throwable $e) { /* message too old to edit — ignore */ }
                try {
                    $telegram->answerCallbackQuery([
                            'callback_query_id' => $result->get('callback_query')['id'],
                            'text'              => '✅ Заявка уже завершена',
                            'show_alert'        => true,
                    ]);
                } catch (\Throwable $e) { /* callback expired — ignore */ }
                return;
            }

            // Проверяем, не была ли заявка уже принята (защита от повторных нажатий в общем чате)
            if ($deal['STAGE_ID'] == botManager::DRIVER_ACCEPTED_STAGE_ID || $deal['STAGE_ID'] == botManager::TRAVEL_STARTED_STAGE_ID) {
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal $dealId already accepted (stage: {$deal['STAGE_ID']})\n", FILE_APPEND);

                // Проверяем, является ли текущий чат общим чатом водителей
                $isDriversGroupChat = ($chatId == botManager::DRIVERS_GROUP_CHAT_ID);

                if ($isDriversGroupChat && $buttonData[0] == 'accept') {
                    // В общем чате блокируем повторное принятие
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

                    // Не спамим в группу — только личный попап нажавшему водителю
                    $telegram->answerCallbackQuery([
                            'callback_query_id' => $result->get('callback_query')['id'],
                            'text' => '✅ Заявка уже принята водителем ' . $driverName,
                            'show_alert' => true
                    ]);
                    exit;
                } else {
                    // В личном чате или для других действий - разрешаем
                    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Allowing action in personal chat or other action\n", FILE_APPEND);
                }
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

    /**
     * Safe wrapper for answerCallbackQuery - never throws on "query too old" errors
     */
    private static function safeAnswerCallback(Api $telegram, Update $result, string $text = "", bool $showAlert = false): void {
        try {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => $text,
                'show_alert' => $showAlert
            ]);
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', 
                date('Y-m-d H:i:s') . " - safeAnswerCallback failed (non-critical): " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    public static function driverAcceptHandle ($telegram, $result, int $dealId): void {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - driverAcceptHandle started for deal $dealId\n", FILE_APPEND);
        
        if (!class_exists("CRest")) { require_once(__DIR__ . "/crest/crest.php"); }
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealId,
            'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD, botManager::INTERMEDIATE_POINTS_FIELD, botManager::CAR_CLASS_FIELD] // Добавляем все необходимые поля
        ])['result'];
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal loaded: " . ($deal['ID'] ?? 'NOT_FOUND') . "\n", FILE_APPEND);
        if(empty($deal['ID'])) {
            self::safeAnswerCallback($telegram, $result);
            exit;
        }

        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Getting message and chat info\n", FILE_APPEND);
        $callbackQuery = $result->get('callback_query');
        $chatId = $callbackQuery->get('message')['chat']['id'];
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - ChatId: $chatId\n", FILE_APPEND);
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Current driver ID: " . ($deal[botManager::DRIVER_ID_FIELD] ?? 'EMPTY') . "\n", FILE_APPEND);
        
        // Получаем Telegram ID нажавшего
        $currentDriverId = $deal[botManager::DRIVER_ID_FIELD];
        $telegramId = $result->callbackQuery->from->id;
        $message = $result->getMessage();
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Current driver ID: $currentDriverId, Telegram ID: $telegramId\n", FILE_APPEND);


        // ПРАВИЛЬНАЯ ЛОГИКА:
        // 1. Если водитель НЕ назначен (не должно быть при правильной настройке)
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
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - About to update deal $dealId in Bitrix24\n", FILE_APPEND);
                
                $updateResult = \CRest::call('crm.deal.update', [
                    'id' => $dealId, 
                    'fields' => [
                        botManager::DRIVER_ID_FIELD => $driverId,
                        'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID,
                        // Инициализируем SERVICE поля сразу, чтобы избежать ложных уведомлений
                        botManager::DRIVER_SUM_FIELD_SERVICE => $deal[botManager::DRIVER_SUM_FIELD],
                        botManager::ADDRESS_FROM_FIELD_SERVICE => $deal[botManager::ADDRESS_FROM_FIELD],
                        botManager::ADDRESS_TO_FIELD_SERVICE => $deal[botManager::ADDRESS_TO_FIELD],
                        botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => $deal[botManager::TRAVEL_DATE_TIME_FIELD],
                        botManager::PASSENGERS_FIELD_SERVICE => $deal['UF_CRM_1751271798896'],
                        botManager::CAR_CLASS_FIELD_SERVICE => $deal[botManager::CAR_CLASS_FIELD],
                        botManager::INTERMEDIATE_POINTS_FIELD_SERVICE => $deal[botManager::INTERMEDIATE_POINTS_FIELD],
                        // Инициализируем служебное поле номера заявки
                        botManager::ORDER_NUMBER_SERVICE_FIELD => self::extractOrderNumber($deal['TITLE'] ?? '')
                    ]
                ]);
                
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', 
                    date('Y-m-d H:i:s') . " - Deal update result: " . json_encode($updateResult) . "\n", FILE_APPEND);

                // Проверка race condition: убеждаемся что заявку взяли именно мы
                $verifyDeal = \CRest::call('crm.deal.get', [
                    'id' => $dealId,
                    'select' => [botManager::DRIVER_ID_FIELD]
                ])['result'];
                $assignedDriverId = $verifyDeal[botManager::DRIVER_ID_FIELD] ?? null;
                if ($assignedDriverId != $driverId) {
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => '⚠️ Заявка уже принята другим водителем',
                        'show_alert' => true
                    ]);
                    return;
                }

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
                $updateResult = \CRest::call('crm.deal.update', [
                    'id' => $dealId, 
                    'fields' => [
                        botManager::DRIVER_ID_FIELD => 9,
                        'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID,
                        // Инициализируем SERVICE поля сразу, чтобы избежать ложных уведомлений
                        botManager::DRIVER_SUM_FIELD_SERVICE => $deal[botManager::DRIVER_SUM_FIELD],
                        botManager::ADDRESS_FROM_FIELD_SERVICE => $deal[botManager::ADDRESS_FROM_FIELD],
                        botManager::ADDRESS_TO_FIELD_SERVICE => $deal[botManager::ADDRESS_TO_FIELD],
                        botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => $deal[botManager::TRAVEL_DATE_TIME_FIELD],
                        botManager::PASSENGERS_FIELD_SERVICE => $deal['UF_CRM_1751271798896'],
                        botManager::CAR_CLASS_FIELD_SERVICE => $deal[botManager::CAR_CLASS_FIELD],
                        botManager::INTERMEDIATE_POINTS_FIELD_SERVICE => $deal[botManager::INTERMEDIATE_POINTS_FIELD],
                        // Инициализируем служебное поле номера заявки
                        botManager::ORDER_NUMBER_SERVICE_FIELD => self::extractOrderNumber($deal['TITLE'] ?? '')
                    ]
                ]);

                // Проверка race condition: убеждаемся что заявку взяли именно мы
                $verifyDeal = \CRest::call('crm.deal.get', [
                    'id' => $dealId,
                    'select' => [botManager::DRIVER_ID_FIELD]
                ])['result'];
                $assignedDriverId = $verifyDeal[botManager::DRIVER_ID_FIELD] ?? null;
                if ($assignedDriverId != 9) {
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => '⚠️ Заявка уже принята другим водителем',
                        'show_alert' => true
                    ]);
                    return;
                }

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
            
            return;
        }
        
        // 2. Если назначен технический водитель ID 9 - любой зарегистрированный может взять
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
                            botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => $deal[botManager::TRAVEL_DATE_TIME_FIELD],
                                botManager::PASSENGERS_FIELD_SERVICE => $deal['UF_CRM_1751271798896'],
                                botManager::CAR_CLASS_FIELD_SERVICE => $deal[botManager::CAR_CLASS_FIELD],
                            botManager::INTERMEDIATE_POINTS_FIELD_SERVICE => $deal[botManager::INTERMEDIATE_POINTS_FIELD],
                            // Инициализируем служебное поле номера заявки
                            botManager::ORDER_NUMBER_SERVICE_FIELD => self::extractOrderNumber($deal['TITLE'] ?? '')
                        ]
                    ]);

                    // Проверка race condition: убеждаемся что заявку взяли именно мы
                    $verifyDeal = \CRest::call('crm.deal.get', [
                        'id' => $dealId,
                        'select' => [botManager::DRIVER_ID_FIELD]
                    ])['result'];
                    $assignedDriverId = $verifyDeal[botManager::DRIVER_ID_FIELD] ?? null;
                    if ($assignedDriverId != $driverId) {
                        $telegram->answerCallbackQuery([
                            'callback_query_id' => $result->callbackQuery->id,
                            'text' => '⚠️ Заявка уже принята другим водителем',
                            'show_alert' => true
                        ]);
                        return;
                    }

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
                        'callback_query_id' => $result->get('callback_query')['id'],
                        'text' => 'Заявка принята! Детали отправлены в личные сообщения.',
                        'show_alert' => true
                    ]);
                    
                } else {
                    // НЕЗАРЕГИСТРИРОВАННЫЙ ВОДИТЕЛЬ - отказываем
                    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Unregistered driver trying to take deal from ID 9, rejecting\n", FILE_APPEND);
                    
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->get('callback_query')['id'],
                        'text' => 'Только зарегистрированные водители могут взять эту заявку.',
                        'show_alert' => true
                    ]);
                }
                
                return;
            }
        
        // 3. Назначен конкретный водитель (не ID 9) - проверяем, что это именно он
        $assignedDriver = \CRest::call('crm.contact.get', [
            'id' => $currentDriverId,
            'select' => ['ID', 'NAME', 'LAST_NAME', botManager::DRIVER_TELEGRAM_ID_FIELD]
        ])['result'];
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Assigned driver Telegram ID: " . ($assignedDriver[botManager::DRIVER_TELEGRAM_ID_FIELD] ?? 'NONE') . "\n", FILE_APPEND);
        
        if (!$assignedDriver || $assignedDriver[botManager::DRIVER_TELEGRAM_ID_FIELD] != $telegramId) {
            // Не тот водитель - отказываем
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Wrong driver tried to accept (expected: " . ($assignedDriver[botManager::DRIVER_TELEGRAM_ID_FIELD] ?? 'NONE') . ", got: $telegramId)\n", FILE_APPEND);
            
            $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => 'Эта заявка назначена другому водителю.',
                'show_alert' => true
            ]);
            return;
        }
        
        // 4. Это правильный водитель - принимаем заявку
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Correct driver accepting deal\n", FILE_APPEND);

        // Проверяем, в какой стадии заявка сейчас
        $currentStage = $deal['STAGE_ID'];
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Current deal stage: $currentStage\n", FILE_APPEND);
        
        // Если заявка уже в стадии PREPAYMENT_INVOICE или EXECUTING - она уже была принята
        if ($currentStage == botManager::DRIVER_ACCEPTED_STAGE_ID || $currentStage == botManager::TRAVEL_STARTED_STAGE_ID) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal already accepted (stage: $currentStage)\n", FILE_APPEND);
            
            $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => 'Эта заявка уже была принята ранее.',
                'show_alert' => true
            ]);
            return;
        }
        
        // Если заявка в стадии PREPARATION (назначена водителю, но не принята) - принимаем её
        if ($currentStage != botManager::DRIVER_CHOICE_STAGE_ID) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Unexpected stage for accepting: $currentStage\n", FILE_APPEND);
            
            $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => 'Заявка находится в неожиданной стадии. Обратитесь к менеджеру.',
                'show_alert' => true
            ]);
            return;
        }

        // СНАЧАЛА отвечаем на callback (быстро!)
        try {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Attempting to answer callback\n", FILE_APPEND);
            $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => 'Заявка принята! Отправляем детали...',
                'show_alert' => false
            ]);
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Callback answered successfully\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Error answering callback: " . $e->getMessage() . " (continuing anyway)\n", FILE_APPEND);
        }
        
        $driverName = trim($assignedDriver['NAME'] . ' ' . $assignedDriver['LAST_NAME']);
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Driver name obtained: $driverName\n", FILE_APPEND);

        // Инициализируем SERVICE поля и меняем стадию на PREPAYMENT_INVOICE
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Updating stage to PREPAYMENT_INVOICE and initializing SERVICE fields\n", FILE_APPEND);
        
        $updateResult = \CRest::call('crm.deal.update', [
            'id' => $dealId,
            'fields' => [
                'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID,
                // Инициализируем SERVICE поля
                botManager::DRIVER_SUM_FIELD_SERVICE => $deal[botManager::DRIVER_SUM_FIELD],
                botManager::ADDRESS_FROM_FIELD_SERVICE => $deal[botManager::ADDRESS_FROM_FIELD],
                botManager::ADDRESS_TO_FIELD_SERVICE => $deal[botManager::ADDRESS_TO_FIELD],
                botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => $deal[botManager::TRAVEL_DATE_TIME_FIELD],
                botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE => $deal[botManager::ADDITIONAL_CONDITIONS_FIELD],
                botManager::PASSENGERS_FIELD_SERVICE => $deal['UF_CRM_1751271798896'],
                botManager::FLIGHT_NUMBER_FIELD_SERVICE => $deal[botManager::FLIGHT_NUMBER_FIELD],
                botManager::CAR_CLASS_FIELD_SERVICE => $deal[botManager::CAR_CLASS_FIELD],
                botManager::INTERMEDIATE_POINTS_FIELD_SERVICE => $deal[botManager::INTERMEDIATE_POINTS_FIELD],
                // Инициализируем служебное поле номера заявки
                botManager::ORDER_NUMBER_SERVICE_FIELD => self::extractOrderNumber($deal['TITLE'] ?? '')
            ]
        ]);

        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
            date('Y-m-d H:i:s') . " - Update result: " . (isset($updateResult['error']) ? 'ERROR: ' . json_encode($updateResult) : 'SUCCESS') . "\n", FILE_APPEND);
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Bitrix update completed\n", FILE_APPEND);
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Stage updated\n", FILE_APPEND);
        
        // Перезагружаем заявку
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealId,
            'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD]
        ])['result'];
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal reloaded successfully\n", FILE_APPEND);

        // Удаляем кнопки из сообщения в группе (с обработкой ошибок)
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Removing buttons from group message\n", FILE_APPEND);
        
        try {
            $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Buttons removed successfully\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Error removing buttons: " . $e->getMessage() . " (continuing anyway)\n", FILE_APPEND);
        } catch (Error $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Fatal error removing buttons: " . $e->getMessage() . " (continuing anyway)\n", FILE_APPEND);
        }
        
        // Отправляем уведомление в группу
        $orderNumber = $deal['TITLE'] ?? $dealId;
        if (strpos($orderNumber, 'Заявка: ') === 0) {
            $orderNumber = substr($orderNumber, 8);
        }
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Sending group notification\n", FILE_APPEND);
        
        $groupMessage = "✅ Заявку #$orderNumber принял водитель: <b>$driverName</b>";
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $groupMessage,
            'parse_mode' => 'HTML'
        ]);
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Group notification sent\n", FILE_APPEND);
        
        // Отправляем детали в ЛС водителю
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Sending details to driver private chat (ID: $telegramId)\n", FILE_APPEND);
        
        $detailedMessage = botManager::orderTextForDriver($deal);
        $privateKeyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"],
                    ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]
                ]
            ]
        ];
        
        try {
            $telegram->sendMessage([
                'chat_id' => $telegramId,
                'text' => $detailedMessage,
                'reply_markup' => json_encode($privateKeyboard),
                'parse_mode' => 'HTML'
            ]);
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Private message sent successfully\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Error sending private message: " . $e->getMessage() . "\n", FILE_APPEND);
        }
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - All messages sent successfully\n", FILE_APPEND);
    }

    public static function cancelHandle(int $dealId, Api $telegram, Update $result) {
        // Отвечаем на callback - ошибка не блокирует основную логику
        self::safeAnswerCallback($telegram, $result, 'Отменить выполнение?');
        
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $keyboard = new Keyboard();

        // Включаем inline-режим
        $keyboard->inline();

        // Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => 'Да', 'callback_data' => "cancelYes_$dealId"]),
                Keyboard::inlineButton(['text' => 'Нет', 'callback_data' => "cancelNo_$dealId"]),
        ]);
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
        ]);
    }

    public static function finishHandle(int $dealId, Api $telegram, Update $result) {
        // Отвечаем на callback - ошибка не блокирует основную логику
        self::safeAnswerCallback($telegram, $result, 'Завершить заявку?');
        
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $keyboard = new Keyboard();

        // Включаем inline-режим
        $keyboard->inline();

        // Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => 'Да', 'callback_data' => "finishYes_$dealId"]),
                Keyboard::inlineButton(['text' => 'Нет', 'callback_data' => "finishNo_$dealId"]),
        ]);
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
        ]);
    }

    public static function finishYesHandle($dealId, Update $result, Api $telegram) {
        // Отвечаем на callback - ошибка не блокирует основную логику
        self::safeAnswerCallback($telegram, $result, '✅ Заявка завершена!');
        
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
                'reply_markup' => null
        ]);
    }

    public static function finishNoHandle(int $dealId, Api $telegram, Update $result) {
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            self::safeAnswerCallback($telegram, $result);
            exit;
        }
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        // B13 fix: destructive stage reset removed (was setting EXECUTING on "No" — Rostov was correct skipping)
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
        if (!class_exists("CRest")) { require_once('/home/telegramBot/crest/crest.php'); }
        
        // СНАЧАЛА отвечаем на callback
        try {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => 'Выполнение отменено!',
                    'show_alert' => false
            ]);
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Error answering callback: " . $e->getMessage() . "\n", FILE_APPEND);
        }
        
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            return;
        }
        
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();

        // Меняем стадию обратно на PREPAYMENT_INVOICE
        $dealUpdate = \CRest::call('crm.deal.update', ['id' => $dealId, 'fields'=>[
                'STAGE_ID'=>botManager::DRIVER_ACCEPTED_STAGE_ID,
        ]
        ]);
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Stage reverted to PREPAYMENT_INVOICE\n", FILE_APPEND);

        // Возвращаем кнопки "Начать выполнение"
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
        
        // Уведомляем ответственного
        $notify = \CRest::call('im.notify.system.add', [
                'USER_ID' => $deal['ASSIGNED_BY_ID'],
                'MESSAGE'=>"Водитель отменил выполнение заявки " . ($deal['TITLE'] ?? "#$dealId") . ". <a href='https://meetride.bitrix24.ru/crm/deal/details/$dealId/'>Открыть заявку</a>"
        ]);
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - cancelYesHandle completed\n", FILE_APPEND);
    }

    public static function cancelNoHandle(int $dealId, Api $telegram, Update $result) {
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            self::safeAnswerCallback($telegram, $result);
            exit;
        }
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        // B13 fix: destructive stage reset removed (was setting EXECUTING on "No" — Rostov was correct skipping)
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
        // Отвечаем на callback - ошибка не блокирует основную логику
        try {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => 'Выполнение началось!',
                    'show_alert' => false
            ]);
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - answerCallbackQuery failed in travelStartYesHandle (non-critical): " . $e->getMessage() . "\n", FILE_APPEND);
        }
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - travelStartYesHandle: Callback answered\n", FILE_APPEND);
        
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            exit;
        }
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - travelStartYesHandle: Updating stage to EXECUTING\n", FILE_APPEND);
        
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
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - travelStartYesHandle: Updating buttons for message " . $message->getMessageId() . "\n", FILE_APPEND);
        
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
        ]);
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - travelStartYesHandle: Complete\n", FILE_APPEND);
    }

    public static function travelStartNoHandle(Api $telegram, Update $result, int $dealId) {
        if (!class_exists("CRest")) { require_once(__DIR__ . "/crest/crest.php"); }
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            self::safeAnswerCallback($telegram, $result);
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
        
        try {
            // Проверяем заявку
            if (!class_exists("CRest")) { require_once('/home/telegramBot/crest/crest.php'); }
            $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal stage: " . $deal['STAGE_ID'] . "\n", FILE_APPEND);
            
            // Проверяем, что заявка в правильной стадии
            if ($deal['STAGE_ID'] != botManager::DRIVER_ACCEPTED_STAGE_ID) {
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Wrong stage for start button: " . $deal['STAGE_ID'] . " (expected: PREPAYMENT_INVOICE)\n", FILE_APPEND);
                $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => 'Сначала примите заявку!',
                        'show_alert' => true
                ]);
                return;
            }
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Answering callback query\n", FILE_APPEND);
            
            // СНАЧАЛА отвечаем на callback - ошибка не блокирует основную логику
            try {
                $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => 'Вы уверены? Нажмите Да для подтверждения.',
                        'show_alert' => false
                ]);
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Callback answered\n", FILE_APPEND);
            } catch (\Exception $e) {
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - answerCallbackQuery failed (non-critical): " . $e->getMessage() . "\n", FILE_APPEND);
            }
            
            // ПОТОМ обновляем кнопки
            $message = $result->getMessage();
            $chatId = $message->getChat()->getId();
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Chat ID: $chatId, Message ID: " . $message->getMessageId() . "\n", FILE_APPEND);
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Да', 'callback_data' => "startYes_$dealId"],
                        ['text' => 'Нет', 'callback_data' => "startNo_$dealId"]
                    ]
                ]
            ];
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Editing message markup\n", FILE_APPEND);
            
            try {
                $telegram->editMessageReplyMarkup([
                        'chat_id' => $chatId,
                        'message_id' => $message->getMessageId(),
                        'reply_markup' => json_encode($keyboard)
                ]);
            } catch (\Exception $e) {
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - editMessageReplyMarkup failed (non-critical): " . $e->getMessage() . "\n", FILE_APPEND);
            }
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - travelStartHandle completed successfully\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - ERROR in travelStartHandle: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
        }
    }

    public static function driverRejectHandle ($telegram, $result, int $dealId):void {
        if (!class_exists("CRest")) { require_once('/home/telegramBot/crest/crest.php'); }
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - driverRejectHandle called for deal $dealId\n", FILE_APPEND);
        
        // СНАЧАЛА отвечаем на callback
        try {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => 'Отказ принят',
                    'show_alert' => false
            ]);
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Callback answered\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Error answering callback: " . $e->getMessage() . "\n", FILE_APPEND);
        }
        
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            return;
        }
        
        // ЗАЩИТА ОТ СПАМА: если водитель уже сброшен и стадия NEW - это повторный вызов
        if ($deal[botManager::DRIVER_ID_FIELD] == 0 && $deal['STAGE_ID'] == botManager::NEW_DEAL_STAGE_ID) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Reject ignored (already rejected, driver=0, stage=NEW)\n", FILE_APPEND);
            return;
        }
        
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        
        // Получаем имя водителя из CRM контакта
        $driverName = 'Водитель';
        
        if ($deal[botManager::DRIVER_ID_FIELD] > 0) {
            $driverContact = \CRest::call('crm.contact.get', [
                'id' => $deal[botManager::DRIVER_ID_FIELD],
                'select' => ['NAME', 'LAST_NAME']
            ])['result'];
            
            if ($driverContact) {
                $driverName = trim($driverContact['NAME'] . ' ' . $driverContact['LAST_NAME']);
            }
        }
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Sending reject message to chat $chatId from $driverName\n", FILE_APPEND);
        
        // Получаем номер заявки из TITLE
        $orderNumber = $deal['TITLE'] ?? $dealId;
        if (strpos($orderNumber, 'Заявка: ') === 0) {
            $orderNumber = substr($orderNumber, 8);
        } elseif (strpos($orderNumber, 'Сделка #') === 0) {
            $orderNumber = substr($orderNumber, 8);
        }
        
        // Отправляем сообщение об отказе в группу
        try {
            $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text'    => "❌ Водитель <b>$driverName</b> отказался от заявки #$orderNumber",
                    'parse_mode' => 'HTML'
            ]);
        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Error sending reject message: " . $e->getMessage() . "\n", FILE_APPEND);
        }
        
        // Обновляем сделку - сбрасываем водителя и возвращаем стадию
        // ВАЖНО: Сохраняем оригинальный номер заявки (не меняем TITLE на ID!)
        // Получаем текущий TITLE и сохраняем его в служебное поле, если оно пустое
        $currentTitle = $deal['TITLE'] ?? '';
        $serviceOrderNumber = $deal[botManager::ORDER_NUMBER_SERVICE_FIELD] ?? '';
        
        // Если служебное поле пустое, сохраняем очищенный номер из TITLE
        if (empty($serviceOrderNumber) && !empty($currentTitle)) {
            $cleanOrderNumber = $currentTitle;
            if (strpos($cleanOrderNumber, 'Заявка: ') === 0) {
                $cleanOrderNumber = substr($cleanOrderNumber, 8);
            } elseif (strpos($cleanOrderNumber, 'Сделка #') === 0) {
                $cleanOrderNumber = substr($cleanOrderNumber, 8);
            }
            $serviceOrderNumber = $cleanOrderNumber;
        }
        
        // Если TITLE не соответствует формату "Заявка: номер", восстанавливаем из служебного поля
        $finalTitle = $currentTitle;
        if (!empty($serviceOrderNumber) && 
            (strpos($currentTitle, 'Заявка: ') !== 0 && strpos($currentTitle, 'Сделка #') !== 0)) {
            $finalTitle = 'Заявка: ' . $serviceOrderNumber;
        }
        
        $dealUpdate = \CRest::call('crm.deal.update', [
                'id' => $dealId,
                'fields'=>[
                    botManager::DRIVER_ID_FIELD => 0,
                    'STAGE_ID' => botManager::NEW_DEAL_STAGE_ID,  // Возвращаем на стадию "Новая заявка"
                    'TITLE' => $finalTitle,  // Восстанавливаем правильный номер заявки
                    botManager::ORDER_NUMBER_SERVICE_FIELD => $serviceOrderNumber,  // Сохраняем в служебное поле
                    botManager::GROUP_MESSAGE_SENT_FIELD => '',  // Очищаем флаг — следующая PREPARATION гарантированно отправит в чат
                    botManager::DRIVER_SUM_FIELD_SERVICE => '',
                    botManager::ADDRESS_FROM_FIELD_SERVICE => '',
                    botManager::ADDRESS_TO_FIELD_SERVICE => '',
                    botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => '',
                    botManager::PASSENGERS_FIELD_SERVICE => '',
                    botManager::INTERMEDIATE_POINTS_FIELD_SERVICE => '',
                    botManager::CAR_CLASS_FIELD_SERVICE => '',
                ]
        ]);
        
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', date('Y-m-d H:i:s') . " - Deal $dealId rejected, stage reset to NEW\n", FILE_APPEND);
        
        // Уведомляем ответственного
        if($deal[botManager::DRIVER_ID_FIELD] > 0) {
            \CRest::call('im.notify.system.add', [
                'USER_ID' => $deal['ASSIGNED_BY_ID'],
                'MESSAGE'=>"Водитель отказался от заявки #$orderNumber. <a href='https://meetride.bitrix24.ru/crm/deal/details/$dealId/'>Открыть заявку</a>"
            ]);
        }
        // УБРАЛИ рассылку всем водителям в личку - по новой логике заявка остается в общем чате
        // Водители могут взять заявку из общего чата, нажав кнопку "Принять"
            self::safeAnswerCallback($telegram, $result);
    }

    /**
     * Обрабатывает изменения полей заявки и отправляет уведомления водителю
     *
     * НОВАЯ ЛОГИКА (БЕЗ SERVICE ПОЛЕЙ):
     * - Получаем старые значения из $_REQUEST['data']['FIELDS']['OLD']
     * - Сравниваем с текущими значениями
     * - Отправляем уведомление водителю только если он взял/выполняет заявку
     *
     * ОТСЛЕЖИВАЕМЫЕ ПОЛЯ (только 5 по ТЗ):
     * 1. Точка А (откуда)
     * 2. Точка Б (куда)
     * 3. Время поездки
     * 4. Промежуточные точки
     * 5. Пассажиры
     *
     * @param int $dealId ID сделки
     * @param Api $telegram Объект Telegram API
     * @param Update $result Объект Update (не используется в новой версии)
     * @param array|null $oldValues Старые значения полей из webhook (опционально)
     */
    public static function dealChangeHandle(int $dealId, Api $telegram, Update $result, ?array $oldValues = null): void {
        if (!class_exists("CRest")) { require_once("/home/telegramBot/crest/crest.php"); }

        // Логирование начала обработки
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
            date('Y-m-d H:i:s') . " - dealChangeHandle started for deal $dealId\n", FILE_APPEND);

        // Получаем текущую сделку СО ВСЕМИ SERVICE полями
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealId,
            'select' => [
                '*',
                botManager::PASSENGERS_FIELD, // Пассажиры
                botManager::INTERMEDIATE_POINTS_FIELD, // Промежуточные точки
                botManager::ADDRESS_FROM_FIELD_SERVICE, // SERVICE: Откуда
                botManager::ADDRESS_TO_FIELD_SERVICE, // SERVICE: Куда
                botManager::TRAVEL_DATE_TIME_FIELD_SERVICE, // SERVICE: Время
                botManager::INTERMEDIATE_POINTS_FIELD_SERVICE, // SERVICE: Промежуточные точки
                botManager::PASSENGERS_FIELD_SERVICE, // SERVICE: Пассажиры
            ]
        ])['result'];

        if (empty($deal['ID'])) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - Deal $dealId not found\n", FILE_APPEND);
            return;
        }

        // Проверяем стадию - уведомляем только если водитель взял или выполняет заявку
        if ($deal['STAGE_ID'] !== botManager::DRIVER_ACCEPTED_STAGE_ID &&
            $deal['STAGE_ID'] !== botManager::TRAVEL_STARTED_STAGE_ID) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - Deal $dealId stage is {$deal['STAGE_ID']}, skipping notification\n", FILE_APPEND);
            return;
        }

        // Получаем данные водителя
        $driver = \CRest::call('crm.contact.get', [
            'id' => $deal[botManager::DRIVER_ID_FIELD],
            'select' => ['ID', botManager::DRIVER_TELEGRAM_ID_FIELD]
        ])['result'];

        if (empty($driver['ID']) || empty($driver[botManager::DRIVER_TELEGRAM_ID_FIELD])) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - Driver not found or no Telegram ID for deal $dealId\n", FILE_APPEND);
            return;
        }

        $driverTelegramId = (int) $driver[botManager::DRIVER_TELEGRAM_ID_FIELD];

        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
            date('Y-m-d H:i:s') . " - Using SERVICE fields for change detection\n", FILE_APPEND);

        // Проверяем изменения используя SERVICE поля (а не OLD values из webhook)
        $changes = [];
        $updateServiceFields = [];

        // 1. Точка А (откуда)
        $serviceAddressFrom = $deal[botManager::ADDRESS_FROM_FIELD_SERVICE] ?? '';
        $currentAddressFrom = $deal[botManager::ADDRESS_FROM_FIELD] ?? '';
        
        if ($serviceAddressFrom && $serviceAddressFrom != $currentAddressFrom && !empty($currentAddressFrom)) {
            $changes[] = [
                'field' => 'addressFrom',
                'emoji' => '🅰️',
                'label' => 'Откуда',
                'old' => $serviceAddressFrom,
                'new' => $currentAddressFrom
            ];
            $updateServiceFields[botManager::ADDRESS_FROM_FIELD_SERVICE] = $currentAddressFrom;
        }

        // 2. Точка Б (куда)
        $serviceAddressTo = $deal[botManager::ADDRESS_TO_FIELD_SERVICE] ?? '';
        $currentAddressTo = $deal[botManager::ADDRESS_TO_FIELD] ?? '';
        
        if ($serviceAddressTo && $serviceAddressTo != $currentAddressTo && !empty($currentAddressTo)) {
            $changes[] = [
                'field' => 'addressTo',
                'emoji' => '🅱️',
                'label' => 'Куда',
                'old' => $serviceAddressTo,
                'new' => $currentAddressTo
            ];
            $updateServiceFields[botManager::ADDRESS_TO_FIELD_SERVICE] = $currentAddressTo;
        }

        // 3. Время поездки
        $serviceDateTime = $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE] ?? '';
        $currentDateTime = $deal[botManager::TRAVEL_DATE_TIME_FIELD] ?? '';
        
        if ($serviceDateTime && $serviceDateTime != $currentDateTime && !empty($currentDateTime)) {
            // Форматируем дату в человеческий вид
            $oldFormatted = $serviceDateTime;
            $newFormatted = $currentDateTime;

            if ($serviceDateTime) {
                try {
                    $oldDate = new \DateTime($serviceDateTime);
                    $oldFormatted = $oldDate->format('d.m.Y H:i');
                } catch (\Exception $e) {}
            }

            if ($currentDateTime) {
                try {
                    $newDate = new \DateTime($currentDateTime);
                    $newFormatted = $newDate->format('d.m.Y H:i');
                } catch (\Exception $e) {}
            }

            $changes[] = [
                'field' => 'dateTime',
                'emoji' => '⏰',
                'label' => 'Дата и время',
                'old' => $oldFormatted,
                'new' => $newFormatted
            ];
            $updateServiceFields[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE] = $currentDateTime;
        }

        // 4. Промежуточные точки
        $serviceIntermediate = $deal[botManager::INTERMEDIATE_POINTS_FIELD_SERVICE] ?? '';
        $currentIntermediate = $deal[botManager::INTERMEDIATE_POINTS_FIELD] ?? '';
        
        // Обработка массивов
        if (is_array($currentIntermediate)) {
            $currentIntermediate = implode(", ", $currentIntermediate);
        }
        if (is_array($serviceIntermediate)) {
            $serviceIntermediate = implode(", ", $serviceIntermediate);
        }
        
        if ($serviceIntermediate && $serviceIntermediate != $currentIntermediate) {
            $changes[] = [
                'field' => 'intermediatePoints',
                'emoji' => '🗺️',
                'label' => 'Промежуточные точки',
                'old' => $serviceIntermediate ?: 'Не указано',
                'new' => $currentIntermediate ?: 'Не указано'
            ];
            $updateServiceFields[botManager::INTERMEDIATE_POINTS_FIELD_SERVICE] = $currentIntermediate;
        }
        
        // 5. Пассажиры
        $servicePassengers = $deal[botManager::PASSENGERS_FIELD_SERVICE] ?? '';
        $currentPassengers = $deal[botManager::PASSENGERS_FIELD] ?? '';
        
        // Обработка массивов
        if (is_array($currentPassengers)) {
            $currentPassengers = implode(", ", $currentPassengers);
        }
        if (is_array($servicePassengers)) {
            $servicePassengers = implode(", ", $servicePassengers);
        }
        
        if ($servicePassengers && $servicePassengers != $currentPassengers) {
            $changes[] = [
                'field' => 'passengers',
                'emoji' => '👥',
                'label' => 'Пассажиры',
                'old' => $servicePassengers ?: 'Не указано',
                'new' => $currentPassengers ?: 'Не указано'
            ];
            $updateServiceFields[botManager::PASSENGERS_FIELD_SERVICE] = $currentPassengers;
        }

        // Если изменений нет - ничего не отправляем
        if (empty($changes)) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - No changes detected for deal $dealId\n", FILE_APPEND);
            return;
        }

        // Проверяем, являются ли изменения инициализацией SERVICE полей при принятии заявки
        $isInitialization = true;
        foreach ($changes as $change) {
            $oldValue = $change['old'];
            // Если старое значение не пустое и не равно "Array" или пустой строке, то это реальные изменения
            if (!empty($oldValue) && $oldValue !== 'Array' && $oldValue !== '' && $oldValue !== 'Не указано') {
                $isInitialization = false;
                break;
            }
        }

        // Если это инициализация полей при принятии заявки - не отправляем уведомление
        if ($isInitialization) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - Changes are SERVICE field initialization, skipping notification for deal $dealId\n", FILE_APPEND);
            return;
        }

        // Формируем сообщение об изменениях
        $orderNumber = $deal['TITLE'] ?? $dealId;
        // Очищаем номер от префикса "Заявка: "
        if (strpos($orderNumber, 'Заявка: ') === 0) {
            $orderNumber = substr($orderNumber, 8);
        }

        $message = "🚗 Заявка #$orderNumber изменена:\n\n";

        foreach ($changes as $change) {
            $message .= "{$change['emoji']} {$change['label']}: <s>{$change['old']}</s> ➔ {$change['new']}\n\n";
        }

        // Убираем последний лишний перенос строки
        $message = rtrim($message);

        // Логируем отправку
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
            date('Y-m-d H:i:s') . " - Sending change notification for deal $dealId to driver $driverTelegramId\n", FILE_APPEND);
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
            date('Y-m-d H:i:s') . " - Message: $message\n", FILE_APPEND);

        // Отправляем уведомление водителю
        try {
            $telegram->sendMessage([
                'chat_id' => $driverTelegramId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - Change notification sent successfully for deal $dealId\n", FILE_APPEND);

            // ОБНОВЛЯЕМ SERVICE ПОЛЯ с текущими значениями
            if (!empty($updateServiceFields)) {
                \CRest::call('crm.deal.update', [
                    'id' => $dealId,
                    'fields' => $updateServiceFields
                ]);
                
                file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                    date('Y-m-d H:i:s') . " - SERVICE fields updated: " . print_r($updateServiceFields, true) . "\n", FILE_APPEND);
            }

        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - Error sending notification for deal $dealId: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    public static function commonMailing(int $dealId, Api $telegram, Update $result): void {
        if (!class_exists("CRest")) { require_once(__DIR__ . "/crest/crest.php"); }
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            self::safeAnswerCallback($telegram, $result);
            exit;
        }
        $dealUpdate = \CRest::call('crm.deal.update', [
                'id' => $dealId,
                'fields'=>[botManager::DRIVER_ID_FIELD => 0]
        ]);
        // УБРАЛИ commonMailing - по новой логике заявки отправляются только в общий чат
            self::safeAnswerCallback($telegram, $result);
    }

    /**
     * Отправляет сообщение в личку водителю с кнопками "Начать выполнение" при ручном переводе на 3-ю стадию
     */
    public static function sendPrivateMessageToDriver(int $dealId, int $driverTelegramId, Api $telegram): void {
        if (!class_exists("CRest")) { require_once("/home/telegramBot/crest/crest.php"); }

        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
            date('Y-m-d H:i:s') . " - sendPrivateMessageToDriver started for deal $dealId to driver $driverTelegramId\n", FILE_APPEND);

        // Получаем данные о сделке
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealId,
            'select' => [
                '*',
                botManager::PASSENGERS_FIELD, // Пассажиры
                botManager::INTERMEDIATE_POINTS_FIELD, // Промежуточные точки
                botManager::FLIGHT_NUMBER_FIELD, // Номер рейса
                botManager::CAR_CLASS_FIELD
            ]
        ])['result'];

        if (empty($deal['ID'])) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - Deal $dealId not found\n", FILE_APPEND);
            return;
        }

        // Получаем имя водителя
        $driver = \CRest::call('crm.contact.get', [
            'id' => $deal[botManager::DRIVER_ID_FIELD],
            'select' => ['NAME', 'LAST_NAME']
        ])['result'];

        $driverName = 'Водитель';
        if ($driver) {
            $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
        }

        // Формируем сообщение
        $orderNumber = $deal['TITLE'] ?? $dealId;
        if (strpos($orderNumber, 'Заявка: ') === 0) {
            $orderNumber = substr($orderNumber, 8);
        } elseif (strpos($orderNumber, 'Сделка #') === 0) {
            $orderNumber = substr($orderNumber, 8);
        }

        $message = "🚗 <b>Заявка #$orderNumber</b>\n\n";
        $message .= "⏰ <b>Время:</b> " . self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD] ?? null) . "\n\n";
        $carClassName = 'Не указано';
        if (!empty($deal[botManager::CAR_CLASS_FIELD])) {
            $carClassRaw = $deal[botManager::CAR_CLASS_FIELD];
            if (is_array($carClassRaw)) {
                $carClassRaw = !empty($carClassRaw) ? $carClassRaw[0] : 0;
            }
            $carClassName = botManager::getCarClassName((int)$carClassRaw);
        }
        $message .= "🚗 <b>Класс авто:</b> $carClassName\n\n";
        $message .= "🅰️ <b>Откуда:</b> " . ($deal[botManager::ADDRESS_FROM_FIELD] ?? 'Не указано') . "\n\n";

        // Промежуточные точки между А и Б
        if (!empty($deal[botManager::INTERMEDIATE_POINTS_FIELD])) {
            $intermediatePoints = $deal[botManager::INTERMEDIATE_POINTS_FIELD];
            if (is_array($intermediatePoints)) {
                $intermediatePoints = implode(", ", $intermediatePoints);
            }
            $message .= "🗺️ <b>Промежуточные точки:</b> $intermediatePoints\n\n";
        }

        $message .= "🅱️ <b>Куда:</b> " . ($deal[botManager::ADDRESS_TO_FIELD] ?? 'Не указано') . "\n\n";

        if (!empty($deal[botManager::PASSENGERS_FIELD])) {
            $passengers = $deal[botManager::PASSENGERS_FIELD];
            if (is_array($passengers)) {
                $passengers = implode(", ", $passengers);
            }
            $message .= "👥 <b>Пассажиры:</b> $passengers\n\n";
        }

        if (!empty($deal[botManager::FLIGHT_NUMBER_FIELD])) {
            $message .= "✈️ <b>Номер рейса:</b> " . $deal[botManager::FLIGHT_NUMBER_FIELD] . "\n\n";
        }

        if (!empty($deal[botManager::ADDITIONAL_CONDITIONS_FIELD])) {
            $additionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD];
            if (is_array($additionalConditions)) {
                $additionalConditions = implode(" | ", $additionalConditions);
            }
            $message .= "📝 <b>Дополнительные условия:</b> " . $additionalConditions . "\n\n";
        }

        $message .= "💰 <b>Сумма:</b> " . ($deal[botManager::DRIVER_SUM_FIELD] ?? 'Не указана') . " руб.\n\n";
        $message .= "Пожалуйста, подтвердите готовность к выполнению заявки";

        // Создаем клавиатуру с кнопками (inline keyboard)
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"],
                    ['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]
                ]
            ]
        ];

        try {
            // Отправляем сообщение с inline кнопками
            $telegram->sendMessage([
                'chat_id' => $driverTelegramId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ]);

            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - Private message sent successfully to driver $driverTelegramId for deal $dealId\n", FILE_APPEND);

            // Инициализируем SERVICE поля для отслеживания изменений
            $passengers = $deal[botManager::PASSENGERS_FIELD];
            if (is_array($passengers)) {
                $passengers = implode(", ", $passengers);
            }
            
            $intermediatePoints = $deal[botManager::INTERMEDIATE_POINTS_FIELD];
            if (is_array($intermediatePoints)) {
                $intermediatePoints = implode(", ", $intermediatePoints);
            }
            
            // Обрабатываем дополнительные условия (может быть массивом)
            $additionalConditions = $deal[botManager::ADDITIONAL_CONDITIONS_FIELD] ?? '';
            if (is_array($additionalConditions)) {
                $additionalConditions = implode(" | ", $additionalConditions);
            }
            
            \CRest::call('crm.deal.update', [
                'id' => $dealId,
                'fields' => [
                    botManager::ADDRESS_FROM_FIELD_SERVICE => $deal[botManager::ADDRESS_FROM_FIELD],
                    botManager::ADDRESS_TO_FIELD_SERVICE => $deal[botManager::ADDRESS_TO_FIELD],
                    botManager::TRAVEL_DATE_TIME_FIELD_SERVICE => $deal[botManager::TRAVEL_DATE_TIME_FIELD],
                    botManager::PASSENGERS_FIELD_SERVICE => $passengers,
                    botManager::INTERMEDIATE_POINTS_FIELD_SERVICE => $intermediatePoints,
                    botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE => $additionalConditions,
                    botManager::ORDER_NUMBER_SERVICE_FIELD => self::extractOrderNumber($deal['TITLE'] ?? '')
                ]
            ]);
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - SERVICE fields initialized for deal $dealId\n", FILE_APPEND);

        } catch (\Exception $e) {
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - Error sending private message to driver $driverTelegramId for deal $dealId: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    public static function groupAcceptHandle(int $dealId, string $chatId, Api $telegram, Update $result, $driverId): void {
        $message = $result->getMessage();
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealId,
            'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD] // Получаем все поля включая пассажиры и номер рейса
        ])['result'];
        if(empty($deal['ID'])) {
            self::safeAnswerCallback($telegram, $result);
            exit;
        }
        if(!$deal[botManager::DRIVER_ID_FIELD] && $deal['STAGE_ID'] === botManager::DRIVER_CHOICE_STAGE_ID) {
            \CRest::call('crm.deal.update', ['id' => $dealId, 'fields'=>[
                botManager::DRIVER_ID_FIELD => $driverId,
                'STAGE_ID'=>botManager::DRIVER_ACCEPTED_STAGE_ID,
                botManager::DRIVER_SUM_FIELD_SERVICE=>$deal[botManager::DRIVER_SUM_FIELD],
                botManager::ADDRESS_FROM_FIELD_SERVICE=>$deal[botManager::ADDRESS_FROM_FIELD],
                botManager::ADDRESS_TO_FIELD_SERVICE=>$deal[botManager::ADDRESS_TO_FIELD],
                botManager::TRAVEL_DATE_TIME_FIELD_SERVICE=>$deal[botManager::TRAVEL_DATE_TIME_FIELD],
                botManager::ADDITIONAL_CONDITIONS_FIELD_SERVICE=>$deal[botManager::ADDITIONAL_CONDITIONS_FIELD],
                botManager::PASSENGERS_FIELD_SERVICE=>$deal['UF_CRM_1751271798896'],
                botManager::FLIGHT_NUMBER_FIELD_SERVICE=>$deal[botManager::FLIGHT_NUMBER_FIELD],
                botManager::CAR_CLASS_FIELD_SERVICE=>$deal[botManager::CAR_CLASS_FIELD],
                botManager::INTERMEDIATE_POINTS_FIELD_SERVICE=>$deal[botManager::INTERMEDIATE_POINTS_FIELD],
                // Инициализируем служебное поле номера заявки
                botManager::ORDER_NUMBER_SERVICE_FIELD => self::extractOrderNumber($deal['TITLE'] ?? '')
            ]])['result'];
        }
        sleep(3);
        $deal = \CRest::call('crm.deal.get', [
            'id' => $dealId,
            'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD] // Добавляем поля "Пассажиры" и "Номер рейса"
        ])['result'];
        if(empty($deal['ID'])) {
            self::safeAnswerCallback($telegram, $result);
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
        $dateText = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD] ?? null);
        
        if ($newDate !== null) {
            // Форматируем старую и новую даты
            $oldDateFormatted = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE] ?? null);
            $newDateFormatted = self::formatDateTime($newDate);
            
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
$header

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
        $dateText = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD] ?? null);
        
        $fromAddress = $deal[botManager::ADDRESS_FROM_FIELD];
        $toAddress = $deal[botManager::ADDRESS_TO_FIELD];
        $sumText = $deal[botManager::DRIVER_SUM_FIELD];

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
        $dateText = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD] ?? null);
        
        $fromAddress = $deal[botManager::ADDRESS_FROM_FIELD];
        $toAddress = $deal[botManager::ADDRESS_TO_FIELD];
        
        // Получаем промежуточные точки
        $intermediatePointsText = '';
        if (!empty($deal[botManager::INTERMEDIATE_POINTS_FIELD])) {
            $intermediatePoints = $deal[botManager::INTERMEDIATE_POINTS_FIELD];
            if (is_array($intermediatePoints)) {
                $intermediatePoints = implode(", ", $intermediatePoints);
            }
            $intermediatePointsText = "\n🗺️ {$intermediatePoints}\n";
        }
        
        // Получаем класс автомобиля
        $carClassName = 'Не указано';
        if (!empty($deal[botManager::CAR_CLASS_FIELD])) {
            $carClassRaw = $deal[botManager::CAR_CLASS_FIELD];
            if (is_array($carClassRaw)) {
                $carClassRaw = !empty($carClassRaw) ? $carClassRaw[0] : 0;
            }
            $carClassName = botManager::getCarClassName((int)$carClassRaw);
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
{$intermediatePointsText}
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
        // B15 fix: was "$log .= $log;" (doubled content). Replaced with proper file write below.
        file_put_contents('/var/www/html/meetRiedeBot/logs/unknown_button_actions.log', $log, FILE_APPEND);
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
        if (!class_exists("CRest")) { require_once("/home/telegramBot/crest/crest.php"); }
        
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if (empty($deal['ID'])) {
            return false;
        }
        
        // Проверяем, что сделка в статусе "Водитель принял"
        if ($deal['STAGE_ID'] !== botManager::DRIVER_ACCEPTED_STAGE_ID) {
            return false;
        }
        
        // Проверяем, что напоминание еще не отправлялось (поле может содержать текст из SERVICE полей)
        if (!empty($deal[botManager::REMINDER_SENT_FIELD]) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)($deal[botManager::REMINDER_SENT_FIELD] ?? ''))) {
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
        if (!class_exists("CRest")) { require_once("/home/telegramBot/crest/crest.php"); }
        
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
        if (!class_exists("CRest")) { require_once("/home/telegramBot/crest/crest.php"); }
        
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if (empty($deal['ID'])) {
            return false;
        }
        
        // Проверяем, что уведомление еще не отправлялось (поле может содержать текст из SERVICE полей)
        if (!empty($deal[botManager::REMINDER_NOTIFICATION_SENT_FIELD]) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)($deal[botManager::REMINDER_NOTIFICATION_SENT_FIELD] ?? ''))) {
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
        if (!class_exists("CRest")) { require_once("/home/telegramBot/crest/crest.php"); }
        
        $result = [
            'reminders_sent' => 0,
            'notifications_sent' => 0,
            'errors' => []
        ];
        
        // Получаем все заявки в статусе "Водитель принял" (с пагинацией, лимит Bitrix24 = 50)
        $start = 0;
        $deals = [];
        do {
            $response = \CRest::call('crm.deal.list', [
                'filter' => ['STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID],
                'select' => ['ID', botManager::TRAVEL_DATE_TIME_FIELD, botManager::REMINDER_SENT_FIELD, botManager::REMINDER_CONFIRMED_FIELD],
                'start' => $start
            ]);
            $batch = $response['result'] ?? [];
            $deals = array_merge($deals, $batch);
            $start = isset($response['next']) ? (int)$response['next'] : null;
        } while ($start !== null && count($batch) > 0);
        
        foreach ($deals as $deal) {
            try {
                $travelTime = strtotime($deal[botManager::TRAVEL_DATE_TIME_FIELD]);
                $currentTime = time();
                $timeUntilTravel = $travelTime - $currentTime;
                
                // Проверяем, было ли уже отправлено напоминание
                $reminderSent = !empty($deal[botManager::REMINDER_SENT_FIELD]) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)($deal[botManager::REMINDER_SENT_FIELD] ?? ''));
                $reminderConfirmed = !empty($deal[botManager::REMINDER_CONFIRMED_FIELD]);
                
                // Если до поездки остался 1 час (3600 секунд) или меньше, и напоминание не отправлялось
                if ($timeUntilTravel <= 3600 && $timeUntilTravel > 0 && !$reminderSent && !$reminderConfirmed) {
                    if (botManager::sendTravelReminder($deal['ID'], $telegram)) {
                        $result['reminders_sent']++;
                    } else {
                        $result['errors'][] = "Ошибка отправки напоминания для заявки #{$deal['ID']}";
                    }
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Ошибка обработки заявки #{$deal['ID']}: " . $e->getMessage();
            }
        }
        
        // Проверяем заявки для отправки уведомлений ответственному (с пагинацией)
        $start = 0;
        $dealsForNotification = [];
        do {
            $response = \CRest::call('crm.deal.list', [
                'filter' => ['STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID],
                'select' => ['ID', botManager::REMINDER_SENT_FIELD, botManager::REMINDER_CONFIRMED_FIELD, botManager::REMINDER_NOTIFICATION_SENT_FIELD, 'ASSIGNED_BY_ID', 'TITLE'],
                'start' => $start
            ]);
            $batch = $response['result'] ?? [];
            $dealsForNotification = array_merge($dealsForNotification, $batch);
            $start = isset($response['next']) ? (int)$response['next'] : null;
        } while ($start !== null && count($batch) > 0);
        
        foreach ($dealsForNotification as $deal) {
            try {
                // Проверяем состояние заявки
                $reminderSent = !empty($deal[botManager::REMINDER_SENT_FIELD]) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)($deal[botManager::REMINDER_SENT_FIELD] ?? ''));
                $reminderConfirmed = !empty($deal[botManager::REMINDER_CONFIRMED_FIELD]);
                $notificationSent = !empty($deal[botManager::REMINDER_NOTIFICATION_SENT_FIELD]) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)($deal[botManager::REMINDER_NOTIFICATION_SENT_FIELD] ?? ''));
                
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
            } catch (\Exception $e) {
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
        $dateText = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD] ?? null);
        
        $fromAddress = $deal[botManager::ADDRESS_FROM_FIELD];
        $toAddress = $deal[botManager::ADDRESS_TO_FIELD];
        
        // Получаем промежуточные точки
        $intermediatePointsText = '';
        if (!empty($deal[botManager::INTERMEDIATE_POINTS_FIELD])) {
            $intermediatePoints = $deal[botManager::INTERMEDIATE_POINTS_FIELD];
            if (is_array($intermediatePoints)) {
                $intermediatePoints = implode(", ", $intermediatePoints);
            }
            $intermediatePointsText = "\n🗺️ <b>Промежуточные точки:</b> {$intermediatePoints}\n";
        }
        
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
        
        // Получаем класс автомобиля
        $carClassName = 'Не указано';
        if (!empty($deal[botManager::CAR_CLASS_FIELD])) {
            $carClassRaw = $deal[botManager::CAR_CLASS_FIELD];
            if (is_array($carClassRaw)) {
                $carClassRaw = !empty($carClassRaw) ? $carClassRaw[0] : 0;
            }
            $carClassName = botManager::getCarClassName((int)$carClassRaw);
        }

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

🚗 <b>Класс авто:</b> {$carClassName}

🅰️ <b>Откуда:</b> {$fromAddress}
{$intermediatePointsText}
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
        $dateText = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD] ?? null);
        
        if ($newDate !== null) {
            // Форматируем старую дату
            $oldDateFormatted = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE] ?? null);
            
            // Форматируем новую дату
            $newDateFormatted = self::formatDateTime($newDate);
            
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
        $dateText = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD] ?? null);
        
        if ($newDate !== null) {
            // Форматируем старую дату
            $oldDateFormatted = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE] ?? null);
            
            // Форматируем новую дату
            $newDateFormatted = self::formatDateTime($newDate);
            
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
            $carClassRaw = $deal[botManager::CAR_CLASS_FIELD];
            if (is_array($carClassRaw)) {
                $carClassRaw = !empty($carClassRaw) ? $carClassRaw[0] : 0;
            }
            $carClassName = botManager::getCarClassName((int)$carClassRaw);
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
                    $oldDate = self::formatDateTime($deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE] ?? null);
                    $newDate = self::formatDateTime($newValue);
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
