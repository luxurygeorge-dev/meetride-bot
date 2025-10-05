<?php
/**
 * Исправление SERVICE полей для заявки 671
 */

require_once(__DIR__ . '/src/crest/crest.php');

echo "=== ИСПРАВЛЕНИЕ ЗАЯВКИ 671 ===\n\n";

// Получаем данные заявки
$deal = \CRest::call('crm.deal.get', [
    'id' => 671,
    'select' => ['*', 'UF_CRM_1751271798896']
])['result'];

if (empty($deal['ID'])) {
    echo "❌ Заявка не найдена!\n";
    exit;
}

echo "Текущие значения:\n";
echo "  Дополнительные условия (основное): " . ($deal['UF_CRM_1751269256380'] ?? 'Пусто') . "\n";
echo "  Дополнительные условия (SERVICE): " . ($deal['UF_CRM_1758709126'] ?? 'Пусто') . "\n";
echo "  Пассажиры (основное): " . ($deal['UF_CRM_1751271798896'] ?? 'Пусто') . "\n";
echo "  Пассажиры (SERVICE): " . ($deal['UF_CRM_1758709139'] ?? 'Пусто') . "\n";
echo "  Номер рейса (основное): " . ($deal['UF_CRM_1751271774391'] ?? 'Пусто') . "\n";
echo "  Номер рейса (SERVICE): " . ($deal['UF_CRM_1758710216'] ?? 'Пусто') . "\n";
echo "  Класс авто (основное): " . ($deal['UF_CRM_1751271728682'] ?? 'Пусто') . "\n";
echo "  Класс авто (SERVICE): " . ($deal['UF_CRM_1751271841129'] ?? 'Пусто') . "\n\n";

// Исправляем SERVICE поля
$updateFields = [
    'UF_CRM_1758709126' => $deal['UF_CRM_1751269256380'], // Дополнительные условия
    'UF_CRM_1758709139' => $deal['UF_CRM_1751271798896'], // Пассажиры
    'UF_CRM_1758710216' => $deal['UF_CRM_1751271774391'], // Номер рейса
    'UF_CRM_1751271841129' => $deal['UF_CRM_1751271728682'] // Класс автомобиля
];

echo "Обновляем SERVICE поля...\n";

$result = \CRest::call('crm.deal.update', [
    'id' => 671,
    'fields' => $updateFields
]);

if (isset($result['result']) && $result['result'] === true) {
    echo "✅ Заявка 671 обновлена!\n";
} else {
    echo "❌ Ошибка обновления: " . json_encode($result) . "\n";
}

echo "\n=== ИСПРАВЛЕНИЕ ЗАВЕРШЕНО ===\n";
?>






