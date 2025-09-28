#!/bin/bash

# MeetRide Bot Deployment Script
# Скрипт для безопасного деплоя бота

set -e  # Остановка при ошибке

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 MeetRide Bot Deployment Script${NC}"
echo "=================================="

# Проверка аргументов
if [ $# -eq 0 ]; then
    echo -e "${RED}❌ Ошибка: Укажите среду (production или test)${NC}"
    echo "Использование: $0 [production|test]"
    exit 1
fi

ENVIRONMENT=$1
WEB_ROOT="/var/www/html/meetRiedeBot"
BACKUP_DIR="/var/backups/meetride-bot"

echo -e "${YELLOW}📋 Настройки деплоя:${NC}"
echo "  Среда: $ENVIRONMENT"
echo "  Путь: $WEB_ROOT"
echo "  Резервные копии: $BACKUP_DIR"

# Создание директории для бэкапов
mkdir -p $BACKUP_DIR

# Создание бэкапа текущей версии
BACKUP_NAME="backup-$(date +%Y%m%d-%H%M%S)"
echo -e "${YELLOW}💾 Создание резервной копии...${NC}"
tar -czf "$BACKUP_DIR/$BACKUP_NAME.tar.gz" -C /var/www/html meetRiedeBot
echo -e "${GREEN}✅ Резервная копия создана: $BACKUP_NAME.tar.gz${NC}"

# Проверка конфигурации
echo -e "${YELLOW}🔍 Проверка конфигурации...${NC}"
if [ ! -f "config/config.php" ]; then
    echo -e "${RED}❌ Файл config/config.php не найден!${NC}"
    echo "Скопируйте config/config.example.php в config/config.php и настройте"
    exit 1
fi

# Копирование файлов
echo -e "${YELLOW}📁 Копирование файлов...${NC}"
cp -r src/* $WEB_ROOT/
cp -r config/config.php $WEB_ROOT/

# Установка прав доступа
echo -e "${YELLOW}🔐 Установка прав доступа...${NC}"
chown -R www-data:www-data $WEB_ROOT
chmod -R 755 $WEB_ROOT
chmod 644 $WEB_ROOT/config/config.php

# Перезапуск веб-сервера (если нужно)
echo -e "${YELLOW}🔄 Проверка веб-сервера...${NC}"
if systemctl is-active --quiet apache2; then
    echo -e "${GREEN}✅ Apache2 работает${NC}"
elif systemctl is-active --quiet nginx; then
    echo -e "${GREEN}✅ Nginx работает${NC}"
else
    echo -e "${YELLOW}⚠️  Веб-сервер не запущен или не Apache/Nginx${NC}"
fi

# Проверка логов
echo -e "${YELLOW}📊 Проверка логов...${NC}"
mkdir -p $WEB_ROOT/logs
chown www-data:www-data $WEB_ROOT/logs
chmod 755 $WEB_ROOT/logs

echo -e "${GREEN}🎉 Деплой завершен успешно!${NC}"
echo -e "${BLUE}📝 Следующие шаги:${NC}"
echo "  1. Проверьте работу бота в Telegram"
echo "  2. Проверьте webhook'и в Bitrix24"
echo "  3. Мониторьте логи: tail -f $WEB_ROOT/logs/bot.log"

if [ "$ENVIRONMENT" = "production" ]; then
    echo -e "${RED}⚠️  ВНИМАНИЕ: Деплой в PRODUCTION!${NC}"
    echo -e "${YELLOW}   Убедитесь, что все тесты пройдены!${NC}"
fi

