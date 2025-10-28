#!/bin/bash

# MeetRide Bot Environment Switcher
# Скрипт для переключения между test и production режимами

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Пути к файлам
BOT_MANAGER="/var/www/html/meetRiedeBot/botManager.php"
BACKUP_MANAGER="/root/meetride/botManager.php"

# Chat IDs
PRODUCTION_CHAT_ID="-1002544521661"
TEST_CHAT_ID="-1001649190984"

echo -e "${BLUE}🔄 MeetRide Bot Environment Switcher${NC}"
echo "====================================="

# Проверка аргументов
if [ $# -eq 0 ]; then
    echo -e "${RED}❌ Ошибка: Укажите среду (production или test)${NC}"
    echo "Использование: $0 [production|test]"
    exit 1
fi

ENVIRONMENT=$1

# Проверка корректности среды
if [ "$ENVIRONMENT" != "production" ] && [ "$ENVIRONMENT" != "test" ]; then
    echo -e "${RED}❌ Ошибка: Неверная среда. Используйте 'production' или 'test'${NC}"
    exit 1
fi

echo -e "${YELLOW}📋 Переключение на режим: $ENVIRONMENT${NC}"

# Выбор Chat ID
if [ "$ENVIRONMENT" = "production" ]; then
    CHAT_ID=$PRODUCTION_CHAT_ID
    ENV_NAME="БОЕВОЙ"
    EMOJI="🚀"
else
    CHAT_ID=$TEST_CHAT_ID
    ENV_NAME="ТЕСТОВЫЙ"
    EMOJI="🧪"
fi

echo -e "${YELLOW}📝 Chat ID: $CHAT_ID${NC}"

# Переключение основного файла
if [ -f "$BOT_MANAGER" ]; then
    echo -e "${YELLOW}📝 Изменяем $BOT_MANAGER...${NC}"
    sed -i "s/DRIVERS_GROUP_CHAT_ID.*=.*-[0-9]*/DRIVERS_GROUP_CHAT_ID = '$CHAT_ID'; \/\/ $ENV_NAME режим/" "$BOT_MANAGER"
    echo -e "${GREEN}✅ $BOT_MANAGER обновлен${NC}"
else
    echo -e "${RED}❌ Файл $BOT_MANAGER не найден!${NC}"
fi

# Переключение backup файла
if [ -f "$BACKUP_MANAGER" ]; then
    echo -e "${YELLOW}📝 Изменяем $BACKUP_MANAGER...${NC}"
    sed -i "s/DRIVERS_GROUP_CHAT_ID.*=.*-[0-9]*/DRIVERS_GROUP_CHAT_ID = '$CHAT_ID'; \/\/ $ENV_NAME режим/" "$BACKUP_MANAGER"
    echo -e "${GREEN}✅ $BACKUP_MANAGER обновлен${NC}"
else
    echo -e "${RED}❌ Файл $BACKUP_MANAGER не найден!${NC}"
fi

echo -e "${GREEN}🎉 ПЕРЕКЛЮЧЕНИЕ ЗАВЕРШЕНО!${NC}"
echo -e "${BLUE}🤖 Бот теперь работает в $ENV_NAME режиме $EMOJI${NC}"
echo ""
echo -e "${BLUE}==================================================${NC}"
echo -e "${BLUE}📊 ТЕКУЩЕЕ СОСТОЯНИЕ ЧАТОВ${NC}"
echo -e "${BLUE}==================================================${NC}"

# Показ текущего состояния
if [ -f "$BOT_MANAGER" ]; then
    CURRENT_CHAT=$(grep "DRIVERS_GROUP_CHAT_ID" "$BOT_MANAGER" | head -1)
    if [[ $CURRENT_CHAT == *"$PRODUCTION_CHAT_ID"* ]]; then
        echo -e "${GREEN}🟢 $BOT_MANAGER: БОЕВОЙ ($PRODUCTION_CHAT_ID)${NC}"
    else
        echo -e "${YELLOW}🟡 $BOT_MANAGER: ТЕСТОВЫЙ ($TEST_CHAT_ID)${NC}"
    fi
fi

if [ -f "$BACKUP_MANAGER" ]; then
    CURRENT_CHAT=$(grep "DRIVERS_GROUP_CHAT_ID" "$BACKUP_MANAGER" | head -1)
    if [[ $CURRENT_CHAT == *"$PRODUCTION_CHAT_ID"* ]]; then
        echo -e "${GREEN}🟢 $BACKUP_MANAGER: БОЕВОЙ ($PRODUCTION_CHAT_ID)${NC}"
    else
        echo -e "${YELLOW}🟡 $BACKUP_MANAGER: ТЕСТОВЫЙ ($TEST_CHAT_ID)${NC}"
    fi
fi

