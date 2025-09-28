# 📁 Структура проекта MeetRide

## 🎯 Полная структура проекта

```
meetride/
├── 📄 Основные файлы
│   ├── telegram_to_bitrix.py          # Телеграм-бот для создания сделок
│   ├── botManager.php                 # Менеджер ботов с методами CRM
│   └── requirements.txt               # Зависимости Python
│
├── 🔔 Система напоминаний
│   ├── reminder_scheduler.php         # Основной планировщик (токен: 7529690360)
│   ├── reminder_scheduler_simple.php  # Упрощенная версия для cron
│   ├── reminder_scheduler_working.php # Рабочая версия
│   └── test_reminder_system.php       # Тесты системы
│
├── 🛠️ Скрипты управления
│   ├── run_bot.sh                     # Запуск бота
│   ├── monitor_bot.sh                 # Мониторинг бота
│   ├── install_service.sh             # Установка как сервис
│   └── setup_cron.sh                  # Настройка cron
│
├── 📚 Документация
│   ├── README.md                      # Основная документация
│   ├── BOT_ANALYSIS.md                # Анализ настроек и интеграций
│   ├── FINAL_REPORT.md                # Финальный отчет готовности
│   ├── REMINDER_SYSTEM_README.md      # Документация системы напоминаний
│   └── PROJECT_STRUCTURE.md           # Этот файл
│
└── 📊 Логи
    └── logs/
        ├── bots.log                   # Логи ботов
        ├── cron.log                   # Логи cron задач
        └── reminder_scheduler.log     # Логи системы напоминаний
```

## 🤖 Telegram боты

### 1. Основной бот (создание сделок)
- **Токен**: `7992462078:AAGJ46crBdOMSAuIfWncFd0AEjrDiT4Tnww`
- **Файл**: `telegram_to_bitrix.py`
- **Функция**: Парсинг заявок и создание сделок в Bitrix24
- **Поддерживает**: Числовые и текстовые номера заявок

### 2. Система напоминаний
- **Токен**: `7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4`
- **Файл**: `reminder_scheduler.php`
- **Функция**: Автоматические напоминания водителям
- **Расписание**: Каждые 5 минут через cron

## 🔗 Bitrix24 интеграция

- **URL**: `https://meetride.bitrix24.ru/`
- **Webhook**: `/rest/9/oo1pdplpuoy0q9ur/crm.deal.add.json`
- **Библиотека**: CRest для всех интеграций
- **Совместимость**: Все боты работают независимо

## ✅ Статус системы

| Компонент | Статус | Описание |
|-----------|--------|----------|
| Основной бот | ✅ Работает | Создание сделок из Telegram |
| Система напоминаний | ✅ Готова | Автоматические уведомления |
| CRest интеграция | ✅ Настроена | Работа с Bitrix24 API |
| Cron планировщик | ✅ Активен | Автоматический запуск |
| Логирование | ✅ Настроено | Полное отслеживание работы |

## 🚀 Как запустить

### Основной бот
```bash
cd /root/meetride
python3 telegram_to_bitrix.py
```

### Система напоминаний
```bash
cd /root/meetride
php reminder_scheduler.php
```

### Установка cron
```bash
cd /root/meetride
./setup_cron.sh
```

## 📋 Зависимости

### Python
- pyTelegramBotAPI==4.12.0
- requests==2.31.0
- dateparser==1.1.8

### PHP
- CRest библиотека (путь: `/home/telegramBot/crest/crest.php`)
- cURL для HTTP запросов

## 🔧 Конфигурация

Все настройки находятся в соответствующих файлах:
- Токены ботов в начале файлов
- URL Bitrix24 в webhook переменных
- Поля CRM описаны в документации

## 📞 Поддержка

Все файлы проекта находятся в папке `/root/meetride/`
Логи доступны в папке `logs/`
Документация в файлах `*.md`

