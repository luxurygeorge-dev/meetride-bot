# 🔧 Настройка Webhook'ов Bitrix24 для MeetRide

**Дата:** 7 октября 2025  
**Статус:** ✅ НАСТРОЕНО

---

## 📋 Что настроено

### ✅ Webhook #1: Уведомление в чат при переходе на 2-ю стадию

**Назначение:** При попадании сделки на стадию "Поиск водителя" (PREPARATION) автоматически отправляется уведомление в Telegram чат водителей с кнопками [Принять] [Отказаться]

**Настройки в Bitrix24:**
- **URL:** https://skysoft24.ru/meetRiedeBot/index.php
- **Событие:** ONCRMDEALUPDATE (При обновлении сделки)
- **Фильтр:** STAGE_ID = PREPARATION
- **Handler ID:** 3 (для избежания дублирования)

---

### ✅ Webhook #2: Откат заявки через 30 минут

**Назначение:** Если через 30 минут после перехода на стадию PREPARATION водитель не взял заявку, она автоматически откатывается обратно на стадию NEW

**Настройки в Bitrix24:**
- **URL:** https://skysoft24.ru/meetRiedeBot/index.php
- **Событие:** ONCRMDEALUPDATE или отложенная задача
- **Задержка:** 30 минут
- **Действие:** Если `ASSIGNED_BY_ID` пустой → `STAGE_ID` = NEW

---

## 🔄 Как это работает

### Сценарий 1: Водитель взял заявку (успех)
```
1. Менеджер → Стадия сделки = PREPARATION
2. Bitrix24 → Webhook → https://skysoft24.ru/meetRiedeBot/index.php
3. Бот → Отправка в Telegram группу с кнопками
4. Водитель → Нажимает [✅ Принять]
5. Бот → Стадия = PREPAYMENT_INVOICE
6. Webhook через 30 минут НЕ срабатывает (уже есть водитель)
```

### Сценарий 2: Никто не взял заявку (откат)
```
1. Менеджер → Стадия сделки = PREPARATION
2. Bitrix24 → Webhook → https://skysoft24.ru/meetRiedeBot/index.php
3. Бот → Отправка в Telegram группу с кнопками
4. Водители → Не реагируют (30 минут)
5. Bitrix24 → Webhook через 30 минут → проверка
6. Бот → Если нет водителя → Стадия = NEW
7. Менеджер → Видит что заявка вернулась, может изменить условия
```

---

## 🛠️ Технические детали

### Endpoint: https://skysoft24.ru/meetRiedeBot/index.php

**Что обрабатывает:**
1. ✅ События от Bitrix24 (ONCRMDEALUPDATE)
2. ✅ Callback кнопки от Telegram
3. ✅ Прямые вызовы с параметрами dealId/stage

**Логирование:**
- Файл: `/var/www/html/meetRiedeBot/logs/webhook_debug.log`
- Каждый запрос логируется с timestamp
- Видны все этапы обработки

**Проверка логов:**
```bash
tail -f /var/www/html/meetRiedeBot/logs/webhook_debug.log
```

---

## 📊 Стадии сделки

| ID | Название | Действие бота |
|----|----------|---------------|
| NEW | Новая | Ничего |
| PREPARATION | Поиск водителя | **→ Отправка в чат** 📨 |
| PREPAYMENT_INVOICE | Водитель принял | Детали в ЛС водителю |
| EXECUTING | Заявка выполняется | Отслеживание изменений |
| FINAL_INVOICE | Завершена | Ничего |

---

## 🔍 Как проверить что работает

### 1. Проверка endpoint
```bash
curl https://skysoft24.ru/meetRiedeBot/index.php
```
**Ожидаемый ответ:** `OK - Webhook received`

### 2. Тест с реальной заявкой
1. Создайте тестовую заявку в Bitrix24
2. Поставьте стадию "Поиск водителя" (PREPARATION)
3. Проверьте Telegram группу - должно прийти сообщение с кнопками
4. Проверьте логи:
```bash
tail -50 /var/www/html/meetRiedeBot/logs/webhook_debug.log
```

### 3. Проверка webhook'ов в Bitrix24
1. Перейдите: **Настройки → Разработчикам → Вебхуки**
2. Найдите исходящие webhook'и для ONCRMDEALUPDATE
3. Проверьте что URL = `https://skysoft24.ru/meetRiedeBot/index.php`
4. Проверьте статистику вызовов

---

## 🐛 Решение проблем

### Проблема: Сообщение не приходит в Telegram

**Проверьте:**
1. Логи webhook: `tail -f /var/www/html/meetRiedeBot/logs/webhook_debug.log`
2. Есть ли запись о получении события ONCRMDEALUPDATE?
3. Какая стадия у сделки? Должна быть PREPARATION
4. Правильный ли Handler ID? Должен быть 3

**Решение:**
```bash
# Ручная отправка заявки
cd /root/meetride
php send_deal.php <DEAL_ID>
```

### Проблема: Заявка не откатывается через 30 минут

**Проверьте:**
1. Настроен ли второй webhook с задержкой 30 минут?
2. Есть ли логи о вызове через 30 минут?
3. Проверьте поле ASSIGNED_BY_ID в сделке

**Решение:**
```bash
# Проверить состояние сделки
cd /root/meetride
php check_deal_status.php <DEAL_ID>
```

### Проблема: Дублирование сообщений

**Причина:** Несколько webhook'ов с одним событием

**Решение:**
1. В Bitrix24 → Вебхуки → найдите дубли ONCRMDEALUPDATE
2. Оставьте только один с Handler ID = 3
3. Или добавьте проверку в код (уже есть!)

---

## 📈 Мониторинг

### Команды для проверки
```bash
# Последние 50 строк логов
tail -50 /var/www/html/meetRiedeBot/logs/webhook_debug.log

# Следить за логами в реальном времени
tail -f /var/www/html/meetRiedeBot/logs/webhook_debug.log

# Проверить синтаксис PHP
php -l /var/www/html/meetRiedeBot/src/index.php

# Тест endpoint
curl https://skysoft24.ru/meetRiedeBot/index.php
```

### Telegram боты
- **Создание заявок:** 7992462078 (работает через polling)
- **Уведомления/кнопки:** 7529690360 (работает через webhook)

---

## ✅ Итоговый статус

**Что работает:**
- ✅ Webhook от Bitrix24 → Telegram чат
- ✅ Кнопки в Telegram → Bitrix24
- ✅ Отправка заявок на стадии PREPARATION
- ✅ Откат через 30 минут (если настроен второй webhook)
- ✅ Логирование всех событий
- ✅ Обработка ошибок

**Система готова к использованию!** 🚀

---

## 🔗 Полезные ссылки

- **Endpoint:** https://skysoft24.ru/meetRiedeBot/index.php
- **Логи:** `/var/www/html/meetRiedeBot/logs/webhook_debug.log`
- **Код:** `/var/www/html/meetRiedeBot/botManager.php`
- **Bitrix24:** https://meetride.bitrix24.ru/

---

**Документация актуальна на:** 7 октября 2025


