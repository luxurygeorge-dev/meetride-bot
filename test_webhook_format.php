<?php
/**
 * Тестовый скрипт для проверки формата webhook от Bitrix24
 * 
 * Цель: Убедиться что Bitrix24 корректно отправляет webhook при изменении полей сделки
 * и определить, содержит ли webhook старые значения полей (OLD)
 */

// Логирование всех входящих данных
$logFile = __DIR__ . '/test_webhook_log.txt';
$timestamp = date('Y-m-d H:i:s');

// Функция для записи в лог
function writeLog($message) {
    global $logFile, $timestamp;
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Очищаем предыдущий лог
if (file_exists($logFile)) {
    unlink($logFile);
}

writeLog("=== НАЧАЛО ТЕСТИРОВАНИЯ WEBHOOK ===");
writeLog("Время: " . $timestamp);
writeLog("IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'неизвестно'));
writeLog("User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'неизвестно'));

// Логируем все входящие данные
writeLog("\n=== ВСЕ ВХОДЯЩИЕ ДАННЫЕ ===");
writeLog("GET параметры:");
writeLog(print_r($_GET, true));

writeLog("\nPOST данные:");
writeLog(print_r($_POST, true));

writeLog("\nREQUEST данные:");
writeLog(print_r($_REQUEST, true));

writeLog("\nRaw POST данные:");
$rawPost = file_get_contents('php://input');
writeLog($rawPost);

// Парсим JSON если есть
if ($rawPost) {
    $jsonData = json_decode($rawPost, true);
    if ($jsonData) {
        writeLog("\nJSON данные:");
        writeLog(print_r($jsonData, true));
    }
}

// Анализируем структуру webhook
writeLog("\n=== АНАЛИЗ СТРУКТУРЫ WEBHOOK ===");

// Проверяем основные поля
$event = $_REQUEST['event'] ?? 'НЕ НАЙДЕН';
$eventHandlerId = $_REQUEST['event_handler_id'] ?? 'НЕ НАЙДЕН';
$data = $_REQUEST['data'] ?? [];

writeLog("Event: $event");
writeLog("Event Handler ID: $eventHandlerId");

if (!empty($data)) {
    writeLog("\nСтруктура data:");
    writeLog(print_r($data, true));
    
    // Проверяем поля сделки
    if (isset($data['FIELDS'])) {
        $fields = $data['FIELDS'];
        writeLog("\nИзменённые поля (FIELDS):");
        writeLog(print_r($fields, true));
        
        // Ищем ID сделки
        $dealId = $fields['ID'] ?? 'НЕ НАЙДЕН';
        writeLog("Deal ID: $dealId");
    }
    
    // Проверяем наличие старых значений
    if (isset($data['FIELDS']['OLD'])) {
        writeLog("\n✅ СТАРЫЕ ЗНАЧЕНИЯ (OLD) ПРИСУТСТВУЮТ:");
        writeLog(print_r($data['FIELDS']['OLD'], true));
        writeLog("\n🎉 ОТЛИЧНО! Bitrix24 отправляет старые значения.");
        writeLog("Можем использовать их для сравнения изменений.");
    } else {
        writeLog("\n❌ СТАРЫЕ ЗНАЧЕНИЯ (OLD) ОТСУТСТВУЮТ!");
        writeLog("Bitrix24 может НЕ отправлять старые значения в webhook.");
        writeLog("Возможно нужно использовать другой метод для получения старых значений.");
    }
    
    // Проверяем другие возможные поля
    $possibleFields = ['OLD', 'old', 'previous', 'PREVIOUS', 'before', 'BEFORE'];
    foreach ($possibleFields as $field) {
        if (isset($data[$field])) {
            writeLog("\nНайдено поле '$field':");
            writeLog(print_r($data[$field], true));
        }
    }
} else {
    writeLog("❌ Данные webhook отсутствуют или пусты");
}

// Проверяем заголовки
writeLog("\n=== HTTP ЗАГОЛОВКИ ===");
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        writeLog("$key: $value");
    }
}

writeLog("\n=== КОНЕЦ ТЕСТИРОВАНИЯ ===");

// Возвращаем успешный ответ
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Webhook получен и проанализирован',
    'timestamp' => $timestamp,
    'log_file' => basename($logFile)
]);

// Дополнительная проверка - попробуем получить данные сделки через API
if (isset($dealId) && $dealId !== 'НЕ НАЙДЕН') {
    writeLog("\n=== ДОПОЛНИТЕЛЬНАЯ ПРОВЕРКА ===");
    writeLog("Попытка получить данные сделки $dealId через API...");
    
    try {
        // Подключаем CRest если доступен
        if (file_exists('/home/telegramBot/crest/crest.php')) {
            require_once('/home/telegramBot/crest/crest.php');
            
            $dealData = \CRest::call('crm.deal.get', ['id' => $dealId]);
            if ($dealData && isset($dealData['result'])) {
                writeLog("✅ Данные сделки получены через API:");
                writeLog(print_r($dealData['result'], true));
            } else {
                writeLog("❌ Не удалось получить данные сделки через API");
            }
        } else {
            writeLog("⚠️ CRest не доступен для дополнительной проверки");
        }
    } catch (Exception $e) {
        writeLog("❌ Ошибка при получении данных сделки: " . $e->getMessage());
    }
}

writeLog("\n=== ФИНАЛЬНЫЙ АНАЛИЗ ===");
writeLog("1. Webhook получен: " . (empty($data) ? 'НЕТ' : 'ДА'));
writeLog("2. Содержит FIELDS: " . (isset($data['FIELDS']) ? 'ДА' : 'НЕТ'));
writeLog("3. Содержит OLD значения: " . (isset($data['FIELDS']['OLD']) ? 'ДА' : 'НЕТ'));
writeLog("4. Deal ID: " . ($dealId ?? 'НЕ НАЙДЕН'));

if (isset($data['FIELDS']['OLD'])) {
    writeLog("\n🎯 РЕКОМЕНДАЦИЯ: Использовать OLD значения для сравнения изменений");
} else {
    writeLog("\n🎯 РЕКОМЕНДАЦИЯ: Реализовать локальный кеш для хранения предыдущих значений");
}

writeLog("\n=== ТЕСТ ЗАВЕРШЁН ===");
?>
