<?php
/**
 * Скрипт для поиска сделок на стадии PREPARATION
 * Эти сделки нужно отправить в общий чат водителей
 */

require_once(__DIR__ . '/src/crest/crest.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;

echo "🔍 Поиск сделок на стадии PREPARATION...\n";
echo "=====================================\n";

try {
    // Получаем все сделки на стадии PREPARATION
    $deals = \CRest::call('crm.deal.list', [
        'filter' => [
            'STAGE_ID' => botManager::DRIVER_CHOICE_STAGE_ID // PREPARATION
        ],
        'select' => ['ID', 'TITLE', 'STAGE_ID', 'DATE_CREATE', 'UF_CRM_1751272181'] // ID, название, стадия, дата создания, водитель
    ])['result'];

    if (empty($deals)) {
        echo "❌ Сделок на стадии PREPARATION не найдено.\n";
        exit(0);
    }

    echo "✅ Найдено сделок на стадии PREPARATION: " . count($deals) . "\n\n";

    foreach ($deals as $deal) {
        $driverId = $deal['UF_CRM_1751272181'] ?? 'Не назначен';
        $dateCreate = date('d.m.Y H:i', strtotime($deal['DATE_CREATE']));
        
        echo "📋 ID: {$deal['ID']} | Название: {$deal['TITLE']} | Водитель: $driverId | Создана: $dateCreate\n";
    }

    echo "\n=====================================\n";
    echo "📊 СТАТИСТИКА:\n";
    echo "- Всего сделок на PREPARATION: " . count($deals) . "\n";
    
    // Подсчитываем сделки без водителя
    $dealsWithoutDriver = array_filter($deals, function($deal) {
        return empty($deal['UF_CRM_1751272181']);
    });
    
    echo "- Сделок без водителя: " . count($dealsWithoutDriver) . "\n";
    echo "- Сделок с водителем: " . (count($deals) - count($dealsWithoutDriver)) . "\n";

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Поиск завершен успешно!\n";
?>





