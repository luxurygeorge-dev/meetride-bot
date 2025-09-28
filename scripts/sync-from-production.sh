#!/bin/bash

# Синхронизация с боевого сервера
# Использование: ./scripts/sync-from-production.sh

set -e

PRODUCTION_SERVER="188.225.24.13"
PRODUCTION_PATH="/var/www/html/meetRiedeBot"
LOCAL_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "🔄 Syncing from production server..."

# Проверка SSH ключа
if [ ! -f ~/.ssh/id_rsa ]; then
    echo "❌ SSH key not found. Please set up SSH access first:"
    echo "ssh-keygen -t rsa -b 4096 -C 'your_email@example.com'"
    echo "ssh-copy-id root@$PRODUCTION_SERVER"
    exit 1
fi

# Создание бэкапа текущей версии
echo "💾 Creating backup of current version..."
BACKUP_DIR="backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -r src/ "$BACKUP_DIR/" 2>/dev/null || true

# Синхронизация файлов
echo "📥 Downloading from production..."
rsync -avz --delete root@$PRODUCTION_SERVER:$PRODUCTION_PATH/ src/

# Проверка синтаксиса
echo "🔍 Checking PHP syntax..."
php -l src/botManager.php
php -l src/index.php

# Коммит изменений
echo "📝 Committing changes..."
git add src/
git commit -m "Sync from production server $(date '+%Y-%m-%d %H:%M:%S')" || echo "No changes to commit"

echo "✅ Sync completed!"
echo "📊 Backup saved in: $BACKUP_DIR"
