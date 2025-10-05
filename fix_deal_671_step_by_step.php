<?php
/**
 * Пошаговое исправление SERVICE полей для заявки 671
 */

require_once(__DIR__ . '/src/crest/crest.php');

echo "=== ПОШАГОВОЕ ИСПРАВЛЕНИЕ ЗАЯВКИ 671 ===\n\n";

// Исправляем каждое поле отдельно
$fields = [
    'UF_CRM_1758709126' => [], // Дополнительные условия (пустой массив)
    'UF_CRM_1758709139' => ["89883900224"], // Пассажиры
    'UF_CRM_1758710216' => "107", // Номер рейса
    'UF_CRM_1751271841129' => "119" // Класс автомобиля
];

foreach ($fields as $fieldName => $value) {
    echo "Обновляем $fieldName = " . json_encode($value) . "...\n";
    
    $result = \CRest::call('crm.deal.update', [
        'id' => 671,
        'fields' => [$fieldName => $value]
    ]);
    
    if (isset($result['result']) && $result['result'] === true) {
        echo "  ✅ Успешно обновлено\n";
    } else {
        echo "  ❌ Ошибка: " . json_encode($result) . "\n";
    }
    echo "\n";
}

echo "=== ИСПРАВЛЕНИЕ ЗАВЕРШЕНО ===\n";
?>






