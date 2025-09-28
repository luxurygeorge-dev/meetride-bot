#!/bin/bash

# Тестирование бота
# Использование: ./scripts/test-bot.sh

set -e

TEST_DIR="/var/www/html/meetRiedeBot-test"

echo "🧪 Testing MeetRide Bot..."

# Проверка доступности
echo "🌐 Checking test environment..."
if [ ! -d "$TEST_DIR" ]; then
    echo "❌ Test environment not found. Run setup first:"
    echo "./scripts/setup-test-environment.sh"
    exit 1
fi

# Проверка конфигурации
echo "⚙️ Checking configuration..."
if [ ! -f "$TEST_DIR/config.php" ]; then
    echo "❌ Test configuration not found"
    exit 1
fi

# Тест синтаксиса
echo "🔍 Syntax check..."
sudo php -l "$TEST_DIR/botManager.php"
sudo php -l "$TEST_DIR/index.php"

# Тест конфигурации
echo "🔧 Configuration test..."
sudo php -r "
require_once '$TEST_DIR/config.php';
echo 'Bot Token: ' . (defined('BOT_TOKEN') ? 'SET' : 'NOT SET') . PHP_EOL;
echo 'Webhook URL: ' . (defined('WEBHOOK_URL') ? 'SET' : 'NOT SET') . PHP_EOL;
echo 'Group Chat ID: ' . (defined('DRIVERS_GROUP_CHAT_ID') ? 'SET' : 'NOT SET') . PHP_EOL;
"

# Тест webhook
echo "🌐 Testing webhook..."
if curl -s -o /dev/null -w "%{http_code}" "https://your-domain.com/meetRiedeBot-test/" | grep -q "200"; then
    echo "✅ Webhook accessible"
else
    echo "❌ Webhook not accessible"
fi

echo "✅ Test completed!"
echo "📊 Check logs: tail -f $TEST_DIR/logs/test.log"
