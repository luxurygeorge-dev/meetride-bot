#!/usr/bin/env python3
import requests
import json

print("🧪 ТЕСТИРОВАНИЕ КНОПОК БОТА")
print("=" * 40)

# Токен бота для напоминаний (который обрабатывает кнопки)
BOT_TOKEN = "7529690360:AAHED5aKmuKjjfFQPRI-0RQ8DlxlZARA2O4"

# ID вашего личного чата (замените на свой)
YOUR_CHAT_ID = "YOUR_PERSONAL_CHAT_ID"  # Замените на ваш реальный ID

def test_buttons():
    print("1️⃣ ОТПРАВКА ТЕСТОВОГО СООБЩЕНИЯ С КНОПКАМИ")
    print("-" * 40)
    
    # Создаем тестовое сообщение с кнопками
    keyboard = {
        "inline_keyboard": [
            [
                {"text": "✅ Принять", "callback_data": "groupAccept_999"},
                {"text": "❌ Отказаться", "callback_data": "reject_999"}
            ],
            [
                {"text": "✅ Начать выполнение", "callback_data": "start_999"},
                {"text": "❌ Отказаться", "callback_data": "reject_999"}
            ],
            [
                {"text": "🏁 Заявка выполнена", "callback_data": "finish_999"},
                {"text": "❌ Отменить выполнение", "callback_data": "cancel_999"}
            ]
        ]
    }
    
    message_text = """🧪 ТЕСТ КНОПОК БОТА

#️⃣ Заявка 999 - ТЕСТ
📆 2025-01-15 14:30
🅰️ Тестовый адрес откуда
🅱️ Тестовый адрес куда
ℹ️ Тестовые условия
💰 1000/800

Нажмите любую кнопку для тестирования!"""
    
    url = f"https://api.telegram.org/bot{BOT_TOKEN}/sendMessage"
    
    data = {
        "chat_id": YOUR_CHAT_ID,
        "text": message_text,
        "reply_markup": keyboard,
        "parse_mode": "HTML"
    }
    
    print(f"📤 Отправляем тестовое сообщение в чат: {YOUR_CHAT_ID}")
    print(f"🔗 URL: {url}")
    
    response = requests.post(url, json=data)
    
    if response.status_code == 200:
        print("✅ Сообщение отправлено успешно!")
        print("🎯 Теперь нажмите кнопки и проверьте логи webhook")
    else:
        print(f"❌ Ошибка: {response.status_code}")
        print(f"📝 Ответ: {response.text}")

def check_webhook_logs():
    print("\n2️⃣ ПРОВЕРКА ЛОГОВ WEBHOOK")
    print("-" * 40)
    
    try:
        with open('/var/www/html/meetRiedeBot/logs/webhook_debug.log', 'r') as f:
            lines = f.readlines()
            last_lines = lines[-10:]  # Последние 10 строк
            
            print("📋 Последние записи в логе:")
            for line in last_lines:
                print(f"   {line.strip()}")
                
    except FileNotFoundError:
        print("❌ Файл логов не найден")
    except Exception as e:
        print(f"❌ Ошибка чтения логов: {e}")

def test_callback_handling():
    print("\n3️⃣ ТЕСТ ОБРАБОТКИ CALLBACK")
    print("-" * 40)
    
    # Симулируем callback данные
    test_callbacks = [
        "groupAccept_999",
        "reject_999", 
        "start_999",
        "finish_999",
        "cancel_999"
    ]
    
    print("🔍 Тестируемые callback данные:")
    for callback in test_callbacks:
        print(f"   - {callback}")
    
    print("\n💡 Для полного теста:")
    print("   1. Замените YOUR_PERSONAL_CHAT_ID на ваш реальный ID")
    print("   2. Запустите скрипт")
    print("   3. Нажмите кнопки в личном чате")
    print("   4. Проверьте логи webhook")

if __name__ == "__main__":
    print("⚠️  ВНИМАНИЕ: Замените YOUR_PERSONAL_CHAT_ID на ваш реальный ID чата!")
    print("   Получить ID можно через @userinfobot в Telegram")
    print()
    
    if YOUR_CHAT_ID == "YOUR_PERSONAL_CHAT_ID":
        print("❌ Сначала замените YOUR_PERSONAL_CHAT_ID на ваш реальный ID!")
        print("   Откройте файл и измените строку 8")
    else:
        test_buttons()
        check_webhook_logs()
        test_callback_handling()
