<?php
/**
 * Скрипт для массовой отправки сделок на стадии PREPARATION в общий чат водителей
 * Использует функцию newDealMessage из botManager
 */

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/botManager.php');

use Store\botManager;
use Telegram\Bot\Api;

// Конфигурация
$telegramToken = '7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4';
$logFile = __DIR__ . '/logs/bulk_mailing.log';

// Создаем директорию для логов, если её нет
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

echo "🚀 Запуск массовой рассылки сделок на стадии PREPARATION...\n";
echo "========================================================\n";

try {
    // Инициализируем Telegram API
    $telegram = new Api($telegramToken);
    
    // Получаем все сделки на стадии PREPARATION
    $deals = \CRest::call('crm.deal.list', [
        'filter' => [
            'STAGE_ID' => botManager::DRIVER_CHOICE_STAGE_ID // PREPARATION
        ],
        'select' => ['ID', 'TITLE', 'STAGE_ID', 'DATE_CREATE', 'UF_CRM_1751272181']
    ])['result'];

    if (empty($deals)) {
        echo "❌ Сделок на стадии PREPARATION не найдено.\n";
        exit(0);
    }

    echo "✅ Найдено сделок для отправки: " . count($deals) . "\n\n";

    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    foreach ($deals as $deal) {
        $dealId = $deal['ID'];
        $dealTitle = $deal['TITLE'];
        
        echo "📤 Отправляем заявку #$dealTitle (ID: $dealId)... ";
        
        try {
            // Используем функцию newDealMessage из botManager
            $result = botManager::newDealMessage($dealId, $telegram);
            
            if ($result) {
                echo "✅ Успешно\n";
                $successCount++;
                
                // Логируем успех
                $logMessage = date('Y-m-d H:i:s') . " - Успешно отправлена заявка #$dealTitle (ID: $dealId)\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
            } else {
                echo "❌ Ошибка отправки\n";
                $errorCount++;
                $errors[] = "Заявка #$dealTitle (ID: $dealId) - ошибка отправки";
                
                // Логируем ошибку
                $logMessage = date('Y-m-d H:i:s') . " - ОШИБКА отправки заявки #$dealTitle (ID: $dealId)\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
            }
        } catch (Exception $e) {
            echo "❌ Исключение: " . $e->getMessage() . "\n";
            $errorCount++;
            $errors[] = "Заявка #$dealTitle (ID: $dealId) - исключение: " . $e->getMessage();
            
            // Логируем исключение
            $logMessage = date('Y-m-d H:i:s') . " - ИСКЛЮЧЕНИЕ для заявки #$dealTitle (ID: $dealId): " . $e->getMessage() . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
        
        // Небольшая пауза между отправками
        sleep(1);
    }

    echo "\n========================================================\n";
    echo "📊 РЕЗУЛЬТАТЫ МАССОВОЙ РАССЫЛКИ:\n";
    echo "- Всего обработано: " . count($deals) . "\n";
    echo "- Успешно отправлено: $successCount\n";
    echo "- Ошибок: $errorCount\n";
    
    if (!empty($errors)) {
        echo "\n❌ ДЕТАЛИ ОШИБОК:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    
    echo "\n📝 Лог сохранен в: $logFile\n";

} catch (Exception $e) {
    $errorMessage = date('Y-m-d H:i:s') . " - КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Массовая рассылка завершена!\n";
?>
