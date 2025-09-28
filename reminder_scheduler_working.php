<?php
/**
 * Рабочий скрипт планировщика для отправки напоминаний водителям
 * Запускается по cron каждые 5 минут
 * Реально выполняет логику отправки напоминаний
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;

// Конфигурация
$logFile = __DIR__ . '/logs/reminder_scheduler.log';
$telegramToken = '7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4';

// Создаем директорию для логов, если её нет
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

try {
    // Проверяем, что CRest доступен
    if (!file_exists('/home/telegramBot/crest/crest.php')) {
        throw new Exception('CRest библиотека не найдена. Проверьте путь: /home/telegramBot/crest/crest.php');
    }
    
    // Проверяем, что botManager работает
    if (!class_exists('Store\\botManager')) {
        throw new Exception('Класс botManager не найден');
    }
    
    // Логируем начало работы
    $logMessage = date('Y-m-d H:i:s') . " - 🚀 Запуск системы напоминаний\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Проверяем доступность методов
    $methods = [
        'sendTravelReminder',
        'sendResponsibleNotification', 
        'checkAndSendReminders'
    ];
    
    foreach ($methods as $method) {
        if (!method_exists('Store\\botManager', $method)) {
            throw new Exception("Метод $method не найден в botManager");
        }
    }
    
    $logMessage = date('Y-m-d H:i:s') . " - ✅ Все методы проверены успешно\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Проверяем константы
    $constants = [
        'REMINDER_SENT_FIELD',
        'REMINDER_CONFIRMED_FIELD',
        'REMINDER_NOTIFICATION_SENT_FIELD',
        'DRIVER_ACCEPTED_STAGE_ID'
    ];
    
    foreach ($constants as $const) {
        if (!defined("Store\\botManager::$const")) {
            throw new Exception("Константа $const не определена в botManager");
        }
    }
    
    $logMessage = date('Y-m-d H:i:s') . " - ✅ Все константы проверены успешно\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Инициализируем CRest
    require_once('/home/telegramBot/crest/crest.php');
    
    // Проверяем доступность CRest
    $logMessage = date('Y-m-d H:i:s') . " - 🔧 CRest подключен\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Тестируем простой вызов CRest
    try {
        $testResponse = \CRest::call('crm.deal.fields');
        $logMessage = date('Y-m-d H:i:s') . " - ✅ CRest работает, получено полей: " . (isset($testResponse['result']) ? count($testResponse['result']) : 'ошибка') . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    } catch (Exception $e) {
        $logMessage = date('Y-m-d H:i:s') . " - ❌ CRest не работает: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        throw $e;
    }
    
    // Получаем все заявки в статусе "Водитель принял"
    $dealsResponse = \CRest::call('crm.deal.list', [
        'filter' => [
            'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID
        ],
        'select' => ['ID', botManager::TRAVEL_DATE_TIME_FIELD, botManager::REMINDER_SENT_FIELD, botManager::REMINDER_CONFIRMED_FIELD, botManager::DRIVER_ID_FIELD]
    ]);
    
    // Проверяем ответ CRest
    if (empty($dealsResponse) || !isset($dealsResponse['result'])) {
        $logMessage = date('Y-m-d H:i:s') . " - ❌ Ошибка CRest: " . json_encode($dealsResponse) . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        throw new Exception('Ошибка получения заявок из CRest: ' . json_encode($dealsResponse));
    }
    
    $deals = $dealsResponse['result'];
    
    $logMessage = date('Y-m-d H:i:s') . " - 📊 Найдено заявок в статусе 'Водитель принял': " . count($deals) . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    $remindersSent = 0;
    $errors = [];
    
    foreach ($deals as $deal) {
        try {
            $travelTime = strtotime($deal[botManager::TRAVEL_DATE_TIME_FIELD]);
            $currentTime = time();
            $timeUntilTravel = $travelTime - $currentTime;
            
            $logMessage = date('Y-m-d H:i:s') . " - 🔍 Заявка #{$deal['ID']}: до поездки " . gmdate("H:i:s", $timeUntilTravel) . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            // Проверяем, было ли уже отправлено напоминание
            $reminderSent = !empty($deal[botManager::REMINDER_SENT_FIELD]);
            $reminderConfirmed = !empty($deal[botManager::REMINDER_CONFIRMED_FIELD]);
            
            // Если до поездки остался 1 час (3600 секунд) или меньше, и напоминание не отправлялось
            if ($timeUntilTravel <= 3600 && $timeUntilTravel > 0 && !$reminderSent && !$reminderConfirmed) {
                $logMessage = date('Y-m-d H:i:s') . " - ⏰ Время для напоминания! Заявка #{$deal['ID']}\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                
                // Получаем информацию о водителе
                $driver = \CRest::call('crm.contact.get', [
                    'id' => $deal[botManager::DRIVER_ID_FIELD],
                    'select' => [botManager::DRIVER_TELEGRAM_ID_FIELD]
                ])['result'];
                
                if (empty($driver) || empty($driver[botManager::DRIVER_TELEGRAM_ID_FIELD])) {
                    $logMessage = date('Y-m-d H:i:s') . " - ❌ Водитель не найден для заявки #{$deal['ID']}\n";
                    file_put_contents($logFile, $logMessage, FILE_APPEND);
                    continue;
                }
                
                $driverTelegramId = (int) $driver[botManager::DRIVER_TELEGRAM_ID_FIELD];
                
                // Отправляем напоминание через Telegram Bot API
                $message = "⚠️ НАПОМИНАНИЕ!\n\nЧерез 1 час начинается поездка по заявке #{$deal['ID']}\n\nПожалуйста, подтвердите готовность к выполнению заказа.";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Подтверждаю', 'callback_data' => "confirm_{$deal['ID']}"]
                        ]
                    ]
                ];
                
                $telegramData = [
                    'chat_id' => $driverTelegramId,
                    'text' => $message,
                    'reply_markup' => json_encode($keyboard)
                ];
                
                $response = file_get_contents("https://api.telegram.org/bot{$telegramToken}/sendMessage?" . http_build_query($telegramData));
                $result = json_decode($response, true);
                
                if ($result['ok']) {
                    // Отмечаем, что напоминание отправлено
                    \CRest::call('crm.deal.update', [
                        'id' => $deal['ID'],
                        'fields' => [botManager::REMINDER_SENT_FIELD => date('Y-m-d H:i:s')]
                    ]);
                    
                    $remindersSent++;
                    $logMessage = date('Y-m-d H:i:s') . " - ✅ Напоминание отправлено водителю для заявки #{$deal['ID']}\n";
                    file_put_contents($logFile, $logMessage, FILE_APPEND);
                } else {
                    $errors[] = "Ошибка отправки напоминания для заявки #{$deal['ID']}: " . ($result['description'] ?? 'Неизвестная ошибка');
                    $logMessage = date('Y-m-d H:i:s') . " - ❌ Ошибка отправки напоминания для заявки #{$deal['ID']}: " . ($result['description'] ?? 'Неизвестная ошибка') . "\n";
                    file_put_contents($logFile, $logMessage, FILE_APPEND);
                }
            }
        } catch (Exception $e) {
            $errors[] = "Ошибка обработки заявки #{$deal['ID']}: " . $e->getMessage();
            $logMessage = date('Y-m-d H:i:s') . " - ❌ Ошибка обработки заявки #{$deal['ID']}: " . $e->getMessage() . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
    
    // Получаем заявки для проверки уведомлений ответственному
    $dealsForNotificationResponse = \CRest::call('crm.deal.list', [
        'filter' => [
            'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID
        ],
        'select' => ['ID', botManager::REMINDER_SENT_FIELD, botManager::REMINDER_CONFIRMED_FIELD, botManager::REMINDER_NOTIFICATION_SENT_FIELD, 'ASSIGNED_BY_ID', 'TITLE']
    ]);
    
    // Проверяем ответ CRest
    if (empty($dealsForNotificationResponse) || !isset($dealsForNotificationResponse['result'])) {
        $logMessage = date('Y-m-d H:i:s') . " - ❌ Ошибка CRest при получении заявок для уведомлений: " . json_encode($dealsForNotificationResponse) . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        $dealsForNotification = [];
    } else {
        $dealsForNotification = $dealsForNotificationResponse['result'];
    }
    
    $notificationsSent = 0;
    
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
                // Отправляем уведомление ответственному лицу
                $notify = \CRest::call('im.notify.system.add', [
                    'USER_ID' => $deal['ASSIGNED_BY_ID'],
                    'MESSAGE' => "⚠️ ВНИМАНИЕ! Водитель 15 минут не подтверждает заказ #{$deal['ID']}. " .
                                "<a href='https://b24-cprnr5.bitrix24.ru/crm/deal/details/{$deal['ID']}/'>{$deal['TITLE']}</a>"
                ]);
                
                if ($notify) {
                    // Отмечаем, что уведомление отправлено
                    \CRest::call('crm.deal.update', [
                        'id' => $deal['ID'],
                        'fields' => [botManager::REMINDER_NOTIFICATION_SENT_FIELD => date('Y-m-d H:i:s')]
                    ]);
                    
                    $notificationsSent++;
                    $logMessage = date('Y-m-d H:i:s') . " - ⚠️ Уведомление ответственному отправлено для заявки #{$deal['ID']}\n";
                    file_put_contents($logFile, $logMessage, FILE_APPEND);
                }
            }
        } catch (Exception $e) {
            $errors[] = "Ошибка отправки уведомления ответственному для заявки #{$deal['ID']}: " . $e->getMessage();
            $logMessage = date('Y-m-d H:i:s') . " - ❌ Ошибка отправки уведомления для заявки #{$deal['ID']}: " . $e->getMessage() . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
    
    // Итоговая статистика
    $logMessage = date('Y-m-d H:i:s') . " - 📊 ИТОГИ:\n";
    $logMessage .= "  - Напоминаний отправлено: {$remindersSent}\n";
    $logMessage .= "  - Уведомлений ответственному: {$notificationsSent}\n";
    $logMessage .= "  - Ошибок: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        $logMessage .= "  - Детали ошибок:\n";
        foreach ($errors as $error) {
            $logMessage .= "    * {$error}\n";
        }
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Выводим результат в консоль для cron
    echo "✅ Система напоминаний выполнена!\n";
    echo "📋 Напоминаний отправлено: {$remindersSent}\n";
    echo "⚠️ Уведомлений ответственному: {$notificationsSent}\n";
    echo "❌ Ошибок: " . count($errors) . "\n";
    echo "📋 Логи записаны в: $logFile\n";
    
} catch (Exception $e) {
    $errorMessage = date('Y-m-d H:i:s') . " - ❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Скрипт выполнен успешно\n";
exit(0);
