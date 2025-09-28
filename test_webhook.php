<?php
/**
 * Тестовый скрипт для проверки webhook'ов
 */

require_once(__DIR__ . '/src/crest/crest.php');

echo "=== ТЕСТ WEBHOOK'ОВ ===\n\n";

// ID тестовой заявки
$testDealId = 669;

echo "1. Получаем текущие данные заявки #$testDealId...\n";

$deal = \CRest::call('crm.deal.get', [
    'id' => $testDealId,
    'select' => ['*', 'UF_CRM_1751271798896', 'UF_CRM_1751271774391']
])['result'];

if (empty($deal['ID'])) {
    echo "❌ Заявка не найдена!\n";
    exit;
}

echo "✅ Заявка найдена: {$deal['TITLE']}\n";
echo "   Номер рейса: " . ($deal['UF_CRM_1751271774391'] ?? 'Пусто') . "\n\n";

echo "2. Изменяем номер рейса...\n";

$newFlightNumber = '109'; // Новый номер рейса

$updateResult = \CRest::call('crm.deal.update', [
    'id' => $testDealId,
    'fields' => [
        'UF_CRM_1751271774391' => $newFlightNumber
    ]
]);

if ($updateResult['result']) {
    echo "✅ Номер рейса изменен на: $newFlightNumber\n";
} else {
    echo "❌ Ошибка при изменении номера рейса: " . json_encode($updateResult) . "\n";
}

echo "\n3. Проверяем логи webhook'ов...\n";
echo "   Логи: /var/www/html/meetRiedeBot/logs/webhook_debug.log\n";
echo "   Логи PHP: /var/log/apache2/error.log\n";

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
?>