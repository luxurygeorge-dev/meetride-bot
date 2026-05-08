<?php
/**
 * Webhook для обработки callback кнопок от Telegram бота
 */

namespace Store;

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

require_once('vendor/autoload.php');
require_once('botManager.php');
require_once(__DIR__ . '/CityConfigLoader.php');

// Phase 2D: token loaded from config/cities/volgograd.php (no more hardcode)
$bot_token = \Store\CityConfigLoader::getByCategoryId(0)['telegram']['notification_bot_token'];

$start_ts = microtime(true);
$lat_action = 'unknown';
$lat_deal = 0;

// Гарантированный лог latency: пишется даже если внутри был exit / fatal.
register_shutdown_function(function () use (&$start_ts, &$lat_action, &$lat_deal) {
    $dur_ms = (int) round((microtime(true) - $start_ts) * 1000);
    file_put_contents(
        '/var/www/html/meetRiedeBot/logs/webhook_debug.log',
        sprintf("%s - LAT callback=%s deal=%d total=%dms\n", date('Y-m-d H:i:s'), $lat_action, $lat_deal, $dur_ms),
        FILE_APPEND
    );
});

// Получаем входящие данные
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Логирование для отладки
$log_message = date('Y-m-d H:i:s') . " - Webhook получил: " . $input . "\n";
file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);

if (!$update) {
    http_response_code(400);
    exit('Invalid JSON');
}

try {
    $telegram = new Api($bot_token);
    $result = new Update($update);

    // Проверяем, есть ли callback query
    if ($result->callbackQuery) {
        $cbData = $result->callbackQuery->data ?? '';
        $log_message = date('Y-m-d H:i:s') . " - Обработка callback: " . $cbData . "\n";
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);

        if ($cbData) {
            $parts = explode('_', $cbData);
            $lat_action = $parts[0] ?? 'unknown';
            $lat_deal = (int) ($parts[1] ?? 0);
        }

        // Добавляем CRest для обработки
        if (!class_exists("CRest")) { require_once('/home/telegramBot/crest/crest.php'); }

        // Используем существующий обработчик из botManager
        \Store\botManager::buttonHanlde($telegram, $result);

        $log_message = date('Y-m-d H:i:s') . " - Callback обработан успешно\n";
        file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);
    }

    http_response_code(200);
    echo 'OK';

} catch (\Throwable $e) {
    $log_message = date('Y-m-d H:i:s') . " - Ошибка webhook: " . $e->getMessage() . "\n";
    $log_message .= "   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    $log_message .= "   Trace: " . $e->getTraceAsString() . "\n";
    file_put_contents('/var/www/html/meetRiedeBot/logs/webhook_debug.log', $log_message, FILE_APPEND);

    // Возвращаем 200, чтобы Telegram не ретраил один и тот же сбойный callback
    // в бесконечном цикле. Сама ошибка зафиксирована в webhook_debug.log.
    http_response_code(200);
    echo 'OK';
}
