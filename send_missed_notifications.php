<?php
/**
 * Единоразовая отправка уведомлений по сделкам на стадиях PREPARATION и EXECUTING
 * Отправляет только последнее уведомление (кнопки управления заявкой)
 */

require_once(__DIR__ . '/botManager.php');
require_once('/home/telegramBot/crest/crest.php');

use Store\botManager;

// Конфигурация
$telegramToken = '7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4';
$logFile = __DIR__ . '/logs/missed_notifications.log';

// Создаем директорию для логов
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Функция логирования
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

try {
    writeLog("=== НАЧАЛО ОТПРАВКИ ПРОПУЩЕННЫХ УВЕДОМЛЕНИЙ ===");
    
    // Инициализируем Telegram API
    $telegram = new \TelegramBot\Api\BotApi($telegramToken);
    
    // Получаем все сделки на стадиях PREPARATION и EXECUTING
    $deals = \CRest::call('crm.deal.list', [
        'filter' => [
            'STAGE_ID' => ['PREPARATION', 'EXECUTING']
        ],
        'select' => [
            'ID',
            'TITLE', 
            'STAGE_ID',
            botManager::DRIVER_ID_FIELD,
            botManager::TRAVEL_DATE_TIME_FIELD,
            botManager::ADDRESS_FROM_FIELD,
            botManager::ADDRESS_TO_FIELD,
            botManager::DRIVER_SUM_FIELD,
            botManager::ADDITIONAL_CONDITIONS_FIELD
        ]
    ])['result'];
    
    writeLog("Найдено сделок: " . count($deals));
    
    $sentCount = 0;
    $errorCount = 0;
    
    foreach ($deals as $deal) {
        try {
            writeLog("Обрабатываем сделку #{$deal['ID']} (статус: {$deal['STAGE_ID']})");
            
            // Проверяем, что у сделки есть назначенный водитель
            if (empty($deal[botManager::DRIVER_ID_FIELD])) {
                writeLog("  ❌ Нет назначенного водителя - пропускаем");
                continue;
            }
            
            // Получаем информацию о водителе
            $driver = \CRest::call('crm.contact.get', [
                'id' => $deal[botManager::DRIVER_ID_FIELD],
                'select' => ['NAME', 'LAST_NAME', botManager::DRIVER_TELEGRAM_ID_FIELD]
            ])['result'];
            
            if (empty($driver) || empty($driver[botManager::DRIVER_TELEGRAM_ID_FIELD])) {
                writeLog("  ❌ Нет Telegram ID водителя - пропускаем");
                continue;
            }
            
            $driverTelegramId = $driver[botManager::DRIVER_TELEGRAM_ID_FIELD];
            $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
            
            writeLog("  👤 Водитель: $driverName (Telegram ID: $driverTelegramId)");
            
            // Создаем кнопки в зависимости от стадии
            $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([]);
            $buttons = [];
            
            if ($deal['STAGE_ID'] === 'PREPARATION') {
                // Стадия "Водитель принял" - кнопки начала выполнения
                $buttons[] = [
                    \TelegramBot\Api\Types\Inline\InlineKeyboardButton::set([
                        'text' => '✅ Начать выполнение', 
                        'callback_data' => "start_{$deal['ID']}"
                    ]),
                    \TelegramBot\Api\Types\Inline\InlineKeyboardButton::set([
                        'text' => '❌ Отказаться', 
                        'callback_data' => "reject_{$deal['ID']}"
                    ])
                ];
            } elseif ($deal['STAGE_ID'] === 'EXECUTING') {
                // Стадия "Выполняется" - кнопки завершения
                $buttons[] = [
                    \TelegramBot\Api\Types\Inline\InlineKeyboardButton::set([
                        'text' => '🏁 Заявка выполнена', 
                        'callback_data' => "finish_{$deal['ID']}"
                    ]),
                    \TelegramBot\Api\Types\Inline\InlineKeyboardButton::set([
                        'text' => '❌ Отменить выполнение', 
                        'callback_data' => "cancel_{$deal['ID']}"
                    ])
                ];
            }
            
            $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($buttons);
            
            // Формируем текст заявки
            $text = botManager::orderTextWithDriver($deal, $driverName);
            
            // Отправляем уведомление в личку водителю
            $message = $telegram->sendMessage([
                'chat_id' => $driverTelegramId,
                'text' => $text,
                'reply_markup' => $keyboard,
                'parse_mode' => 'HTML'
            ]);
            
            if ($message) {
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
