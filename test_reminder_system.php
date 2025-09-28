<?php
/**
 * Тестовый скрипт для проверки системы напоминаний
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;
use Telegram\Bot\Api;

echo "=== Тест системы напоминаний ===\n\n";

// Проверяем наличие необходимых файлов
echo "1. Проверка файлов:\n";
$requiredFiles = [
    'botManager.php',
    'vendor/autoload.php',
    'logs/'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file) || is_dir($file)) {
        echo "   ✅ $file - найден\n";
    } else {
        echo "   ❌ $file - НЕ НАЙДЕН\n";
    }
}

echo "\n2. Проверка констант:\n";
$constants = [
    'REMINDER_SENT_FIELD',
    'REMINDER_CONFIRMED_FIELD', 
    'REMINDER_NOTIFICATION_SENT_FIELD',
    'DRIVER_ACCEPTED_STAGE_ID'
];

foreach ($constants as $const) {
    if (defined("Store\\botManager::$const")) {
        echo "   ✅ $const = " . constant("Store\\botManager::$const") . "\n";
    } else {
        echo "   ❌ $const - НЕ ОПРЕДЕЛЕНА\n";
    }
}

echo "\n3. Проверка методов:\n";
$methods = [
    'sendTravelReminder',
    'confirmReminderHandle',
    'sendResponsibleNotification',
    'checkAndSendReminders'
];

foreach ($methods as $method) {
    if (method_exists('Store\\botManager', $method)) {
        echo "   ✅ $method() - найден\n";
    } else {
        echo "   ❌ $method() - НЕ НАЙДЕН\n";
    }
}

echo "\n4. Проверка директории логов:\n";
if (is_dir('logs') && is_writable('logs')) {
    echo "   ✅ Директория logs доступна для записи\n";
} else {
    echo "   ❌ Проблемы с директорией logs\n";
}

echo "\n5. Проверка Composer:\n";
if (file_exists('composer.json')) {
    echo "   ✅ composer.json найден\n";
    $composer = json_decode(file_get_contents('composer.json'), true);
    if (isset($composer['require'])) {
        echo "   ✅ Зависимости composer.json:\n";
        foreach ($composer['require'] as $package => $version) {
            echo "      - $package: $version\n";
        }
    }
} else {
    echo "   ❌ composer.json не найден\n";
}

echo "\n=== Рекомендации ===\n";

if (!file_exists('reminder_scheduler.php')) {
    echo "❌ Создайте файл reminder_scheduler.php\n";
}

if (!file_exists('setup_cron.sh')) {
    echo "❌ Создайте файл setup_cron.sh\n";
}

echo "\nДля настройки cron выполните:\n";
echo "chmod +x setup_cron.sh\n";
echo "./setup_cron.sh\n";

echo "\nДля тестирования системы выполните:\n";
echo "php reminder_scheduler.php\n";

echo "\n=== Тест завершен ===\n";







