<?php
/**
 * Скрипт для поиска существующих заявок
 */

require_once(__DIR__ . '/src/crest/crest.php');

echo "=== ПОИСК ЗАЯВОК ===\n\n";

// Получаем список заявок
$deals = \CRest::call('crm.deal.list', [
    'filter' => [
        'STAGE_ID' => ['PREPAYMENT_INVOICE', 'EXECUTING']
    ],
    'select' => ['ID', 'TITLE', 'STAGE_ID', 'UF_CRM_1751272181'],
    'order' => ['ID' => 'DESC'],
    'start' => 0
])['result'];

if (empty($deals)) {
    echo "❌ Заявки не найдены!\n";
    exit;
}

echo "Найдено заявок: " . count($deals) . "\n\n";

foreach ($deals as $deal) {
    $driverId = $deal['UF_CRM_1751272181'] ?? 'Не назначен';
    echo "ID: {$deal['ID']} | Название: {$deal['TITLE']} | Стадия: {$deal['STAGE_ID']} | Водитель: $driverId\n";
}

echo "\n=== ПОИСК ЗАВЕРШЕН ===\n";
?>






