<?php

namespace Store;

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update;
use Illuminate\Support\Collection;


include('vendor/autoload.php');

class botManager {
//callback pattern =   action_dealId
    public const DRIVER_ID_FIELD                = 'UF_CRM_1751272181';
    public const DRIVER_TELEGRAM_ID_FIELD       = 'UF_CRM_1751185017761';
    public const ADDRESS_FROM_FIELD             = 'UF_CRM_1751269147414';
    public const ADDRESS_FROM_FIELD_SERVICE     = 'UF_CRM_1751638512';
    public const ADDRESS_TO_FIELD               = 'UF_CRM_1751269175432';
    public const ADDRESS_TO_FIELD_SERVICE       = 'UF_CRM_1751638529';
    public const ADDITIONAL_CONDITIONS_FIELD    = 'UF_CRM_1751269256380';
    public const DRIVER_SUM_FIELD               = 'UF_CRM_1751271862251';
    public const DRIVER_SUM_FIELD_SERVICE       = 'UF_CRM_1751638441407';
    public const TRAVEL_DATE_TIME_FIELD         = 'UF_CRM_1751269222959';
    public const TRAVEL_DATE_TIME_FIELD_SERVICE = 'UF_CRM_1751638617';
    public const DRIVER_ACCEPTED_STAGE_ID       = 'EXECUTING';
    public const NEW_DEAL_STAGE_ID              = 'NEW';
    public const DRIVER_CHOICE_STAGE_ID         = 'PREPARATION';
    public const TRAVEL_STARTED_STAGE_ID         = 'EXECUTING';
    public const FINISH_STAGE_ID         = 'FINAL_INVOICE';
    public const DRIVER_CONTACT_TYPE            = 'UC_C7O5J7';
    public const DRIVERS_GROUP_CHAT_ID          = -1002544521661; // Боевая группа водителей

    public static function newDealMessage(int $dealid, Api $telegram): ?Message {
        require_once('/home/telegramBot/crest/crest.php');
        $deal = \CRest::call('crm.deal.get', ['id' => $dealid])['result'];
        if(empty($deal['ID'])) {
            return null;
        }
        
        // Получаем информацию о назначенном водителе для отображения в сообщении
        $driver = \CRest::call('crm.contact.get', ['id' => $deal[botManager::DRIVER_ID_FIELD], 'select' => ['NAME', 'LAST_NAME']])['result'];
        $driverName = '';
        if($driver) {
            $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
        }
        
        $keyboard = new Keyboard();
        $keyboard->inline();

        // Кнопки доступны всем водителям
        $keyboard->row([
                Keyboard::inlineButton(['text' => '✅ Принять', 'callback_data' => "accept_$dealid"]),
                Keyboard::inlineButton(['text' => '❌ Отказаться', 'callback_data' => "reject_$dealid"]),
        ]);

        // Отправляем в общий чат водителей
        return $telegram->sendMessage(
                [
                        'chat_id'      => botManager::DRIVERS_GROUP_CHAT_ID,
                        'text'         => botManager::orderTextWithDriver($deal, $driverName),
                        'reply_markup' => $keyboard,
                        'parse_mode'   => 'HTML',
                ]
        );
    }

    public static function buttonHanlde(Api $telegram, Update $result) {

        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();

        $data = $result->callbackQuery->data;
        if ($data) {
            $buttonData = explode('_', $data);
            $dealId = (int) $buttonData[1];
            $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
            if(empty($deal['ID'])) {
                $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => '', // можно добавить всплывающее уведомление
                        'show_alert' => false
                ]);
                exit;
            }
            if(
                    $deal['STAGE_ID'] == botManager::FINISH_STAGE_ID
                    || $deal['STAGE_ID'] =='LOSE'
                    || $deal['STAGE_ID'] == 'WON'
                    || $deal['STAGE_ID'] == botManager::NEW_DEAL_STAGE_ID
            ) {
                $telegram->sendMessage(
                        [
                                'chat_id' => $chatId,
                                'text'    => "Заявка недоступна",
                        ]
                );
                $telegram->answerCallbackQuery([
                        'callback_query_id' => $result->callbackQuery->id,
                        'text' => '', // можно добавить всплывающее уведомление
                        'show_alert' => false
                ]);
                exit;
            }
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
                default => botManager::writeToLog("/logs/xxx.php", $buttonData[0],'$buttonData[0]', 'a'),
            };

            exit;
        }
    }

    public static function driverAcceptHandle (Api $telegram, Update $result, int $dealId): void {
        require_once(__DIR__ . '/../crest/crest.php');
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
        if(!$deal[botManager::DRIVER_ID_FIELD]) {
            $telegram->sendMessage(
                    [
                            'chat_id' => $chatId,
                            'text'    => "Используйте сообщение из общей рассылки",
                    ]
            );
            $telegram->deleteMessage([
                    'chat_id'    => $chatId,
                    'message_id' => $message->getMessageId(),

            ]);

            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            return;
        }


        $keyboard = new Keyboard();

        // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
        $keyboard->inline();

        // 3. Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"]),
                Keyboard::inlineButton(['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]),
        ]);
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
        ]);
        $dealUpdate = \CRest::call('crm.deal.update', ['id' => $dealId, 'fields'=>[
                'STAGE_ID'=>botManager::DRIVER_ACCEPTED_STAGE_ID,
                botManager::DRIVER_SUM_FIELD_SERVICE=>$deal[botManager::DRIVER_SUM_FIELD],
                botManager::ADDRESS_FROM_FIELD_SERVICE=>$deal[botManager::ADDRESS_FROM_FIELD],
                botManager::ADDRESS_TO_FIELD_SERVICE=>$deal[botManager::ADDRESS_TO_FIELD],
                botManager::TRAVEL_DATE_TIME_FIELD_SERVICE=>$deal[botManager::TRAVEL_DATE_TIME_FIELD]
        ]
        ]);
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
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
        require_once(__DIR__ . '/../crest/crest.php');
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


        $keyboard = new Keyboard();

        // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
        $keyboard->inline();

        // 3. Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"]),
                Keyboard::inlineButton(['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]),
        ]);
        $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $message->getMessageId(),
                'reply_markup' => $keyboard
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
        require_once(__DIR__ . '/../crest/crest.php');
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


        $keyboard = new Keyboard();

        // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
        $keyboard->inline();

        // 3. Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"]),
                Keyboard::inlineButton(['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]),
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

    public static function travelStartHandle(int $dealId, Api $telegram, Update $result) {
        $message = $result->getMessage();
        $chatId = $message->getChat()->getId();
        $keyboard = new Keyboard();

        // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
        $keyboard->inline();

        // 3. Добавляем строку с кнопками
        $keyboard->row([
                Keyboard::inlineButton(['text' => 'Да', 'callback_data' => "startYes_$dealId"]),
                Keyboard::inlineButton(['text' => 'Нет', 'callback_data' => "startNo_$dealId"]),
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

    public static function driverRejectHandle (Api $telegram, Update $result, int $dealId):void {
        require_once(__DIR__ . '/../crest/crest.php');
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
        $telegram->sendMessage(
                [
                        'chat_id' => $chatId,
                        'text'    => "вы отказались!",
                ]
        );
        $telegram->deleteMessage([
                'chat_id'    => $chatId,
                'message_id' => $message->getMessageId(),
        ]);
        $dealUpdate = \CRest::call('crm.deal.update', [
                'id' => $dealId,
                'fields'=>[botManager::DRIVER_ID_FIELD => 0]
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
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        
        // ЗАЩИТА ОТ СПАМА: Проверяем, что поля SERVICE не совпадают с основными
        // Если совпадают - значит уведомление уже было отправлено
        if ($deal[botManager::DRIVER_SUM_FIELD] === $deal[botManager::DRIVER_SUM_FIELD_SERVICE] &&
            $deal[botManager::ADDRESS_FROM_FIELD] === $deal[botManager::ADDRESS_FROM_FIELD_SERVICE] &&
            $deal[botManager::ADDRESS_TO_FIELD] === $deal[botManager::ADDRESS_TO_FIELD_SERVICE] &&
            $deal[botManager::TRAVEL_DATE_TIME_FIELD] === $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE]) {
            return; // Уведомление уже было отправлено - выходим
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
        $newSum = null;
        if ($deal[botManager::DRIVER_SUM_FIELD] !== $deal[botManager::DRIVER_SUM_FIELD_SERVICE]) {
            $newSum = (int) $deal[botManager::DRIVER_SUM_FIELD];
        }
        $newAddressFrom=null;
        if ($deal[botManager::ADDRESS_FROM_FIELD] !== $deal[botManager::ADDRESS_FROM_FIELD_SERVICE]) {
            $newAddressFrom = (string) $deal[botManager::ADDRESS_FROM_FIELD];
        }
        $newAddressTo=null;
        if ($deal[botManager::ADDRESS_TO_FIELD] !== $deal[botManager::ADDRESS_TO_FIELD_SERVICE]) {
            $newAddressTo = (string) $deal[botManager::ADDRESS_TO_FIELD];
        }
        $newDate=null;
        if ($deal[botManager::TRAVEL_DATE_TIME_FIELD] !== $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE]) {
            $newDate = (string) $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        }

        $telegram->sendMessage(
                [
                        'chat_id'      => $driverTelegramId,
                        'text'         => botManager::orderText($deal, $newSum, $newAddressFrom, $newAddressTo, $newDate),
                        'parse_mode' => 'HTML',
                ]
        );
        $dealUpdate = \CRest::call('crm.deal.update', ['id' => $dealId, 'fields'=>[
                // УБРАЛИ автоматическое изменение стадии - только обновляем service поля
                botManager::DRIVER_SUM_FIELD_SERVICE=>$deal[botManager::DRIVER_SUM_FIELD],
                botManager::ADDRESS_FROM_FIELD_SERVICE=>$deal[botManager::ADDRESS_FROM_FIELD],
                botManager::ADDRESS_TO_FIELD_SERVICE=>$deal[botManager::ADDRESS_TO_FIELD],
                botManager::TRAVEL_DATE_TIME_FIELD_SERVICE=>$deal[botManager::TRAVEL_DATE_TIME_FIELD]
        ]
        ]);
        $telegram->answerCallbackQuery([
                'callback_query_id' => $result->callbackQuery->id,
                'text' => '', // можно добавить всплывающее уведомление
                'show_alert' => false
        ]);
    }

    public static function commonMailing(int $dealId, Api $telegram, Update $result): void {
        require_once(__DIR__ . '/../crest/crest.php');
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
        $deal = \CRest::call('crm.deal.get', ['id' => $dealId])['result'];
        if(empty($deal['ID'])) {
            $telegram->answerCallbackQuery([
                    'callback_query_id' => $result->callbackQuery->id,
                    'text' => '', // можно добавить всплывающее уведомление
                    'show_alert' => false
            ]);
            exit;
        }
        if($deal[botManager::DRIVER_ID_FIELD] === $driverId) {
            $keyboard = new Keyboard();

            // 2. Включаем inline-режим (если нужны кнопки ВНУТРИ сообщения)
            $keyboard->inline();

            // 3. Добавляем строку с кнопками
            $keyboard->row([
                    Keyboard::inlineButton(['text' => '✅ Начать выполнение', 'callback_data' => "start_$dealId"]),
                    Keyboard::inlineButton(['text' => '❌ Отказаться', 'callback_data' => "reject_$dealId"]),
            ]);
            $telegram->editMessageReplyMarkup([
                    'chat_id' => $chatId,
                    'message_id' => $message->getMessageId(),
                    'reply_markup' => $keyboard
            ]);
            
            // Отправляем заявку в личку водителю
            $driverTelegramId = $result->callbackQuery->from->id;
            $driverName = $result->callbackQuery->from->first_name;
            if($result->callbackQuery->from->last_name) {
                $driverName .= ' ' . $result->callbackQuery->from->last_name;
            }
            
            $telegram->sendMessage([
                    'chat_id' => $driverTelegramId,
                    'text' => botManager::orderTextWithDriver($deal, $driverName),
                    'reply_markup' => $keyboard,
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
            $additionalConditions = implode(" | ", $deal[botManager::ADDITIONAL_CONDITIONS_FIELD]);
        }
        $dateText = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        if ($newDate !== null) {
            $dateText = "<s>{$deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE]}</s> ➔ {$newDate}";
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
            $sumText = "<s>{$deal[botManager::DRIVER_SUM_FIELD_SERVICE]}</s> ➔ {$newSum}|RUB";
        }

        $header = $deal['ID'];
        if($newSum || $newToAddress || $newFromAddress || $newDate) {
            $header = "Заявка {$deal['ID']} изменена:";
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
            $additionalConditions = implode(" | ", $deal[botManager::ADDITIONAL_CONDITIONS_FIELD]);
        }
        
        // Форматируем дату в удобочитаемый вид
        $dateText = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
        if ($dateText) {
            $date = new DateTime($dateText);
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

    public static function writeToLog($LogFileName, $info, $prefix, $wa) {
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
            $log = "<?php /*";
        }

        $log .= "\n------------------------\n";
        $log .= date("Y.m.d G:i:s") . "\n";
        $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
        $log .= print_r($data, 1);
        $log .= "\n------------------------\n";

        if ($wa == 'w') {
            file_put_contents(getcwd() . $LogFileName, $log);
        } else {
            file_put_contents(getcwd() . $LogFileName, $log, FILE_APPEND);
        }

        return true;
    }
}
