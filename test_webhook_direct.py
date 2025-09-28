#!/usr/bin/env python3
import requests
import json

print("🧪 ПРЯМОЕ ТЕСТИРОВАНИЕ WEBHOOK")
print("=" * 40)

# URL webhook
WEBHOOK_URL = "https://skysoft24.ru/meetRiedeBot/index.php"

def test_deal_creation():
    print("1️⃣ ТЕСТ СОЗДАНИЯ ЗАЯВКИ")
    print("-" * 30)
    
    # Симулируем webhook от Bitrix24
    test_data = {
        "dealId": "999",
        "stage": "Назначение водителя",
        "commonDel": False,
        "changed": False
    }
    
    print(f"📤 Отправляем тестовые данные: {test_data}")
    
    response = requests.get(WEBHOOK_URL, params=test_data)
    
    print(f"📥 Ответ сервера: {response.status_code}")
    print(f"📝 Содержимое: {response.text}")

def test_callback_queries():
    print("\n2️⃣ ТЕСТ CALLBACK QUERIES")
    print("-" * 30)
    
    # Симулируем callback query
    callback_data = {
        "update_id": 123456789,
        "callback_query": {
            "id": "test_callback_123",
            "from": {
                "id": 123456789,
                "first_name": "Test",
                "last_name": "User"
            },
            "message": {
                "message_id": 123,
                "chat": {
                    "id": -1002544521661,  # ID общего чата
                    "type": "supergroup"
                }
            },
            "data": "groupAccept_999"
        }
    }
    
    print(f"📤 Отправляем callback: {callback_data['callback_query']['data']}")
    
    # Отправляем GET запрос с callback данными
    response = requests.get(WEBHOOK_URL, params=callback_data)
    
    print(f"📥 Ответ сервера: {response.status_code}")
    print(f"📝 Содержимое: {response.text}")

def test_different_callbacks():
    print("\n3️⃣ ТЕСТ РАЗНЫХ ТИПОВ КНОПОК")
    print("-" * 30)
    
    callbacks = [
        "groupAccept_999",
        "reject_999",
        "start_999", 
        "finish_999",
        "cancel_999"
    ]
    
    for callback in callbacks:
        print(f"\n🔘 Тестируем: {callback}")
        
        callback_data = {
            "update_id": 123456789,
            "callback_query": {
                "id": f"test_{callback}",
                "from": {
                    "id": 123456789,
                    "first_name": "Test",
                    "last_name": "User"
                },
                "message": {
                    "message_id": 123,
                    "chat": {
                        "id": -1002544521661,
                        "type": "supergroup"
                    }
                },
                "data": callback
            }
        }
        
        response = requests.get(WEBHOOK_URL, params=callback_data)
        print(f"   📥 Ответ: {response.status_code}")

if __name__ == "__main__":
    print("🎯 ТЕСТИРОВАНИЕ БЕЗ СПАМА В ОБЩИЙ ЧАТ")
    print("=" * 50)
    
    test_deal_creation()
    test_callback_queries() 
    test_different_callbacks()
    
    print("\n✅ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!")
    print("💡 Проверьте логи webhook для деталей:")
    print("   tail -f /var/www/html/meetRiedeBot/logs/webhook_debug.log")
