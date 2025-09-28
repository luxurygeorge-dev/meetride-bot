<?php
/**
 * Скрипт планировщика для отправки напоминаний водителям
 * Запускается по cron каждые 5 минут
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;

// Конфигурация
$telegramToken = '7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4'; // Токен бота для системы напоминаний
$logFile = __DIR__ . '/logs/reminder_scheduler.log';

// Создаем директорию для логов, если её нет
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

try {
    // Инициализируем Telegram API
    $telegram = new Telegram($telegramToken);
    
    // Запускаем проверку и отправку напоминаний
    $result = botManager::checkAndSendReminders($telegram);
    
    // Логируем результат
    $logMessage = date('Y-m-d H:i:s') . " - ";
    $logMessage .= "Напоминаний отправлено: {$result['reminders_sent']}, ";
    $logMessage .= "Уведомлений ответственному: {$result['notifications_sent']}";
    
    if (!empty($result['errors'])) {
        $logMessage .= "\nОшибки: " . implode('; ', $result['errors']);
    }
    
    file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
    
    // Выводим результат в консоль для cron
    echo $logMessage . "\n";
    
} catch (Exception $e) {
    $errorMessage = date('Y-m-d H:i:s') . " - Ошибка: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    echo $errorMessage;
    exit(1);
}

echo "Скрипт выполнен успешно\n";
exit(0);
