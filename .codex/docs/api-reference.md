# API Reference - MeetRide Bot

## 🤖 Telegram Bot API

### Основные методы
```php
// Отправка сообщения
$telegram->sendMessage([
    'chat_id' => $chatId,
    'text' => $message,
    'reply_markup' => $keyboard
]);

// Редактирование сообщения
$telegram->editMessageReplyMarkup([
    'chat_id' => $chatId,
    'message_id' => $messageId,
    'reply_markup' => $newKeyboard
]);

// Ответ на callback
$telegram->answerCallbackQuery([
    'callback_query_id' => $callbackId,
    'text' => $text
]);
```

## 🏢 Bitrix24 REST API

### Сделки
```php
// Получить сделку
CRest::call('crm.deal.get', ['id' => $dealId]);

// Обновить сделку
CRest::call('crm.deal.update', [
    'id' => $dealId,
    'fields' => [
        'STAGE_ID' => $stageId,
        'ASSIGNED_BY_ID' => $driverId
    ]
]);

// Список сделок
CRest::call('crm.deal.list', [
    'filter' => ['STAGE_ID' => $stageId],
    'select' => ['ID', 'TITLE', 'STAGE_ID']
]);
```

### Контакты
```php
// Найти контакт по Telegram ID
CRest::call('crm.contact.list', [
    'filter' => ['UF_CRM_1751185017761' => $telegramId],
    'select' => ['ID', 'NAME', 'LAST_NAME']
]);

// Получить контакт
CRest::call('crm.contact.get', ['id' => $contactId]);
```

### Уведомления
```php
// Системное уведомление
CRest::call('im.notify.system.add', [
    'USER_ID' => $userId,
    'MESSAGE' => $message
]);
```

## 🔧 Основные функции

### `buttonHandle($callbackData)`
Обработка нажатий кнопок
- `accept_deal_123` - принять заявку
- `start_travel_123` - начать выполнение
- `reject_deal_123` - отказаться

### `dealChangeHandle($dealId)`
Обработка изменений заявки
- Проверка изменений полей
- Отправка уведомлений водителю

### `orderTextForDriver($deal)`
Форматирование сообщения для водителя
- Включает пассажиров и телефоны
- Показывает все детали заявки

### `orderTextForGroup($deal)`
Форматирование сообщения для группы
- Скрывает пассажиров и телефоны
- Показывает только основную информацию

## 📊 Стадии сделок
- `PREPARATION` - Подготовка
- `PREPAYMENT_INVOICE` - Предоплата
- `EXECUTING` - Выполнение
- `FINAL_INVOICE` - Финальный счет

## 🔍 Поля сделки
- `TITLE` - Название заявки
- `STAGE_ID` - Стадия сделки
- `ASSIGNED_BY_ID` - Назначенный водитель
- `UF_CRM_1751271798896` - Пассажиры
- `UF_CRM_1751185017761` - Telegram ID водителя
- `UF_CRM_1751271841129` - Скрытое поле
