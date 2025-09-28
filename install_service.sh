#!/bin/bash

# Скрипт установки systemd сервиса для бота РНП
# Использование: ./install_service.sh [install|start|stop|restart|status|enable|disable|logs]

SERVICE_NAME="rnp-bot"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "🤖 Управление сервисом бота РНП"
echo "================================"

case "$1" in
    "install")
        echo "📦 Установка сервиса..."
        
        # Проверяем существование файлов
        if [ ! -f "${SCRIPT_DIR}/rnp_bot.py" ]; then
            echo "❌ Ошибка: Файл rnp_bot.py не найден в ${SCRIPT_DIR}"
            exit 1
        fi
        
        if [ ! -f "${SCRIPT_DIR}/rnp-bot.service" ]; then
            echo "❌ Ошибка: Файл rnp-bot.service не найден в ${SCRIPT_DIR}"
            exit 1
        fi
        
        # Копируем сервис файл
        cp "${SCRIPT_DIR}/rnp-bot.service" "${SERVICE_FILE}"
        
        # Обновляем путь к скрипту в сервис файле
        sed -i "s|ExecStart=/usr/bin/python3 /root/rnp_bot.py|ExecStart=/usr/bin/python3 ${SCRIPT_DIR}/rnp_bot.py|g" "${SERVICE_FILE}"
        sed -i "s|WorkingDirectory=/root|WorkingDirectory=${SCRIPT_DIR}|g" "${SERVICE_FILE}"
        
        # Перезагружаем systemd
        systemctl daemon-reload
        
        echo "✅ Сервис установлен: ${SERVICE_FILE}"
        echo "📋 Для запуска выполните: ./install_service.sh start"
        echo "📋 Для автозапуска выполните: ./install_service.sh enable"
        ;;
        
    "start")
        echo "🚀 Запуск сервиса..."
        systemctl start "${SERVICE_NAME}"
        if [ $? -eq 0 ]; then
            echo "✅ Сервис запущен"
            systemctl status "${SERVICE_NAME}" --no-pager -l
        else
            echo "❌ Ошибка запуска сервиса"
            exit 1
        fi
        ;;
        
    "stop")
        echo "🛑 Остановка сервиса..."
        systemctl stop "${SERVICE_NAME}"
        if [ $? -eq 0 ]; then
            echo "✅ Сервис остановлен"
        else
            echo "❌ Ошибка остановки сервиса"
            exit 1
        fi
        ;;
        
    "restart")
        echo "🔄 Перезапуск сервиса..."
        systemctl restart "${SERVICE_NAME}"
        if [ $? -eq 0 ]; then
            echo "✅ Сервис перезапущен"
            systemctl status "${SERVICE_NAME}" --no-pager -l
        else
            echo "❌ Ошибка перезапуска сервиса"
            exit 1
        fi
        ;;
        
    "status")
        echo "📊 Статус сервиса..."
        systemctl status "${SERVICE_NAME}" --no-pager -l
        ;;
        
    "enable")
        echo "🔗 Включение автозапуска..."
        systemctl enable "${SERVICE_NAME}"
        if [ $? -eq 0 ]; then
            echo "✅ Автозапуск включен"
        else
            echo "❌ Ошибка включения автозапуска"
            exit 1
        fi
        ;;
        
    "disable")
        echo "🔓 Отключение автозапуска..."
        systemctl disable "${SERVICE_NAME}"
        if [ $? -eq 0 ]; then
            echo "✅ Автозапуск отключен"
        else
            echo "❌ Ошибка отключения автозапуска"
            exit 1
        fi
        ;;
        
    "logs")
        echo "📋 Логи сервиса..."
        journalctl -u "${SERVICE_NAME}" -f --no-pager
        ;;
        
    "uninstall")
        echo "🗑️ Удаление сервиса..."
        systemctl stop "${SERVICE_NAME}" 2>/dev/null
        systemctl disable "${SERVICE_NAME}" 2>/dev/null
        rm -f "${SERVICE_FILE}"
        systemctl daemon-reload
        echo "✅ Сервис удален"
        ;;
        
    *)
        echo "❓ Использование: $0 [install|start|stop|restart|status|enable|disable|logs|uninstall]"
        echo ""
        echo "Команды:"
        echo "  install   - Установить сервис"
        echo "  start     - Запустить сервис"
        echo "  stop      - Остановить сервис"
        echo "  restart   - Перезапустить сервис"
        echo "  status    - Показать статус"
        echo "  enable    - Включить автозапуск"
        echo "  disable   - Отключить автозапуск"
        echo "  logs      - Показать логи (следить)"
        echo "  uninstall - Удалить сервис"
        echo ""
        echo "Примеры:"
        echo "  ./install_service.sh install"
        echo "  ./install_service.sh start"
        echo "  ./install_service.sh enable"
        echo "  ./install_service.sh logs"
        exit 1
        ;;
esac
