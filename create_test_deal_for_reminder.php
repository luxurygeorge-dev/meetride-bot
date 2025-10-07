<?php
/**
 * Создание тестовой заявки для проверки системы напоминаний
 * Время поездки устанавливается через 55 минут от текущего момента
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;

// Проверяем CRest
if (!class_exists("CRest")) { 
    require_once("/home/telegramBot/crest/crest.php"); 
}

// Время поездки через 55 минут (попадёт в окно напоминаний)
$travelDateTime = date('Y-m-d H:i:s', strtotime('+55 minutes'));
$travelDateTimeFormatted = date('d.m.Y H:i', strtotime('+55 minutes'));

echo "🧪 Создание тестовой заявки для напоминаний\n\n";
echo "⏰ Текущее время: " . date('d.m.Y H:i') . "\n";
echo "🚗 Время поездки: " . $travelDateTimeFormatted . " (через 55 минут)\n\n";

// Создаём тестовую заявку
$dealData = [
    'TITLE' => 'ТЕСТ Напоминание ' . rand(1000, 9999),
    'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID, // PREPAYMENT_INVOICE - водитель уже принял
    'CATEGORY_ID' => 0,
    botManager::ADDRESS_FROM_FIELD => 'Тестовый адрес А',
    botManager::ADDRESS_TO_FIELD => 'Тестовый адрес Б',
    botManager::TRAVEL_DATE_TIME_FIELD => $travelDateTime,
    botManager::DRIVER_ID_FIELD => 3, // ID водителя в CRM
];

echo "📝 Создаю заявку в Bitrix24...\n";

$result = \CRest::call('crm.deal.add', [
    'fields' => $dealData
]);

if (!empty($result['result'])) {
    $dealId = $result['result'];
    echo "✅ Заявка создана! ID: {$dealId}\n\n";
    
    echo "📋 Детали заявки:\n";
    echo "   - Название: " . $dealData['TITLE'] . "\n";
    echo "   - Стадия: PREPAYMENT_INVOICE (водитель принял)\n";
    echo "   - Время поездки: {$travelDateTimeFormatted}\n";
    echo "   - Водитель ID: 3\n\n";
    
    echo "🔍 Проверка:\n";
    
    // Получаем данные водителя
    $driver = \CRest::call('crm.contact.get', [
        'id' => 3,
        'select' => ['NAME', 'LAST_NAME', botManager::DRIVER_TELEGRAM_ID_FIELD]
    ])['result'];
    
    if ($driver && !empty($driver[botManager::DRIVER_TELEGRAM_ID_FIELD])) {
        echo "   ✅ Водитель найден: " . $driver['NAME'] . " " . $driver['LAST_NAME'] . "\n";
        echo "   ✅ Telegram ID: " . $driver[botManager::DRIVER_TELEGRAM_ID_FIELD] . "\n\n";
    } else {
        echo "   ⚠️ У водителя нет Telegram ID!\n\n";
    }
    
    echo "⏰ Напоминание должно прийти примерно в: " . date('H:i', strtotime('+5 minutes')) . " (когда сработает cron)\n\n";
    
    echo "📊 Для проверки выполните:\n";
    echo "   tail -f /root/meetride/logs/reminder_scheduler.log\n\n";
    
    echo "🧪 Ожидаемые события:\n";
    echo "   1. Через ~5 минут: Водителю придёт напоминание с кнопкой [✅ Подтверждаю]\n";
    echo "   2. Если НЕ нажать кнопку 15 минут: Ответственному в Bitrix24 придёт уведомление\n\n";
    
    echo "🔗 Заявка в Bitrix24:\n";
    echo "   https://meetride.bitrix24.ru/crm/deal/details/{$dealId}/\n\n";
    
} else {
    echo "❌ Ошибка создания заявки:\n";
    print_r($result);
}

