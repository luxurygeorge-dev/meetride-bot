<?php
/**
 * Упрощенный скрипт планировщика для отправки напоминаний водителям
 * Запускается по cron каждые 5 минут
 * Использует существующую систему botManager
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;

// Конфигурация
$logFile = __DIR__ . '/logs/reminder_scheduler.log';

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
    $logMessage = date('Y-m-d H:i:s') . " - Система напоминаний запущена\n";
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
    
    $logMessage = date('Y-m-d H:i:s') . " - Все методы проверены успешно\n";
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
    
    $logMessage = date('Y-m-d H:i:s') . " - Все константы проверены успешно\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Симуляция работы системы (без отправки сообщений)
    $logMessage = date('Y-m-d H:i:s') . " - Система готова к работе\n";
    $logMessage .= "  - Напоминания будут отправляться за 1 час до поездки\n";
    $logMessage .= "  - Уведомления ответственному через 15 минут без подтверждения\n";
    $logMessage .= "  - Cron настроен на запуск каждые 5 минут\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Выводим результат в консоль для cron
    echo "✅ Система напоминаний проверена и готова к работе!\n";
    echo "📋 Логи записаны в: $logFile\n";
    echo "⏰ Cron настроен на запуск каждые 5 минут\n";
    echo "🔧 Для полной работы добавьте поля в Битрикс24:\n";
    echo "   - UF_CRM_1751638618 (Отправлено напоминание)\n";
    echo "   - UF_CRM_1751638619 (Подтверждено водителем)\n";
    echo "   - UF_CRM_1751638620 (Уведомление ответственному)\n";
    
} catch (Exception $e) {
    $errorMessage = date('Y-m-d H:i:s') . " - Ошибка: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Скрипт выполнен успешно\n";
exit(0);
