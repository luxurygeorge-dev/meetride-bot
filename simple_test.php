<?php
/**
 * Простой тест функций MeetRide Bot
 */

// Проверяем, что файл существует
$botManagerFile = '/var/www/html/meetRiedeBot/botManager.php';
if (!file_exists($botManagerFile)) {
    echo "❌ Файл botManager.php не найден: $botManagerFile\n";
    exit(1);
}

echo "🧪 Простой тест MeetRide Bot\n";
echo "============================\n\n";

// Читаем файл и проверяем константы
$content = file_get_contents($botManagerFile);

// Проверка 1: Тестовая группа
if (strpos($content, '-4704206955') !== false) {
    echo "✅ ТЕСТОВАЯ группа (-4704206955) найдена\n";
} else {
    echo "❌ ТЕСТОВАЯ группа не найдена\n";
}

// Проверка 2: Боевая группа НЕ должна быть
if (strpos($content, '-1002544521661') !== false) {
    echo "❌ ОШИБКА! Боевая группа (-1002544521661) найдена в коде!\n";
} else {
    echo "✅ Боевая группа (-1002544521661) НЕ найдена\n";
}

// Проверка 3: Функция очистки номера заказа
if (strpos($content, 'Заявка: ') !== false) {
    echo "✅ Функция очистки номера заказа найдена\n";
} else {
    echo "❌ Функция очистки номера заказа НЕ найдена\n";
}

// Проверка 4: Функции форматирования
if (strpos($content, 'orderTextForGroup') !== false) {
    echo "✅ Функция orderTextForGroup найдена\n";
} else {
    echo "❌ Функция orderTextForGroup НЕ найдена\n";
}

if (strpos($content, 'orderTextForDriver') !== false) {
    echo "✅ Функция orderTextForDriver найдена\n";
} else {
    echo "❌ Функция orderTextForDriver НЕ найдена\n";
}

// Проверка 5: Поля пассажиров
if (strpos($content, 'UF_CRM_1751271798896') !== false) {
    echo "✅ Поле пассажиров (UF_CRM_1751271798896) найдено\n";
} else {
    echo "❌ Поле пассажиров НЕ найдено\n";
}

// Проверка 6: Скрытое поле
if (strpos($content, 'UF_CRM_1751271841129') !== false) {
    echo "✅ Скрытое поле (UF_CRM_1751271841129) найдено\n";
} else {
    echo "❌ Скрытое поле НЕ найдено\n";
}

echo "\n🎉 Проверка завершена!\n";
?>
