#!/bin/bash

# Скрипт для настройки cron задачи для отправки напоминаний

# Получаем абсолютный путь к директории скрипта
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REMINDER_SCRIPT="$SCRIPT_DIR/reminder_scheduler.php"

# Проверяем существование скрипта
if [ ! -f "$REMINDER_SCRIPT" ]; then
    echo "Ошибка: Скрипт $REMINDER_SCRIPT не найден!"
    exit 1
fi

# Делаем скрипт исполняемым
chmod +x "$REMINDER_SCRIPT"

# Создаем временный файл для cron
TEMP_CRON=$(mktemp)

# Получаем текущие cron задачи
crontab -l > "$TEMP_CRON" 2>/dev/null || true

# Проверяем, есть ли уже наша задача
if grep -q "reminder_scheduler.php" "$TEMP_CRON"; then
    echo "Cron задача для reminder_scheduler.php уже существует!"
    echo "Текущие cron задачи:"
    crontab -l
    rm "$TEMP_CRON"
    exit 0
fi

# Добавляем новую задачу (каждые 5 минут)
echo "# Отправка напоминаний водителям каждые 5 минут" >> "$TEMP_CRON"
echo "*/5 * * * * php $REMINDER_SCRIPT >> $SCRIPT_DIR/logs/cron.log 2>&1" >> "$TEMP_CRON"

# Устанавливаем новые cron задачи
crontab "$TEMP_CRON"

# Проверяем результат
if [ $? -eq 0 ]; then
    echo "Cron задача успешно добавлена!"
    echo "Скрипт будет запускаться каждые 5 минут"
    echo ""
    echo "Текущие cron задачи:"
    crontab -l
else
    echo "Ошибка при добавлении cron задачи!"
    exit 1
fi

# Удаляем временный файл
rm "$TEMP_CRON"

echo ""
echo "Для проверки работы скрипта выполните:"
echo "php $REMINDER_SCRIPT"
echo ""
echo "Для просмотра логов:"
echo "tail -f $SCRIPT_DIR/logs/reminder_scheduler.log"
echo "tail -f $SCRIPT_DIR/logs/cron.log"







