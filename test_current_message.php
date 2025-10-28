<?php
require_once('/root/meetride/src/crest/crest.php');
require_once('/root/meetride/botManager.php');

// Получаем тестовую сделку
$deal = \CRest::call('crm.deal.get', ['id' => 815])['result'];

if (empty($deal['ID'])) {
    echo 'Сделка не найдена';
    exit;
}

// Симулируем формирование сообщения как в функции sendPrivateMessageToDriver
$dealId = 815;
$driverTelegramId = 302484095;

// Получаем имя водителя (имитация)
$driver = \CRest::call('crm.contact.get', [
    'id' => $deal[\Store\botManager::DRIVER_ID_FIELD],
    'select' => ['NAME', 'LAST_NAME']
])['result'];

$driverName = 'Водитель';
if ($driver) {
    $driverName = trim($driver['NAME'] . ' ' . $driver['LAST_NAME']);
}

// Формируем сообщение как в функции sendPrivateMessageToDriver
$orderNumber = $deal['TITLE'] ?? $dealId;
if (strpos($orderNumber, 'Заявка: ') === 0) {
    $orderNumber = substr($orderNumber, 8);
} elseif (strpos($orderNumber, 'Сделка #') === 0) {
    $orderNumber = substr($orderNumber, 8);
}

$message = "🚗 <b>Заявка #$orderNumber</b>\n\n";
$message .= "🅰️ <b>Откуда:</b> " . ($deal[\Store\botManager::ADDRESS_FROM_FIELD] ?? 'Не указано') . "\n\n";
$message .= "🅱️ <b>Куда:</b> " . ($deal[\Store\botManager::ADDRESS_TO_FIELD] ?? 'Не указано') . "\n\n";
$message .= "⏰ <b>Время:</b> " . \Store\botManager::formatDateTime($deal[\Store\botManager::TRAVEL_DATE_TIME_FIELD] ?? null) . "\n\n";

if (!empty($deal[\Store\botManager::INTERMEDIATE_POINTS_FIELD])) {
    $intermediatePoints = $deal[\Store\botManager::INTERMEDIATE_POINTS_FIELD];
    if (is_array($intermediatePoints)) {
        $intermediatePoints = implode(", ", $intermediatePoints);
    }
    $message .= "🗺️ <b>Промежуточные точки:</b> $intermediatePoints\n\n";
}

if (!empty($deal[\Store\botManager::PASSENGERS_FIELD])) {
    $passengers = $deal[\Store\botManager::PASSENGERS_FIELD];
    if (is_array($passengers)) {
        $passengers = implode(", ", $passengers);
    }
    $message .= "👥 <b>Пассажиры:</b> $passengers\n\n";
}

if (!empty($deal[\Store\botManager::FLIGHT_NUMBER_FIELD])) {
    $message .= "✈️ <b>Номер рейса:</b> " . $deal[\Store\botManager::FLIGHT_NUMBER_FIELD] . "\n\n";
}

if (!empty($deal[\Store\botManager::ADDITIONAL_CONDITIONS_FIELD])) {
    $message .= "📝 <b>Дополнительные условия:</b> " . $deal[\Store\botManager::ADDITIONAL_CONDITIONS_FIELD] . "\n\n";
}

$message .= "💰 <b>Сумма:</b> " . ($deal[\Store\botManager::DRIVER_SUM_FIELD] ?? 'Не указана') . " руб.\n\n";
$message .= "Пожалуйста, подтвердите готовность к выполнению заявки";

// Выводим результат
echo "=== СФОРМИРОВАННОЕ СООБЩЕНИЕ ===\n\n";
echo $message;
echo "\n\n=== ПРОВЕРКА ФОРМАТА ===\n";
echo "Содержит приветствие: " . (strpos($message, 'Здравствуйте') !== false ? 'ДА' : 'НЕТ') . "\n";
echo "Содержит точку в конце: " . (strpos($message, 'заявки:') !== false ? 'ДА' : 'НЕТ') . "\n";
echo "Количество двойных переносов строк: " . substr_count($message, "\n\n") . "\n";
echo "Количество одиночных переносов строк: " . substr_count($message, "\n") . "\n";



