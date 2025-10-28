#!/bin/bash

# Настройка тестовой среды
# Использование: ./scripts/setup-test-environment.sh

set -e

echo "🧪 Setting up test environment..."

# Создание тестовой директории
TEST_DIR="/var/www/html/meetRiedeBot-test"
echo "📁 Creating test directory: $TEST_DIR"

sudo mkdir -p "$TEST_DIR"
sudo chown -R www-data:www-data "$TEST_DIR"

# Копирование файлов
echo "📋 Copying files to test environment..."
sudo cp -r src/* "$TEST_DIR/"
sudo cp config/config.example.php "$TEST_DIR/config.php"

# Создание тестовой конфигурации
echo "⚙️ Creating test configuration..."
sudo tee "$TEST_DIR/config.php" > /dev/null << 'EOF'
<?php
// Тестовая конфигурация MeetRide Bot
define('BOT_TOKEN', 'TEST_BOT_TOKEN');
define('WEBHOOK_URL', 'https://your-domain.com/meetRiedeBot-test/');
define('DRIVERS_GROUP_CHAT_ID', -1002544521661); // Боевая группа
define('BITRIX24_DOMAIN', 'your-test.bitrix24.ru');
define('BITRIX24_WEBHOOK_CODE', 'TEST_WEBHOOK_CODE');
define('DRIVER_ACCEPTED_STAGE_ID', 'PREPAYMENT_INVOICE');
define('DRIVER_ID_FIELD', 'UF_CRM_1751185017761');
define('PASSENGERS_FIELD', 'UF_CRM_1751271798896');
define('HIDDEN_FIELD', 'UF_CRM_1751271841129');
define('ASSIGNED_BY_ID', '1');
?>
EOF

# Создание логов
echo "📊 Setting up logs..."
sudo mkdir -p "$TEST_DIR/logs"
sudo touch "$TEST_DIR/logs/test.log"
sudo chown -R www-data:www-data "$TEST_DIR/logs"

# Создание тестового скрипта
echo "🔧 Creating test script..."
sudo tee "$TEST_DIR/test.php" > /dev/null << 'EOF'
<?php
// Тестовый скрипт для проверки бота
require_once 'config.php';
require_once 'botManager.php';

echo "🧪 MeetRide Bot Test Environment\n";
echo "================================\n";
echo "Bot Token: " . (defined('BOT_TOKEN') ? 'SET' : 'NOT SET') . "\n";
echo "Webhook URL: " . (defined('WEBHOOK_URL') ? 'SET' : 'NOT SET') . "\n";
echo "Group Chat ID: " . (defined('DRIVERS_GROUP_CHAT_ID') ? 'SET' : 'NOT SET') . "\n";
echo "Bitrix24 Domain: " . (defined('BITRIX24_DOMAIN') ? 'SET' : 'NOT SET') . "\n";
echo "================================\n";

// Тест синтаксиса
echo "PHP Syntax Check:\n";
$syntax_check = shell_exec('php -l botManager.php 2>&1');
echo $syntax_check;

echo "\n✅ Test environment ready!\n";
echo "🌐 Access: https://your-domain.com/meetRiedeBot-test/test.php\n";
?>
EOF

# Настройка прав
sudo chown -R www-data:www-data "$TEST_DIR"
sudo chmod -R 755 "$TEST_DIR"

echo "✅ Test environment setup complete!"
echo "📁 Test directory: $TEST_DIR"
echo "🔧 Next steps:"
echo "1. Update config.php with your test credentials"
echo "2. Set up test webhook in Bitrix24"
echo "3. Create test Telegram group"
echo "4. Run: ./scripts/deploy-to-test.sh"
