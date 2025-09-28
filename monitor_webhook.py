#!/usr/bin/env python3
import time
import os

print("👀 МОНИТОРИНГ ЛОГОВ WEBHOOK")
print("=" * 40)

def monitor_logs():
    log_file = '/var/www/html/meetRiedeBot/logs/webhook_debug.log'
    
    print(f"📁 Отслеживаем файл: {log_file}")
    print("⏰ Нажмите Ctrl+C для остановки")
    print("-" * 40)
    
    try:
        # Проверяем существование файла
        if not os.path.exists(log_file):
            print(f"❌ Файл {log_file} не найден!")
            print("💡 Создайте файл или проверьте путь")
            return
        
        # Читаем последние строки
        with open(log_file, 'r') as f:
            lines = f.readlines()
            print(f"📊 Всего строк в логе: {len(lines)}")
            
            if lines:
                print("\n📋 Последние 5 записей:")
                for line in lines[-5:]:
                    print(f"   {line.strip()}")
            else:
                print("📝 Лог пустой")
        
        print("\n🔄 Начинаем мониторинг...")
        print("   (Отправьте тестовое сообщение и нажмите кнопки)")
        
        # Мониторим изменения файла
        last_size = os.path.getsize(log_file)
        
        while True:
            time.sleep(1)
            
            current_size = os.path.getsize(log_file)
            
            if current_size > last_size:
                print(f"\n🆕 НОВАЯ ЗАПИСЬ В ЛОГЕ ({time.strftime('%H:%M:%S')}):")
                
                with open(log_file, 'r') as f:
                    lines = f.readlines()
                    new_lines = lines[last_size:]
                    
                    for line in new_lines:
                        print(f"   {line.strip()}")
                
                last_size = current_size
                
    except KeyboardInterrupt:
        print("\n\n⏹️  Мониторинг остановлен")
    except Exception as e:
        print(f"\n❌ Ошибка: {e}")

if __name__ == "__main__":
    monitor_logs()
