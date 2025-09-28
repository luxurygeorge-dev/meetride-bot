<?php
/**
 * Простая отправка уведомлений через cURL
 */

require_once('/home/telegramBot/crest/crest.php');

// Конфигурация
$telegramToken = '7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4';
$logFile = __DIR__ . '/logs/missed_notifications.log';

// Функция логирования
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

// Функция отправки сообщения в Telegram
function sendTelegramMessage($chatId, $text, $keyboard = null) {
    global $telegramToken;
    
    $url = "https://api.telegram.org/bot$telegramToken/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

// Функция форматирования даты
function formatDate($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    return $date->format('d.m.Y H:i');
}

try {
    writeLog("=== НАЧАЛО ОТПРАВКИ ПРОПУЩЕННЫХ УВЕДОМЛЕНИЙ ===");
    
    // Получаем все сделки на стадиях PREPARATION и EXECUTING
    $deals = \CRest::call('crm.deal.list', [
        'filter' => [
            'STAGE_ID' => ['PREPARATION', 'EXECUTING']
        ],
        'select' => [
            'ID',
            'TITLE', 
            'STAGE_ID',
            'UF_CRM_1751272181', // DRIVER_ID_FIELD
            'UF_CRM_1751269222959', // TRAVEL_DATE_TIME_FIELD
            'UF_CRM_1751269147414', // ADDRESS_FROM_FIELD
            'UF_CRM_1751269175432', // ADDRESS_TO_FIELD
            'UF_CRM_1751271862251', // DRIVER_SUM_FIELD
            'UF_CRM_1751269256380'  // ADDITIONAL_CONDITIONS_FIELD
        ]
    ])['result'];
    
    writeLog("Найдено сделок: " . count($deals));
    
    $sentCount = 0;
    $errorCount = 0;
    
    foreach ($deals as $deal) {
        try {
            writeLog("Обрабатываем сделку #{$deal['ID']} (статус: {$deal['STAGE_ID']})");
            
            // Проверяем, что у сделки есть назначенный водитель
            if (empty($deal['UF_CRM_1751272181'])) {
                writeLog("  ❌ Нет назначенного водителя - пропускаем");
                continue;
            }
            
            // Получаем информацию о водителе
            $driver = \CRest::call('crm.contact.get', [
                'id' => $deal['UF_CRM_1751272181'],
                'select' => ['NAME', 'LAST_NAME', 'UF_CRM_1751185017761'] // DRIVER_TELEGRAM_ID_FIELD
            ])['result'];
            
            if (empty($driver) || empty($driver['UF_CRM_1751185017761'])) {
                writeLog("  ❌ Нет Telegram ID водителя - пропускаем");
                continue;
            }
            
            $driverTelegramId = $driver['UF_CRM_1751185017761'];
            $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
            
            writeLog("  👤 Водитель: $driverName (Telegram ID: $driverTelegramId)");
            
            // Формируем текст заявки
            $dateText = formatDate($deal['UF_CRM_1751269222959']);
            $fromAddress = $deal['UF_CRM_1751269147414'];
            $toAddress = $deal['UF_CRM_1751269175432'];
            $sumText = $deal['UF_CRM_1751271862251'];
            $conditions = $deal['UF_CRM_1751269256380'];
            
            $text = "#️⃣ Заявка {$deal['ID']} - <b>Назначена водителю: $driverName</b>\n\n";
            $text .= "📆 $dateText\n\n";
            $text .= "🅰️ $fromAddress\n\n";
            $text .= "🅱️ $toAddress\n\n";
            if ($conditions) {
                $text .= "ℹ️ $conditions\n\n";
            }
            $text .= "💰 $sumText";
            
            // Создаем кнопки в зависимости от стадии
            $keyboard = null;
            
            if ($deal['STAGE_ID'] === 'PREPARATION') {
                // Стадия "Водитель принял" - кнопки начала выполнения
                $keyboard = [
                    'inline_keyboard' => [[
                        ['text' => '✅ Начать выполнение', 'callback_data' => "start_{$deal['ID']}"],
                        ['text' => '❌ Отказаться', 'callback_data' => "reject_{$deal['ID']}"]
                    ]]
                ];
            } elseif ($deal['STAGE_ID'] === 'EXECUTING') {
                // Стадия "Выполняется" - кнопки завершения
                $keyboard = [
                    'inline_keyboard' => [[
                        ['text' => '🏁 Заявка выполнена', 'callback_data' => "finish_{$deal['ID']}"],
                        ['text' => '❌ Отменить выполнение', 'callback_data' => "cancel_{$deal['ID']}"]
                    ]]
                ];
            }
            
            // Отправляем уведомление в личку водителю
            if (sendTelegramMessage($driverTelegramId, $text, $keyboard)) {
                writeLog("  ✅ Уведомление отправлено в личку");
                $sentCount++;
            } else {
                writeLog("  ❌ Ошибка отправки уведомления");
                $errorCount++;
            }
            
            // Небольшая пауза между отправками
            sleep(1);
            
        } catch (Exception $e) {
            writeLog("  ❌ Ошибка обработки сделки #{$deal['ID']}: " . $e->getMessage());
            $errorCount++;
        }
    }
    
    writeLog("=== ЗАВЕРШЕНИЕ ОТПРАВКИ ===");
    writeLog("Успешно отправлено: $sentCount");
    writeLog("Ошибок: $errorCount");
    writeLog("Всего обработано: " . count($deals));
    
} catch (Exception $e) {
    writeLog("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    exit(1);
}
