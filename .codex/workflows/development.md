# Процесс разработки MeetRide Bot

## 🚀 Быстрый старт

### 1. Клонирование и настройка
```bash
git clone https://github.com/luxurygeorge-dev/meetride-bot.git
cd meetride-bot
cp config/config.example.php config/config.php
# Отредактировать config/config.php
```

### 2. Тестирование
```bash
# Переключиться на тестовый режим
./scripts/switch_environment.sh test

# Проверить логи
tail -f logs/webhook_debug.log
```

## 🔧 Разработка

### Структура изменений
1. **Новая функция** → добавить в `botManager.php`
2. **Webhook обработка** → изменить `index.php`
3. **Конфигурация** → обновить `config.example.php`

### Тестирование изменений
```bash
# 1. Переключиться на тест
./scripts/switch_environment.sh test

# 2. Создать тестовую заявку в Bitrix24
# 3. Проверить логи
tail -f logs/webhook_debug.log

# 4. Протестировать кнопки в Telegram
```

### Отладка
```php
// Добавить в код для отладки
file_put_contents('/var/www/html/meetRiedeBot/logs/debug.log', 
    date('Y-m-d H:i:s') . " - DEBUG: " . print_r($data, true) . "\n", 
    FILE_APPEND
);
```

## 📦 Деплой

### Тестовый деплой
```bash
./deploy/deploy.sh test
```

### Продакшн деплой
```bash
./deploy/deploy.sh production
```

### Откат
```bash
./deploy/backup.sh restore
```

## 🐛 Решение проблем

### Бот не отвечает
1. Проверить webhook: `curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo`
2. Проверить логи: `tail -f logs/error.log`
3. Проверить конфигурацию: `php -l src/botManager.php`

### Bitrix24 не отправляет события
1. Проверить настройки webhook в Bitrix24
2. Проверить права доступа к API
3. Проверить логи Bitrix24

### Ошибки в коде
1. Включить отображение ошибок PHP
2. Проверить синтаксис: `php -l src/botManager.php`
3. Проверить логи: `tail -f logs/error.log`

## 📋 Чек-лист перед деплоем

- [ ] Код протестирован в тестовом режиме
- [ ] Логи не содержат ошибок
- [ ] Конфигурация обновлена
- [ ] Резервная копия создана
- [ ] Webhook настроен
- [ ] Telegram бот работает
