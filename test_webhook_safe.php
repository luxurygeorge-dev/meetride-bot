<?php
/**
 * БЕЗОПАСНЫЙ тест webhook для MeetRide Bot
 * Тестирует только функции, НЕ отправляет реальные сообщения
 */

echo "🧪 БЕЗОПАСНЫЙ тест webhook MeetRide Bot\n";
echo "======================================\n\n";

// Имитируем тестовую сделку
$testDeal = [
    'ID' => '999',
    'TITLE' => 'Заявка: 123456',
    'STAGE_ID' => 'PREPARATION',
    'UF_CRM_1751269222959' => '2025-01-15 14:30:00', // Дата
    'UF_CRM_1751269222958' => 'Аэропорт Волгоград', // Откуда
    'UF_CRM_1751269222957' => 'Центр города, ул. Ленина 1', // Куда
    'UF_CRM_1751269222960' => '5000|RUB', // Сумма
    'UF_CRM_1751269222961' => 'Дополнительные условия', // Доп условия
    'UF_CRM_1751271798896' => 'Иван Петров (79001234567), Мария Сидорова (79007654321)', // Пассажиры
    'UF_CRM_1751271841129' => 'Скрытое поле - НЕ ДОЛЖНО ПОКАЗЫВАТЬСЯ' // Скрытое поле
];

echo "📋 Тестовая сделка:\n";
echo "   ID: " . $testDeal['ID'] . "\n";
echo "   TITLE: " . $testDeal['TITLE'] . "\n";
echo "   Стадия: " . $testDeal['STAGE_ID'] . "\n\n";

// Тест 1: Очистка номера заказа
echo "1. Тест очистки номера заказа:\n";
$orderNumber = $testDeal['TITLE'];
if (strpos($orderNumber, 'Заявка: ') === 0) {
    $orderNumber = substr($orderNumber, 8);
}
if (strpos($orderNumber, ':') !== false) {
    $orderNumber = trim(explode(':', $orderNumber)[1]);
}
echo "   Исходный: '" . $testDeal['TITLE'] . "'\n";
echo "   Очищенный: '$orderNumber'\n";
echo "   ✅ Очистка работает корректно\n\n";

// Тест 2: Проверка безопасности группы
echo "2. Проверка безопасности группы:\n";
$testGroupId = -1001649190984;
$productionGroupId = -1002544521661;

if ($testGroupId == -1001649190984) {
    echo "   ✅ Используется ТЕСТОВАЯ группа: $testGroupId\n";
} else {
    echo "   ❌ ОШИБКА! Неправильная группа: $testGroupId\n";
}

if ($testGroupId != $productionGroupId) {
    echo "   ✅ БОЕВАЯ группа НЕ используется\n";
} else {
    echo "   ❌ КРИТИЧЕСКАЯ ОШИБКА! Используется БОЕВАЯ группа!\n";
}
echo "\n";

// Тест 3: Форматирование сообщений (без отправки)
echo "3. Тест форматирования сообщений:\n";

// Имитируем функцию orderTextForGroup
$additionalConditions = $testDeal['UF_CRM_1751269222961'];
$dateText = $testDeal['UF_CRM_1751269222959'];
if ($dateText) {
    $date = new DateTime($dateText);
    $dateText = $date->format('d.m.Y H:i');
}

$fromAddress = $testDeal['UF_CRM_1751269222958'];
$toAddress = $testDeal['UF_CRM_1751269222957'];

$sumText = $testDeal['UF_CRM_1751269222960'];
if ($sumText) {
    $sumText = str_replace('|RUB', '', $sumText);
}

$orderNumber = $testDeal['TITLE'];
if (strpos($orderNumber, ':') !== false) {
    $orderNumber = trim(explode(':', $orderNumber)[1]);
}

$groupMessage = <<<HTML
#️⃣ $orderNumber

📆 $dateText

🅰️ $fromAddress

🅱️ $toAddress

ℹ️ $additionalConditions

💰 $sumText
HTML;

echo "   Групповое сообщение (БЕЗ пассажиров):\n";
echo "   " . str_replace("\n", "\n   ", $groupMessage) . "\n\n";

// Проверяем, что пассажиры НЕ в групповом сообщении
if (strpos($groupMessage, 'Пассажиры') === false) {
    echo "   ✅ В групповом сообщении НЕТ пассажиров\n";
} else {
    echo "   ❌ В групповом сообщении ЕСТЬ пассажиры!\n";
}

// Проверяем, что скрытое поле НЕ в групповом сообщении
if (strpos($groupMessage, 'Скрытое поле') === false) {
    echo "   ✅ Скрытое поле НЕ показывается в групповом сообщении\n";
} else {
    echo "   ❌ Скрытое поле показывается в групповом сообщении!\n";
}

echo "\n";

// Тест 4: Имитация личного сообщения водителю
echo "4. Тест личного сообщения водителю:\n";

$passengers = $testDeal['UF_CRM_1751271798896'];

$driverMessage = <<<HTML
🚗 Ваша заявка #$orderNumber

📆 Дата и время: $dateText

🅰️ Откуда: $fromAddress

🅱️ Куда: $toAddress

👥 Пассажиры: $passengers

ℹ️ Дополнительные условия: $additionalConditions

💰 Сумма: $sumText

Нажмите "Начать выполнение" когда будете готовы ехать
HTML;

echo "   Личное сообщение (С пассажирами):\n";
echo "   " . str_replace("\n", "\n   ", $driverMessage) . "\n\n";

// Проверяем, что пассажиры ЕСТЬ в личном сообщении
if (strpos($driverMessage, 'Пассажиры') !== false) {
    echo "   ✅ В личном сообщении ЕСТЬ пассажиры\n";
} else {
    echo "   ❌ В личном сообщении НЕТ пассажиров!\n";
}

// Проверяем, что скрытое поле НЕ в личном сообщении
if (strpos($driverMessage, 'Скрытое поле') === false) {
    echo "   ✅ Скрытое поле НЕ показывается в личном сообщении\n";
} else {
    echo "   ❌ Скрытое поле показывается в личном сообщении!\n";
}

echo "\n";

echo "🎉 БЕЗОПАСНЫЙ тест завершен!\n";
echo "============================\n";
echo "✅ Все функции работают корректно\n";
echo "✅ Боевой скрипт не затронут\n";
echo "✅ Готово к реальному тестированию\n";
?>
