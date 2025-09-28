# Решение проблем MeetRide Bot

## 🚨 Частые проблемы

### 1. "Class CRest not found"
**Проблема:** PHP не может найти класс CRest
```php
Fatal error: Uncaught Error: Class "CRest" not found
```

**Решение:**
```php
// Добавить в начало функции
require_once(__DIR__ . '/crest/crest.php');
```

### 2. Стадия сделки не меняется
**Проблема:** После нажатия "Принять" стадия остается прежней

**Решение:**
```php
// В функции driverAcceptHandle добавить STAGE_ID
CRest::call('crm.deal.update', [
    'id' => $dealId,
    'fields' => [
        'ASSIGNED_BY_ID' => $driverId,
        'STAGE_ID' => botManager::DRIVER_ACCEPTED_STAGE_ID  // ← Добавить эту строку
    ]
]);
```

### 3. "Пассажиры: Array"
**Проблема:** В сообщении показывается "Array" вместо списка пассажиров

**Решение:**
```php
// В функции orderTextForDriver
$passengers = $deal['UF_CRM_1751271798896'] ?? '';
if (is_array($passengers)) {
    $passengers = implode(", ", $passengers);
}
```

### 4. Бот не отправляет сообщения
**Проблема:** Telegram API не отвечает

**Проверки:**
1. Проверить токен бота
2. Проверить webhook: `curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo`
3. Проверить права бота в группе
4. Проверить логи: `tail -f logs/error.log`

### 5. Bitrix24 не отправляет события
**Проблема:** Webhook не срабатывает

**Проверки:**
1. URL webhook правильный
2. Права доступа к API
3. Обработчик событий активен
4. Проверить логи Bitrix24

## 🔍 Диагностика

### Проверка логов
```bash
# Все логи
tail -f logs/*.log

# Только ошибки
tail -f logs/error.log

# Webhook события
tail -f logs/webhook_debug.log
```

### Проверка конфигурации
```bash
# Синтаксис PHP
php -l src/botManager.php
php -l src/index.php

# Проверка переменных
php -r "include 'config/config.php'; var_dump(get_defined_constants());"
```

### Тестирование API
```bash
# Telegram API
curl "https://api.telegram.org/bot{BOT_TOKEN}/getMe"

# Bitrix24 API
curl -X POST "https://{DOMAIN}.bitrix24.ru/rest/1/{WEBHOOK_CODE}/crm.deal.list" \
  -H "Content-Type: application/json" \
  -d '{"select":["ID","TITLE"]}'
```

## 🛠️ Инструменты отладки

### Добавление логов
```php
// В любом месте кода
file_put_contents('/var/www/html/meetRiedeBot/logs/debug.log', 
    date('Y-m-d H:i:s') . " - " . print_r($data, true) . "\n", 
    FILE_APPEND
);
```

### Проверка переменных
```php
// В функции
error_log("DEBUG: " . print_r($deal, true));
error_log("DEBUG: " . print_r($driver, true));
```

### Тестирование функций
```php
// Создать тестовый файл test.php
<?php
require_once 'config/config.php';
require_once 'src/botManager.php';

$bot = new botManager();
$result = $bot->testFunction();
var_dump($result);
?>
```

## 📞 Получение помощи

1. **Проверить логи** - большинство проблем видны в логах
2. **Проверить конфигурацию** - убедиться что все настройки правильные
3. **Протестировать API** - проверить работу Telegram и Bitrix24
4. **Создать issue** - описать проблему с логами и конфигурацией
