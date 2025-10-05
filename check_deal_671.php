<?php
/**
 * Проверка заявки 671 после обновления
 */

require_once(__DIR__ . '/src/crest/crest.php');

echo "=== ПРОВЕРКА ЗАЯВКИ 671 ===\n\n";

// Получаем данные заявки
$deal = \CRest::call('crm.deal.get', [
    'id' => 671,
    'select' => ['*', 'UF_CRM_1751271798896']
])['result'];

if (empty($deal['ID'])) {
    echo "❌ Заявка не найдена!\n";
    exit;
}

echo "Текущие значения после обновления:\n";
echo "  Дополнительные условия (основное): " . json_encode($deal['UF_CRM_1751269256380'] ?? 'Пусто') . "\n";
echo "  Дополнительные условия (SERVICE): " . json_encode($deal['UF_CRM_1758709126'] ?? 'Пусто') . "\n";
echo "  Пассажиры (основное): " . json_encode($deal['UF_CRM_1751271798896'] ?? 'Пусто') . "\n";
echo "  Пассажиры (SERVICE): " . json_encode($deal['UF_CRM_1758709139'] ?? 'Пусто') . "\n";
echo "  Номер рейса (основное): " . json_encode($deal['UF_CRM_1751271774391'] ?? 'Пусто') . "\n";
echo "  Номер рейса (SERVICE): " . json_encode($deal['UF_CRM_1758710216'] ?? 'Пусто') . "\n";
echo "  Класс авто (основное): " . json_encode($deal['UF_CRM_1751271728682'] ?? 'Пусто') . "\n";
echo "  Класс авто (SERVICE): " . json_encode($deal['UF_CRM_1751271841129'] ?? 'Пусто') . "\n\n";

echo "=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";
?>






