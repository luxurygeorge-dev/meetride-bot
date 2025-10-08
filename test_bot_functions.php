<?php
/**
 * Тестовый скрипт для проверки функций MeetRide Bot
 */

require_once '/var/www/html/meetRiedeBot/botManager.php';

echo "🧪 Тестирование функций MeetRide Bot\n";
echo "=====================================\n\n";

// Тест 1: Проверка констант
echo "1. Проверка констант:\n";
echo "   DRIVERS_GROUP_CHAT_ID: " . botManager::DRIVERS_GROUP_CHAT_ID . "\n";
echo "   DRIVER_ACCEPTED_STAGE_ID: " . botManager::DRIVER_ACCEPTED_STAGE_ID . "\n";
echo "   PASSENGERS_FIELD: " . botManager::PASSENGERS_FIELD . "\n";
echo "   HIDDEN_FIELD: " . botManager::HIDDEN_FIELD . "\n\n";

// Тест 2: Проверка функции очистки номера заказа
echo "2. Тест очистки номера заказа:\n";
$testTitles = [
    'Заявка: 123',
    'Заявка: 999999',
    '123',
    '999999',
    'Заявка: Тест 456'
];

foreach ($testTitles as $title) {
    $orderNumber = $title;
    if (strpos($orderNumber, 'Заявка: ') === 0) {
        $orderNumber = substr($orderNumber, 8);
    }
    echo "   '$title' -> '$orderNumber'\n";
}
echo "\n";

// Тест 3: Проверка форматирования сообщений
echo "3. Тест форматирования сообщений:\n";
$testDeal = [
    'ID' => '123',
    'TITLE' => 'Заявка: 999999',
    'STAGE_ID' => 'PREPARATION',
    'UF_CRM_1751269222959' => '2025-01-15 14:30:00', // Дата
    'UF_CRM_1751269222958' => 'Аэропорт', // Откуда
    'UF_CRM_1751269222957' => 'Центр города', // Куда
    'UF_CRM_1751269222960' => '5000|RUB', // Сумма
    'UF_CRM_1751269222961' => 'Дополнительные условия', // Доп условия
    'UF_CRM_1751271798896' => 'Иван Петров (79001234567), Мария Сидорова (79007654321)', // Пассажиры
    'UF_CRM_1751271841129' => 'Скрытое поле' // Скрытое поле
];

echo "   Тестовая сделка создана\n";
echo "   TITLE: " . $testDeal['TITLE'] . "\n";
echo "   Пассажиры: " . $testDeal['UF_CRM_1751271798896'] . "\n";
echo "   Скрытое поле: " . $testDeal['UF_CRM_1751271841129'] . "\n\n";

// Тест 4: Проверка функций форматирования
echo "4. Тест функций форматирования:\n";
try {
    $groupText = botManager::orderTextForGroup($testDeal, 'Тест Водитель');
    echo "   orderTextForGroup() - УСПЕШНО\n";
    echo "   Длина сообщения: " . strlen($groupText) . " символов\n";
    
    $driverText = botManager::orderTextForDriver($testDeal);
    echo "   orderTextForDriver() - УСПЕШНО\n";
    echo "   Длина сообщения: " . strlen($driverText) . " символов\n";
    
    // Проверяем, что в групповом сообщении нет пассажиров
    if (strpos($groupText, 'Пассажиры') === false) {
        echo "   ✅ В групповом сообщении НЕТ пассажиров\n";
    } else {
        echo "   ❌ В групповом сообщении ЕСТЬ пассажиры!\n";
    }
    
    // Проверяем, что в личном сообщении есть пассажиры
    if (strpos($driverText, 'Пассажиры') !== false) {
        echo "   ✅ В личном сообщении ЕСТЬ пассажиры\n";
    } else {
        echo "   ❌ В личном сообщении НЕТ пассажиров!\n";
    }
    
    // Проверяем, что скрытое поле нигде не показывается
    if (strpos($groupText, 'Скрытое поле') === false && strpos($driverText, 'Скрытое поле') === false) {
        echo "   ✅ Скрытое поле нигде не показывается\n";
    } else {
        echo "   ❌ Скрытое поле где-то показывается!\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 5: Проверка безопасности
echo "5. Проверка безопасности:\n";
if (botManager::DRIVERS_GROUP_CHAT_ID == -4704206955) {
    echo "   ✅ Используется ТЕСТОВАЯ группа (-4704206955)\n";
} else {
    echo "   ❌ ОШИБКА! Используется группа: " . botManager::DRIVERS_GROUP_CHAT_ID . "\n";
}

if (botManager::DRIVERS_GROUP_CHAT_ID != -1002544521661) {
    echo "   ✅ БОЕВАЯ группа (-1002544521661) НЕ используется\n";
} else {
    echo "   ❌ КРИТИЧЕСКАЯ ОШИБКА! Используется БОЕВАЯ группа!\n";
}
echo "\n";

echo "🎉 Тестирование завершено!\n";
echo "============================\n";
?>
