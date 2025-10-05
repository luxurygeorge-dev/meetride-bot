<?php
/**
 * Тестовый скрипт для проверки упрощенной системы изменений
 */

require_once(__DIR__ . '/src/crest/crest.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;

echo "=== ТЕСТ УПРОЩЕННОЙ СИСТЕМЫ ИЗМЕНЕНИЙ ===\n\n";

// ID тестовой заявки
$testDealId = 671;

echo "1. Получаем данные заявки #$testDealId...\n";

$deal = \CRest::call('crm.deal.get', [
    'id' => $testDealId,
    'select' => ['*', 'UF_CRM_1751271798896', botManager::FLIGHT_NUMBER_FIELD]
])['result'];

if (empty($deal['ID'])) {
    echo "❌ Заявка не найдена!\n";
    exit;
}

echo "✅ Заявка найдена: {$deal['TITLE']}\n\n";

echo "2. Тестируем различные сценарии изменений...\n\n";

// Тест 1: Изменение только адреса назначения
echo "Тест 1: Изменение только адреса назначения\n";
$changes1 = ['addressTo' => 'Новый адрес назначения'];
$message1 = botManager::orderTextForDriverWithChangesSimple($deal, $changes1);
echo "Результат:\n";
echo "---\n";
echo $message1;
echo "\n---\n\n";

// Тест 2: Изменение только номера рейса
echo "Тест 2: Изменение только номера рейса\n";
$changes2 = ['flightNumber' => '999'];
$message2 = botManager::orderTextForDriverWithChangesSimple($deal, $changes2);
echo "Результат:\n";
echo "---\n";
echo $message2;
echo "\n---\n\n";

// Тест 3: Изменение нескольких полей
echo "Тест 3: Изменение нескольких полей\n";
$changes3 = [
    'addressTo' => 'Новый адрес назначения',
    'sum' => 1500,
    'date' => '2025-09-29 15:30:00'
];
$message3 = botManager::orderTextForDriverWithChangesSimple($deal, $changes3);
echo "Результат:\n";
echo "---\n";
echo $message3;
echo "\n---\n\n";

echo "=== ТЕСТ ЗАВЕРШЕН ===\n";
?>






