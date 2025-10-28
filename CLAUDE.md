# MeetRide Bot - Документация для Claude

## 📋 Описание проекта

**MeetRide Bot** — это Telegram-бот для автоматизации управления заявками на такси с интеграцией Bitrix24 CRM. Система автоматизирует весь процесс от создания заявки до её выполнения, обеспечивая связь между диспетчерской и водителями через Telegram.

### Основные функции:
- ✅ **Автоматическая отправка заявок** в общий чат водителей
- ✅ **Принятие заявок** любыми пользователями (зарегистрированными и новыми)
- ✅ **Полный workflow** (Принять → Начать → Завершить/Отменить)
- ✅ **Уведомления об изменениях** полей заявки
- ✅ **Система напоминаний** за час до поездки
- ✅ **Защита контактов** (пассажиры только в личку водителю)

## 🏗️ Стек технологий

### Backend
- **PHP 7.4+** — основной язык программирования
- **Bitrix24 REST API** — интеграция с CRM системой
- **Telegram Bot API** — взаимодействие с Telegram

### Инфраструктура
- **Webhook** — обработка событий от Bitrix24 и Telegram
- **Linux Server** — хостинг на Ubuntu/Debian
- **Apache/Nginx** — веб-сервер
- **Cron** — планировщик задач для напоминаний

### Библиотеки и зависимости
- **Longman\TelegramBot** — PHP SDK для Telegram Bot API
- **CRest** — клиент для Bitrix24 REST API
- **Carbon** — работа с датами и временем
- **Guzzle HTTP** — HTTP клиент

## 📁 Структура файлов

```
meetride/
├── 📄 Основные файлы
│   ├── botManager.php                    # Основная логика бота (класс Store\botManager)
│   ├── webhook_stage3.php               # Webhook для стадии PREPAYMENT_INVOICE
│   ├── src/index.php                    # Точка входа для webhook'ов
│   └── simple_test.php                  # Тестирование констант и конфигурации
│
├── 🔔 Система напоминаний
│   ├── reminder_scheduler.php           # Основной планировщик напоминаний
│   ├── reminder_scheduler_simple.php    # Упрощенная версия для cron
│   └── send_missed_notifications.php    # Отправка пропущенных уведомлений
│
├── 🛠️ Скрипты управления
│   ├── scripts/
│   │   ├── switch_environment.sh        # Переключение между тест/прод
│   │   ├── setup-test-environment.sh    # Настройка тестовой среды
│   │   ├── deploy-to-test.sh           # Деплой в тестовую среду
│   │   └── sync-to-production.sh       # Синхронизация с продакшном
│   ├── run_bot.sh                      # Запуск бота
│   ├── monitor_bot.sh                  # Мониторинг работы бота
│   └── setup_cron.sh                   # Настройка cron задач
│
├── 📚 Документация
│   ├── README.md                        # Основная документация
│   ├── WORKFLOW.md                      # Описание бизнес-процессов
│   ├── USAGE.md                        # Руководство по использованию
│   ├── TESTING_CHECKLIST.md            # Чек-лист тестирования
│   └── ИНСТРУКЦИЯ_ПО_ТЕСТИРОВАНИЮ.md   # Подробная инструкция по тестированию
│
├── 🧪 Тестирование
│   ├── meetride-bot/                    # Дублированная структура для тестирования
│   │   ├── test_bot_functions.php       # Тесты функций бота
│   │   ├── test_webhook_safe.php        # Безопасное тестирование webhook'ов
│   │   └── simple_test.php              # Простые тесты
│   └── logs/                           # Логи системы
│       ├── webhook_debug.log           # Отладочные логи webhook'ов
│       ├── bots.log                    # Логи работы ботов
│       └── reminder_scheduler.log      # Логи системы напоминаний
│
├── ⚙️ Конфигурация
│   ├── config/
│   │   └── config.example.php          # Пример конфигурации
│   ├── composer.json                   # PHP зависимости
│   └── requirements.txt                # Python зависимости (для вспомогательных скриптов)
│
└── 📊 Данные
    ├── vendor/                         # PHP зависимости (Composer)
    ├── src/crest/                      # Bitrix24 REST API клиент
    └── logs/                          # Логи и мониторинг
```

## 🔧 Основные компоненты

### 1. Класс `Store\botManager`
Центральный класс системы, содержащий всю бизнес-логику:

#### Константы полей Bitrix24:
```php
// Основные поля заявки
public const DRIVER_ID_FIELD = 'UF_CRM_1751272181';
public const DRIVER_TELEGRAM_ID_FIELD = 'UF_CRM_1751185017761';
public const ADDRESS_FROM_FIELD = 'UF_CRM_1751269147414';
public const ADDRESS_TO_FIELD = 'UF_CRM_1751269175432';
public const TRAVEL_DATE_TIME_FIELD = 'UF_CRM_1751269222959';
public const PASSENGERS_FIELD = 'UF_CRM_1751271798896';

// SERVICE поля для отслеживания изменений
public const DRIVER_SUM_FIELD_SERVICE = 'UF_CRM_1751638441407';
public const ADDRESS_FROM_FIELD_SERVICE = 'UF_CRM_1751638512';
// ... и другие SERVICE поля
```

#### Константы стадий:
```php
public const NEW_DEAL_STAGE_ID = 'NEW';
public const DRIVER_CHOICE_STAGE_ID = 'PREPARATION';
public const TRAVEL_STARTED_STAGE_ID = 'EXECUTING';
public const FINISH_STAGE_ID = 'FINAL_INVOICE';
public const DRIVERS_GROUP_CHAT_ID = '-1002544521661'; // Боевая группа
```

#### Основные методы:
- `newDealMessage($dealid, $telegram)` — отправка новой заявки в группу
- `buttonHandle($callbackData, $telegram, $update)` — обработка нажатий кнопок
- `driverAcceptHandle($dealId, $telegram, $update)` — принятие заявки водителем
- `dealChangeHandle($dealId, $telegram)` — обработка изменений заявки
- `sendPrivateMessageToDriver($deal, $telegram)` — отправка деталей водителю
- `orderTextForGroup($deal, $driverName)` — форматирование сообщения для группы
- `orderTextForDriver($deal)` — форматирование сообщения для водителя
- `formatDateTime($dateString)` — форматирование даты и времени

### 2. Webhook обработчики
- **`src/index.php`** — основной webhook для событий Bitrix24
- **`webhook_stage3.php`** — специальный webhook для стадии PREPAYMENT_INVOICE

### 3. Система напоминаний
- **`reminder_scheduler.php`** — планировщик напоминаний
- **Cron задачи** — автоматическая отправка напоминаний за час до поездки

### 4. Скрипты автоматизации
- **`switch_environment.sh`** — переключение между тестовой и боевой средой
- **`deploy-to-test.sh`** — деплой в тестовую среду
- **`setup-test-environment.sh`** — настройка тестовой среды

## 🔄 Частые задачи

### 1. Переключение среды (тест/прод)
```bash
# Переключение на тестовую группу
./scripts/switch_environment.sh test

# Переключение на боевую группу  
./scripts/switch_environment.sh production
```

### 2. Деплой изменений
```bash
# Копирование изменений на веб-сервер
cp /root/meetride/botManager.php /var/www/html/meetRiedeBot/botManager.php

# Проверка синтаксиса
php -l /var/www/html/meetRiedeBot/botManager.php
```

### 3. Тестирование функций
```bash
# Запуск простых тестов
php simple_test.php

# Тестирование функций бота
php meetride-bot/test_bot_functions.php

# Безопасное тестирование webhook'ов
php meetride-bot/test_webhook_safe.php
```

### 4. Отладка и мониторинг
```bash
# Просмотр логов webhook'ов
tail -f /var/www/html/meetRiedeBot/logs/webhook_debug.log

# Просмотр логов ботов
tail -f logs/bots.log

# Мониторинг работы бота
./monitor_bot.sh
```

### 5. Работа с заявками
```php
// Тестирование конкретной заявки
$dealId = 815;
$telegram = new \Telegram\Bot\Api('BOT_TOKEN');
botManager::newDealMessage($dealId, $telegram);

// Проверка изменений заявки
botManager::dealChangeHandle($dealId, $telegram);
```

### 6. Настройка webhook'ов Bitrix24
- **URL webhook:** `https://skysoft24.ru/meetRiedeBot/index.php`
- **События:** `ONCRMDEALUPDATE`
- **Стадии:** `PREPARATION`, `PREPAYMENT_INVOICE`

### 7. Управление системой напоминаний
```bash
# Запуск планировщика напоминаний
php reminder_scheduler_simple.php

# Отправка пропущенных уведомлений
php send_missed_notifications.php

# Настройка cron (каждые 5 минут)
*/5 * * * * /usr/bin/php /root/meetride/reminder_scheduler_simple.php
```

### 8. Резервное копирование и восстановление
```bash
# Создание бэкапа
./deploy/backup.sh

# Восстановление из бэкапа
./deploy/backup.sh restore
```

## 🚨 Важные особенности

### Безопасность
- **Конфиденциальность:** Пассажиры показываются только в личке водителю
- **Валидация:** Проверка прав водителя на принятие заявки
- **Защита от спама:** Предотвращение повторного принятия заявок

### Производительность
- **Кэширование:** Кэш сделок для быстрого доступа
- **Логирование:** Подробные логи для отладки
- **Асинхронность:** Webhook'и не блокируют основной процесс

### Надежность
- **Обработка ошибок:** Graceful handling всех исключений
- **Fallback механизмы:** Резервные варианты при сбоях
- **Мониторинг:** Автоматическое отслеживание состояния системы

## 📞 Поддержка

При возникновении проблем:
1. Проверить логи: `/var/www/html/meetRiedeBot/logs/webhook_debug.log`
2. Проверить конфигурацию: `php simple_test.php`
3. Протестировать webhook: `php meetride-bot/test_webhook_safe.php`
4. Связаться с разработчиком для решения сложных вопросов


