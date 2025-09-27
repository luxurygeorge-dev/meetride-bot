<?php
/**
 * MeetRide Bot Configuration Template
 * Скопируйте этот файл в config.php и укажите реальные значения
 */

return [
    // Telegram Bot Configuration
    'telegram' => [
        'bot_token' => 'YOUR_BOT_TOKEN_HERE',
        'production_chat_id' => 'YOUR_PRODUCTION_CHAT_ID',
        'test_chat_id' => 'YOUR_TEST_CHAT_ID',
        'environment' => 'production', // production или test
    ],

    // Bitrix24 Configuration
    'bitrix24' => [
        'domain' => 'your-domain.bitrix24.ru',
        'webhook_url' => 'https://your-domain.bitrix24.ru/rest/1/YOUR_WEBHOOK_CODE/',
        'default_driver_contact_id' => 39,
    ],

    // Bot Settings
    'bot' => [
        'log_level' => 'info', // debug, info, error
        'log_file' => __DIR__ . '/../logs/bot.log',
        'debug_mode' => false,
    ],

    // Deal Stages (Bitrix24 IDs)
    'stages' => [
        'new' => 'NEW',
        'driver_accepted' => 'PREPARATION',
        'executing' => 'PREPAYMENT_INVOICE',
        'completed' => 'FINAL_INVOICE',
    ],

    // Field IDs (Bitrix24)
    'fields' => [
        'driver_telegram_id' => 'UF_CRM_1751271798897',
        'passengers' => 'UF_CRM_1751271798896',
        'driver_sum' => 'UF_CRM_1751271798898',
        'address_from' => 'UF_CRM_1751271798899',
        'address_to' => 'UF_CRM_1751271798900',
        'travel_date' => 'UF_CRM_1751271798901',
    ],
];