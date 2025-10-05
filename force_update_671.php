<?php
/**
 * Принудительное обновление SERVICE полей для заявки 671
 */

require_once(__DIR__ . '/src/crest/crest.php');

echo "=== ПРИНУДИТЕЛЬНОЕ ОБНОВЛЕНИЕ ЗАЯВКИ 671 ===\n\n";

// Получаем текущие данные
$deal = \CRest::call('crm.deal.get', [
    'id' => 671,
    'select' => ['*', 'UF_CRM_1751271798896']
])['result'];

echo "Текущие значения:\n";
echo "  Дополнительные условия (основное): " . json_encode($deal['UF_CRM_1751269256380'] ?? 'Пусто') . "\n";
echo "  Дополнительные условия (SERVICE): " . json_encode($deal['UF_CRM_1758709126'] ?? 'Пусто') . "\n";
echo "  Пассажиры (основное): " . json_encode($deal['UF_CRM_1751271798896'] ?? 'Пусто') . "\n";
echo "  Пассажиры (SERVICE): " . json_encode($deal['UF_CRM_1758709139'] ?? 'Пусто') . "\n";
echo "  Номер рейса (основное): " . json_encode($deal['UF_CRM_1751271774391'] ?? 'Пусто') . "\n";
echo "  Номер рейса (SERVICE): " . json_encode($deal['UF_CRM_1758710216'] ?? 'Пусто') . "\n";
echo "  Класс авто (основное): " . json_encode($deal['UF_CRM_1751271728682'] ?? 'Пусто') . "\n";
echo "  Класс авто (SERVICE): " . json_encode($deal['UF_CRM_1751271841129'] ?? 'Пусто') . "\n\n";

// Подготавливаем правильные значения для SERVICE полей
$mainAdditionalConditions = $deal['UF_CRM_1751269256380'] ?? [];
$mainPassengers = $deal['UF_CRM_1751271798896'] ?? [];
$mainFlightNumber = $deal['UF_CRM_1751271774391'] ?? '';
$mainCarClass = $deal['UF_CRM_1751271728682'] ?? '';

echo "Обновляем SERVICE поля правильными значениями:\n";
echo "  Дополнительные условия: " . json_encode($mainAdditionalConditions) . "\n";
echo "  Пассажиры: " . json_encode($mainPassengers) . "\n";
echo "  Номер рейса: " . json_encode($mainFlightNumber) . "\n";
echo "  Класс авто: " . json_encode($mainCarClass) . "\n\n";

// Обновляем все поля сразу
$updateFields = [
    'UF_CRM_1758709126' => $mainAdditionalConditions, // Дополнительные условия
    'UF_CRM_1758709139' => $mainPassengers, // Пассажиры
    'UF_CRM_1758710216' => $mainFlightNumber, // Номер рейса
    'UF_CRM_1751271841129' => $mainCarClass // Класс автомобиля
];

echo "Отправляем обновление...\n";

$result = \CRest::call('crm.deal.update', [
    'id' => 671,
    'fields' => $updateFields
]);

echo "Результат обновления: " . json_encode($result) . "\n\n";

if (isset($result['result']) && $result['result'] === true) {
    echo "✅ Заявка 671 обновлена!\n";
    
    // Проверяем результат
    echo "\nПроверяем результат...\n";
    $updatedDeal = \CRest::call('crm.deal.get', [
        'id' => 671,
        'select' => ['*', 'UF_CRM_1751271798896']
    ])['result'];
    
    echo "Новые значения:\n";
    echo "  Дополнительные условия (SERVICE): " . json_encode($updatedDeal['UF_CRM_1758709126'] ?? 'Пусто') . "\n";
    echo "  Пассажиры (SERVICE): " . json_encode($updatedDeal['UF_CRM_1758709139'] ?? 'Пусто') . "\n";
    echo "  Номер рейса (SERVICE): " . json_encode($updatedDeal['UF_CRM_1758710216'] ?? 'Пусто') . "\n";
    echo "  Класс авто (SERVICE): " . json_encode($updatedDeal['UF_CRM_1751271841129'] ?? 'Пусто') . "\n";
} else {
    echo "❌ Ошибка обновления!\n";
}

echo "\n=== ОБНОВЛЕНИЕ ЗАВЕРШЕНО ===\n";
?>






