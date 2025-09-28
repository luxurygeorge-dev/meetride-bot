#!/bin/bash

# Синхронизация на боевой сервер
# Использование: ./scripts/sync-to-production.sh [environment]

set -e

ENVIRONMENT=${1:-staging}
PRODUCTION_SERVER="188.225.24.13"
PRODUCTION_PATH="/var/www/html/meetRiedeBot"
LOCAL_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "🚀 Syncing to production server ($ENVIRONMENT)..."

# Проверка SSH ключа
if [ ! -f ~/.ssh/id_rsa ]; then
    echo "❌ SSH key not found. Please set up SSH access first:"
    echo "ssh-keygen -t rsa -b 4096 -C 'your_email@example.com'"
    echo "ssh-copy-id root@$PRODUCTION_SERVER"
    exit 1
fi

# Проверка синтаксиса
echo "🔍 Checking PHP syntax..."
php -l src/botManager.php
php -l src/index.php

# Создание бэкапа на сервере
echo "💾 Creating backup on production server..."
ssh root@$PRODUCTION_SERVER "mkdir -p /var/backups/meetride-$(date +%Y%m%d-%H%M%S) && cp -r $PRODUCTION_PATH/* /var/backups/meetride-$(date +%Y%m%d-%H%M%S)/"

# Синхронизация файлов
echo "📤 Uploading to production..."
rsync -avz --delete src/ root@$PRODUCTION_SERVER:$PRODUCTION_PATH/

# Проверка после синхронизации
echo "🔍 Post-sync verification..."
ssh root@$PRODUCTION_SERVER "php -l $PRODUCTION_PATH/botManager.php && php -l $PRODUCTION_PATH/index.php"

echo "✅ Sync to production completed!"
