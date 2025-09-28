#!/bin/bash

# Безопасный деплой MeetRide Bot
# Использование: ./scripts/deploy-safe.sh [environment]

set -e

ENVIRONMENT=${1:-staging}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "🚀 Deploying MeetRide Bot to $ENVIRONMENT..."

# Проверка ветки
CURRENT_BRANCH=$(git branch --show-current)
echo "📋 Current branch: $CURRENT_BRANCH"

# Проверка статуса Git
if ! git diff-index --quiet HEAD --; then
    echo "❌ Error: Uncommitted changes detected!"
    echo "Please commit or stash your changes before deploying."
    exit 1
fi

# Проверка синтаксиса PHP
echo "🔍 Checking PHP syntax..."
php -l "$PROJECT_DIR/src/botManager.php"
php -l "$PROJECT_DIR/src/index.php"

# Проверка конфигурации
if [ ! -f "$PROJECT_DIR/config/config.php" ]; then
    echo "❌ Error: config/config.php not found!"
    echo "Please create configuration file before deploying."
    exit 1
fi

# Создание бэкапа
echo "💾 Creating backup..."
BACKUP_DIR="/var/backups/meetride-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

if [ -d "/var/www/html/meetRiedeBot" ]; then
    cp -r /var/www/html/meetRiedeBot/* "$BACKUP_DIR/"
    echo "✅ Backup created: $BACKUP_DIR"
fi

# Деплой в зависимости от окружения
case $ENVIRONMENT in
    "staging")
        echo "🧪 Deploying to STAGING..."
        # Копируем в тестовую директорию
        sudo cp -r "$PROJECT_DIR/src/"* /var/www/html/meetRiedeBot/
        sudo cp "$PROJECT_DIR/config/config.php" /var/www/html/meetRiedeBot/
        echo "✅ Staging deployment complete!"
        ;;
    "production")
        echo "🏭 Deploying to PRODUCTION..."
        # Дополнительные проверки для продакшна
        read -p "Are you sure you want to deploy to PRODUCTION? (yes/no): " confirm
        if [ "$confirm" != "yes" ]; then
            echo "❌ Production deployment cancelled."
            exit 1
        fi
        
        # Копируем в продакшн
        sudo cp -r "$PROJECT_DIR/src/"* /var/www/html/meetRiedeBot/
        sudo cp "$PROJECT_DIR/config/config.php" /var/www/html/meetRiedeBot/
        echo "✅ Production deployment complete!"
        ;;
    *)
        echo "❌ Unknown environment: $ENVIRONMENT"
        echo "Usage: $0 [staging|production]"
        exit 1
        ;;
esac

# Проверка после деплоя
echo "🔍 Post-deployment checks..."
if [ -f "/var/www/html/meetRiedeBot/botManager.php" ]; then
    echo "✅ botManager.php deployed successfully"
else
    echo "❌ botManager.php deployment failed"
    exit 1
fi

echo "🎉 Deployment to $ENVIRONMENT completed successfully!"
echo "📊 Backup location: $BACKUP_DIR"
