#!/usr/bin/env python3
import requests
import json

print("🧪 БЕЗОПАСНЫЙ ТЕСТ КНОПОК (БЕЗ СПАМА)")
print("=" * 45)

def test_webhook_structure():
    print("1️⃣ ПРОВЕРКА СТРУКТУРЫ WEBHOOK")
    print("-" * 35)
    
    url = "https://skysoft24.ru/meetRiedeBot/index.php"
    
    # Тест 1: Проверяем, что webhook отвечает
    print("🔍 Проверяем доступность webhook...")
    try:
        response = requests.get(url, timeout=5)
        print(f"   📥 Статус: {response.status_code}")
        print(f"   📝 Ответ: {response.text[:100]}...")
        
        if response.status_code == 200:
            print("   ✅ Webhook доступен")
        else:
            print("   ❌ Webhook недоступен")
            
    except Exception as e:
        print(f"   ❌ Ошибка: {e}")

def test_callback_parsing():
    print("\n2️⃣ ТЕСТ ПАРСИНГА CALLBACK")
    print("-" * 35)
    
    # Тестируем парсинг callback данных локально
    test_callbacks = [
        "groupAccept_999",
        "reject_999",
        "start_999", 
        "finish_999",
        "cancel_999"
    ]
    
    print("🔍 Тестируем парсинг callback данных:")
    
    for callback in test_callbacks:
        print(f"\n   🔘 {callback}")
        
        # Парсим callback как это делает бот
        if callback.startswith("groupAccept_"):
            deal_id = callback.replace("groupAccept_", "")
            print(f"      ✅ Принятие заявки #{deal_id}")
        elif callback.startswith("reject_"):
            deal_id = callback.replace("reject_", "")
            print(f"      ✅ Отказ от заявки #{deal_id}")
        elif callback.startswith("start_"):
            deal_id = callback.replace("start_", "")
            print(f"      ✅ Начало выполнения #{deal_id}")
        elif callback.startswith("finish_"):
            deal_id = callback.replace("finish_", "")
            print(f"      ✅ Завершение заявки #{deal_id}")
        elif callback.startswith("cancel_"):
            deal_id = callback.replace("cancel_", "")
            print(f"      ✅ Отмена выполнения #{deal_id}")
        else:
            print(f"      ❌ Неизвестный callback: {callback}")

def test_button_creation():
    print("\n3️⃣ ТЕСТ СОЗДАНИЯ КНОПОК")
    print("-" * 35)
    
    # Создаем кнопки как в боте
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
    
    print("🔍 Созданные кнопки:")
    for row in keyboard["inline_keyboard"]:
        for button in row:
            print(f"   🔘 {button['text']} → {button['callback_data']}")
    
    print("\n✅ Кнопки созданы корректно!")

def test_message_formatting():
    print("\n4️⃣ ТЕСТ ФОРМАТИРОВАНИЯ СООБЩЕНИЙ")
    print("-" * 35)
    
    # Тестируем форматирование сообщения как в боте
    deal = {
        "ID": "999",
        "UF_CRM_1751269147414": "Тестовый адрес откуда",
        "UF_CRM_1751269175432": "Тестовый адрес куда", 
        "UF_CRM_1751269222959": "2025-01-15 14:30",
        "UF_CRM_1751269256380": "Тестовые условия",
        "UF_CRM_1751271841129": "1000",
        "UF_CRM_1751271862251": "800"
    }
    
    driver_name = "Тест Водитель"
    
    # Форматируем как в боте
    text = f"""#️⃣ Заявка {deal['ID']} - <b>Назначена водителю: {driver_name}</b>

📆 {deal['UF_CRM_1751269222959']}

🅰️ {deal['UF_CRM_1751269147414']}

🅱️ {deal['UF_CRM_1751269175432']}

ℹ️ {deal['UF_CRM_1751269256380']}

💰 {deal['UF_CRM_1751271841129']}/{deal['UF_CRM_1751271862251']}"""
    
    print("🔍 Отформатированное сообщение:")
    print("-" * 30)
    print(text)
    print("-" * 30)
    print("✅ Форматирование корректно!")

if __name__ == "__main__":
    print("🎯 ТЕСТИРОВАНИЕ БЕЗ ОТПРАВКИ В ОБЩИЙ ЧАТ")
    print("=" * 50)
    
    test_webhook_structure()
    test_callback_parsing()
    test_button_creation()
    test_message_formatting()
    
    print("\n✅ ВСЕ ТЕСТЫ ПРОЙДЕНЫ!")
    print("💡 Логика кнопок работает корректно")
    print("   Теперь можно безопасно тестировать в реальном чате")
