# MeetRide Telegram Bot

Система автоматизации заявок для MeetRide через Telegram бота.

## 🚀 Функциональность

- ✅ **Автоматическая отправка заявок** в общий чат водителей
- ✅ **Принятие заявок любыми пользователями** (зарегистрированными и новыми)
- ✅ **Полный workflow** (Принять → Начать → Завершить/Отменить)
- ✅ **Уведомления об изменениях** полей заявки
- ✅ **Система напоминаний** за час до поездки
- ✅ **Защита контактов** (пассажиры только в личку водителю)

## 📁 Структура проекта

```
src/
├── botManager.php      # Основная логика бота
├── webhook.php         # Обработчик webhook от Telegram
└── index.php          # Обработчик событий от Битрикс24

config/
├── config.example.php  # Шаблон конфигурации
├── .env.example       # Пример переменных окружения
└── secrets.php        # Секретные данные (НЕ в Git!)

deploy/
├── switch_chat.sh     # Переключение между тест/прод чатами
├── deploy.sh         # Скрипт деплоя
└── backup.sh         # Резервное копирование

docs/
├── SETUP.md          # Инструкция по установке
├── API.md           # Описание API функций
└── WORKFLOW.md      # Описание бизнес-процессов
```

## 🔧 Установка

1. **Клонировать репозиторий:**
```bash
git clone https://github.com/YOUR_USERNAME/meetride-bot.git
cd meetride-bot
```

2. **Настроить конфигурацию:**
```bash
cp config/config.example.php config/config.php
cp config/.env.example .env
# Отредактировать config.php и .env
```

3. **Установить зависимости:**
```bash
composer install
```

4. **Настроить webhook в Битрикс24:**
```
https://your-domain.com/meetride-bot/index.php?dealId={{ID}}&stage={{Стадия сделки (текст)}}
```

## 🧪 Тестирование

```bash
# Переключить на тестовый режим
./deploy/switch_chat.sh test

# Переключить на боевой режим  
./deploy/switch_chat.sh production

# Проверить состояние
./deploy/switch_chat.sh status
```

## 📊 Мониторинг

Логи находятся в:
- `logs/webhook.log` - события от Telegram
- `logs/bitrix.log` - события от Битрикс24
- `logs/error.log` - ошибки системы

## 🔒 Безопасность

- Все секретные данные в `.env` и `config/secrets.php`
- Приватный репозиторий
- Автоматические бэкапы
- Логирование всех действий

## 📞 Поддержка

При проблемах проверьте:
1. Логи в `logs/`
2. Настройки в `config/`
3. Статус webhook: `curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo`
