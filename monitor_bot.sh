#!/bin/bash

echo "📊 Мониторинг бота РНП"
echo "======================"

# Статус сервиса
echo "🔍 Статус сервиса:"
if systemctl is-active rnp-bot >/dev/null 2>&1; then
    echo "✅ Сервис активен"
else
    echo "❌ Сервис неактивен"
fi

# Использование памяти
echo "💾 Память:"
MEMORY=$(systemctl show rnp-bot --property=MemoryCurrent --value 2>/dev/null)
if [ -n "$MEMORY" ] && [ "$MEMORY" != "0" ]; then
    echo "   Используется: $MEMORY байт"
else
    echo "   Информация недоступна"
fi

# Время работы
echo "⏱️ Время работы:"
UPTIME=$(systemctl show rnp-bot --property=ActiveEnterTimestamp --value 2>/dev/null)
if [ -n "$UPTIME" ]; then
    echo "   Запущен: $UPTIME"
else
    echo "   Информация недоступна"
fi

# Последние логи
echo "📋 Последние логи:"
journalctl -u rnp-bot -n 5 --no-pager 2>/dev/null || echo "   Логи недоступны"

echo ""
echo "🎮 Управление:"
echo "   Статус: ./install_service.sh status"
echo "   Логи: ./install_service.sh logs"
echo "   Перезапуск: ./install_service.sh restart"
