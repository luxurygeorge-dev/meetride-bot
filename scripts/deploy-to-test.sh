#!/bin/bash

# Деплой в тестовую среду
# Использование: ./scripts/deploy-to-test.sh

set -e

TEST_DIR="/var/www/html/meetRiedeBot-test"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "🧪 Deploying to test environment..."

# Проверка синтаксиса
echo "🔍 Checking PHP syntax..."
php -l src/botManager.php
php -l src/index.php

# Создание бэкапа тестовой среды
echo "💾 Creating test environment backup..."
if [ -d "$TEST_DIR" ]; then
    sudo cp -r "$TEST_DIR" "${TEST_DIR}-backup-$(date +%Y%m%d-%H%M%S)"
fi

# Копирование файлов
echo "📋 Copying files to test environment..."
sudo cp -r src/* "$TEST_DIR/"

# Проверка после деплоя
echo "🔍 Post-deployment verification..."
sudo php -l "$TEST_DIR/botManager.php"
sudo php -l "$TEST_DIR/index.php"

echo "✅ Test deployment complete!"
echo "🌐 Test URL: https://your-domain.com/meetRiedeBot-test/"
echo "🔧 Test script: https://your-domain.com/meetRiedeBot-test/test.php"
