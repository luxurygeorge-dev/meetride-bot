#!/bin/bash

# Скрипт для запуска бота РНП

echo "🚀 Запуск Telegram-бота РНП..."

# Проверяем наличие Python
if ! command -v python3 &> /dev/null; then
    echo "❌ Python3 не найден. Установите Python 3.7+"
    exit 1
fi

# Проверяем наличие файла бота
if [ ! -f "rnp_bot.py" ]; then
    echo "❌ Файл rnp_bot.py не найден"
    exit 1
fi

# Устанавливаем зависимости
echo "📦 Установка зависимостей..."
pip3 install -r requirements.txt

# Протестируем B24 интеграцию
echo "🔗 Тестирование интеграции с Bitrix24..."
python3 test_b24.py

# Запускаем бота
echo "🤖 Запуск бота..."
python3 rnp_bot.py
