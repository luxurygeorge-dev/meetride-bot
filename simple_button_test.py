#!/usr/bin/env python3
import requests

print("🧪 ПРОСТОЙ ТЕСТ КНОПОК")
print("=" * 30)

# Тест создания заявки (должен отправить в общий чат)
def test_deal_creation():
    print("1️⃣ ТЕСТ СОЗДАНИЯ ЗАЯВКИ")
    print("-" * 25)
    
    url = "https://skysoft24.ru/meetRiedeBot/index.php"
    params = {
        "dealId": "999",
        "stage": "Назначение водителя",
        "commonDel": False,
        "changed": False
    }
    
    print(f"📤 Отправляем: {params}")
    
    try:
        response = requests.get(url, params=params, timeout=10)
        print(f"📥 Статус: {response.status_code}")
        print(f"📝 Ответ: {response.text[:200]}...")
        
        if response.status_code == 200:
            print("✅ Заявка должна появиться в общем чате!")
        else:
            print("❌ Ошибка при создании заявки")
            
    except Exception as e:
        print(f"❌ Ошибка: {e}")

# Тест callback кнопок (симулируем нажатие)
def test_callback_buttons():
    print("\n2️⃣ ТЕСТ CALLBACK КНОПОК")
    print("-" * 25)
    
    # Симулируем разные типы callback
    callbacks = [
        "groupAccept_999",
        "reject_999", 
        "start_999",
        "finish_999",
        "cancel_999"
    ]
    
    for callback in callbacks:
        print(f"\n🔘 Тестируем: {callback}")
        
        # Создаем простой GET запрос с callback данными
        url = "https://skysoft24.ru/meetRiedeBot/index.php"
        params = {
            "callback_query": callback,
            "test": "true"
        }
        
        try:
            response = requests.get(url, params=params, timeout=5)
            print(f"   📥 Статус: {response.status_code}")
            
            if response.status_code == 200:
                print("   ✅ Обработано успешно")
            else:
                print("   ❌ Ошибка обработки")
                
        except Exception as e:
            print(f"   ❌ Ошибка: {e}")

if __name__ == "__main__":
    print("⚠️  ВНИМАНИЕ: Этот тест отправит сообщение в общий чат!")
    print("   Если не хотите спамить, остановите выполнение (Ctrl+C)")
    print()
    
    response = input("Продолжить тестирование? (y/N): ")
    
    if response.lower() == 'y':
        test_deal_creation()
        test_callback_buttons()
        print("\n✅ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!")
    else:
        print("❌ Тестирование отменено")
