<?php
/**
 * Исправленная версия dealChangeHandle
 * Использует SERVICE поля вместо OLD values из webhook
 */

/**
 * Отслеживание изменений в полях сделки
 * 
 * @param int $dealId ID сделки
 * @param Api $telegram Telegram API
 * @param Update $result Update object
 * @param array|null $oldValues Старые значения (не используется - Bitrix24 не передаёт)
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
            'UF_CRM_1751271798896', // Пассажиры
            botManager::INTERMEDIATE_POINTS_FIELD, // Промежуточные точки
            botManager::ADDRESS_FROM_FIELD_SERVICE, // SERVICE: Откуда
            botManager::ADDRESS_TO_FIELD_SERVICE, // SERVICE: Куда
            botManager::TRAVEL_DATE_TIME_FIELD_SERVICE, // SERVICE: Время
            // Для пассажиров и промежуточных точек пока нет отдельных SERVICE полей
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

    // Проверяем изменения используя SERVICE поля (а не OLD values)
    $changes = [];

    // 1. Точка А (откуда)
    $serviceAddressFrom = $deal[botManager::ADDRESS_FROM_FIELD_SERVICE] ?? null;
    $currentAddressFrom = $deal[botManager::ADDRESS_FROM_FIELD];
    
    if ($serviceAddressFrom && $serviceAddressFrom != $currentAddressFrom && !empty($currentAddressFrom)) {
        $changes[] = [
            'field' => 'addressFrom',
            'emoji' => '🅰️',
            'label' => 'Откуда',
            'old' => $serviceAddressFrom,
            'new' => $currentAddressFrom,
            'serviceField' => botManager::ADDRESS_FROM_FIELD_SERVICE
        ];
    }

    // 2. Точка Б (куда)
    $serviceAddressTo = $deal[botManager::ADDRESS_TO_FIELD_SERVICE] ?? null;
    $currentAddressTo = $deal[botManager::ADDRESS_TO_FIELD];
    
    if ($serviceAddressTo && $serviceAddressTo != $currentAddressTo && !empty($currentAddressTo)) {
        $changes[] = [
            'field' => 'addressTo',
            'emoji' => '🅱️',
            'label' => 'Куда',
            'old' => $serviceAddressTo,
            'new' => $currentAddressTo,
            'serviceField' => botManager::ADDRESS_TO_FIELD_SERVICE
        ];
    }

    // 3. Время поездки
    $serviceDateTime = $deal[botManager::TRAVEL_DATE_TIME_FIELD_SERVICE] ?? null;
    $currentDateTime = $deal[botManager::TRAVEL_DATE_TIME_FIELD];
    
    if ($serviceDateTime && $serviceDateTime != $currentDateTime && !empty($currentDateTime)) {
        // Форматируем дату в человеческий вид
        $oldFormatted = $serviceDateTime;
        $newFormatted = $currentDateTime;

        if ($serviceDateTime) {
            try {
                $oldDate = new \DateTime($serviceDateTime);
                $oldFormatted = $oldDate->format('d.m.Y H:i');
            } catch (Exception $e) {}
        }

        if ($currentDateTime) {
            try {
                $newDate = new \DateTime($currentDateTime);
                $newFormatted = $newDate->format('d.m.Y H:i');
            } catch (Exception $e) {}
        }

        $changes[] = [
            'field' => 'dateTime',
            'emoji' => '⏰',
            'label' => 'Дата и время',
            'old' => $oldFormatted,
            'new' => $newFormatted,
            'serviceField' => botManager::TRAVEL_DATE_TIME_FIELD_SERVICE
        ];
    }

    // 4. Промежуточные точки - используем дополнительные условия как SERVICE поле временно
    // TODO: Добавить отдельное SERVICE поле для промежуточных точек
    
    // 5. Пассажиры - тоже нужно отдельное SERVICE поле
    // TODO: Добавить отдельное SERVICE поле для пассажиров

    // Если изменений нет - ничего не отправляем
    if (empty($changes)) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
            date('Y-m-d H:i:s') . " - No changes detected for deal $dealId\n", FILE_APPEND);
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
            date('Y-m-d H:i:s') . " - Change notification sent successfully\n", FILE_APPEND);

        // ОБНОВЛЯЕМ SERVICE ПОЛЯ с текущими значениями
        $updateFields = [];
        foreach ($changes as $change) {
            $updateFields[$change['serviceField']] = $change['new'];
        }

        if (!empty($updateFields)) {
            \CRest::call('crm.deal.update', [
                'id' => $dealId,
                'fields' => $updateFields
            ]);
            
            file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
                date('Y-m-d H:i:s') . " - SERVICE fields updated\n", FILE_APPEND);
        }

    } catch (Exception $e) {
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log',
            date('Y-m-d H:i:s') . " - Error sending notification: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}


