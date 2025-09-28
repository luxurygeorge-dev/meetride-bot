# Примеры Webhook'ов

## 📨 Bitrix24 → Bot

### Создание заявки
```json
{
    "event": "ONCRMDEALUPDATE",
    "data": {
        "FIELDS": {
            "ID": "123",
            "TITLE": "Заявка #123",
            "STAGE_ID": "PREPARATION",
            "UF_CRM_1751271798896": "Иван Петров (79001234567), Мария Сидорова (79007654321)",
            "UF_CRM_1751185017761": "",
            "ASSIGNED_BY_ID": "1"
        }
    }
}
```

### Изменение заявки
```json
{
    "event": "ONCRMDEALUPDATE", 
    "data": {
        "FIELDS": {
            "ID": "123",
            "STAGE_ID": "PREPAYMENT_INVOICE",
            "UF_CRM_1751271798896": "Иван Петров (79001234567), Мария Сидорова (79007654321), Петр Иванов (79009876543)"
        }
    }
}
```

## 🤖 Telegram → Bot

### Callback от кнопки "Принять"
```json
{
    "update_id": 123456789,
    "callback_query": {
        "id": "123456789",
        "from": {
            "id": 123456789,
            "first_name": "Иван",
            "last_name": "Петров",
            "username": "ivan_petrov"
        },
        "message": {
            "message_id": 123,
            "chat": {
                "id": -1001234567890,
                "title": "Водители MeetRide"
            }
        },
        "data": "accept_deal_123"
    }
}
```

### Callback от кнопки "Начать выполнение"
```json
{
    "callback_query": {
        "data": "start_travel_123"
    }
}
```

## 📱 Ответы бота

### Сообщение в группу
```json
{
    "chat_id": -1001234567890,
    "text": "🚗 Ваша заявка #123\n📆 Дата: 19.12.2025 11:50\n🅰️ Откуда: Аэропорт\n🅱️ Куда: Центр города\n💰 Сумма: 5000 руб.",
    "reply_markup": {
        "inline_keyboard": [[
            {"text": "✅ Принять", "callback_data": "accept_deal_123"}
        ]]
    }
}
```

### Сообщение водителю в личку
```json
{
    "chat_id": 123456789,
    "text": "🚗 Ваша заявка #123\n📆 Дата: 19.12.2025 11:50\n🅰️ Откуда: Аэропорт\n🅱️ Куда: Центр города\n👥 Пассажиры: Иван Петров (79001234567), Мария Сидорова (79007654321)\n💰 Сумма: 5000 руб.",
    "reply_markup": {
        "inline_keyboard": [
            [{"text": "🚀 Начать выполнение", "callback_data": "start_travel_123"}],
            [{"text": "❌ Отказаться", "callback_data": "reject_deal_123"}]
        ]
    }
}
```
